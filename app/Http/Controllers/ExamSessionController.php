<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ExamSessionQuestion;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Services\AnswerEvaluationService;
use App\Services\AnswerPayloadValidator;
use App\Services\ExamAnswerSynthesisService;
use App\Services\ExamEntryPipelineService;
use App\Services\ExamOtpService;
use App\Services\ExamRedisService;
use App\Services\ExamSessionInvalidateForRetakeService;
use App\Services\ProctoringGlobalControlService;
use App\Services\ProctoringOrchestratorService;
use App\Services\ResultFinalizationService;
use App\Services\SensitiveStorageService;
use App\Services\SystemExamPolicyService;
use App\Support\ExamRuntimeStateExtension;
use App\Support\ExamSessionStateResolver;
use App\Support\ExamSessionTimer;
use App\Support\ProctoringCapabilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ProctoringOrchestratorService $orchestrator,
        private readonly ExamEntryPipelineService $entryPipeline,
        private readonly ProctoringGlobalControlService $globalControl,
        private readonly AnswerEvaluationService $answerEvaluation,
        private readonly ExamAnswerSynthesisService $answerSynthesis,
        private readonly ResultFinalizationService $resultFinalization,
        private readonly ExamOtpService $examOtp,
        private readonly ExamRedisService $examRedis,
        private readonly SystemExamPolicyService $examPolicy,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $this->assertStudentActiveAndOnboardedForExamApi($request);

        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:quizzes,id'],
            'verification_snapshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'hardware_concurrency' => ['nullable', 'integer', 'min:1', 'max:512'],
            'device_memory_gb' => ['nullable', 'numeric', 'min:1'],
            'network_effective_type' => ['nullable', 'string', 'max:32'],
            'save_data' => ['nullable', 'boolean'],
        ]);

        $payload = $this->entryPipeline->execute($request, $validated);
        $status = $payload['status'] ?? '';
        if ($status === 'service_unavailable') {
            return response()->json($payload, 503);
        }

        return response()->json($payload, 200);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'student', 403);

        $this->assertStudentActiveAndOnboardedForExamApi($request);

        abort_unless($this->examPolicy->isOtpEnabled(), 422, 'Phone verification is disabled for this institution.');

        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:quizzes,id'],
            'otp_code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $this->examOtp->verifySubmittedOtp(
            $request->user(),
            (int) $validated['exam_id'],
            $validated['otp_code'],
        );

        return response()->json([
            'status' => 'otp_verified',
            'exam_id' => (int) $validated['exam_id'],
        ]);
    }

    public function state(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentOwnsSession($request, $examSession);

        $fresh = $examSession->fresh();
        if ($fresh && $fresh->status !== 'submitted') {
            $this->autoExpireIfTimedOut($fresh);
        }

        $fresh = $examSession->fresh();
        abort_if($fresh === null, 404);

        if ($fresh->status === 'active') {
            $fresh->forceFill(['last_seen_at' => now()])->save();
            $fresh = $examSession->fresh();
        }

        return response()->json($this->mergeStudentExamStatePayload($fresh));
    }

    public function resume(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentOwnsSession($request, $examSession);

        $row = $examSession->fresh();
        abort_if($row === null, 404);
        abort_unless($row->status === 'paused', 422, 'Session is not paused.');

        $row->loadMissing('exam');
        abort_if($row->exam === null, 422);
        abort_unless($row->exam->isAvailableForStudentToStart(now()), 422, 'This exam is no longer in its scheduled window.');

        $segmentStart = $row->pause_segment_started_at ?? now();
        $add = max(0, $segmentStart->diffInSeconds(now()));

        $row->update([
            'status' => 'active',
            'accumulated_pause_seconds' => (int) ($row->accumulated_pause_seconds ?? 0) + $add,
            'pause_segment_started_at' => null,
            'last_seen_at' => now(),
        ]);

        $out = $row->fresh();
        abort_if($out === null, 404);

        return response()->json(array_merge(
            ['status' => 'resumed'],
            $this->mergeStudentExamStatePayload($out),
        ));
    }

    public function storeVerificationImage(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentProctoringSession($request, $examSession);

        if ($examSession->verification_image_path !== null && $examSession->verification_image_path !== '') {
            return response()->json(['status' => 'already_stored']);
        }

        $validated = $request->validate([
            'snapshot' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $dir = sprintf(
            'proctoring/user_%d/session_%d',
            (int) $examSession->student_id,
            (int) $examSession->id,
        );

        $path = $dir.'/verification.jpg';

        Storage::disk('local')->put($path, file_get_contents($validated['snapshot']->getRealPath()));

        $examSession->forceFill(['verification_image_path' => $path])->save();

        return response()->json(['status' => 'stored']);
    }

    public function saveAnswer(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);
        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'answer_payload' => ['required', 'array'],
            'client_revision' => ['nullable', 'integer', 'min:1'],
        ]);

        $question = Question::query()
            ->where('id', $validated['question_id'])
            ->where('quiz_id', $examSession->exam_id)
            ->first();
        abort_unless($question !== null, 422, 'Question does not belong to this exam.');

        $sessionQuestion = ExamSessionQuestion::query()
            ->where('exam_session_id', $examSession->id)
            ->where('question_id', $validated['question_id'])
            ->first();

        if (ExamSessionQuestion::query()->where('exam_session_id', $examSession->id)->exists()) {
            abort_unless($sessionQuestion !== null, 422, 'Question is not assigned to this exam attempt.');
        }

        try {
            if ($question->type === 'mcq' && $sessionQuestion !== null) {
                $map = $sessionQuestion->mcqDisplayToOriginal();
                if ($map !== null && $map !== []) {
                    $normalized = AnswerPayloadValidator::remapMcqPayloadToOriginalIndices($validated['answer_payload'], $map);
                } else {
                    $normalized = AnswerPayloadValidator::validate($question, $validated['answer_payload']);
                }
            } else {
                $normalized = AnswerPayloadValidator::validate($question, $validated['answer_payload']);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid answer payload.',
                'errors' => $e->errors(),
            ], 422);
        }

        $incomingRev = $validated['client_revision'] ?? null;
        $existing = ExamSessionAnswer::query()
            ->where('exam_session_id', $examSession->id)
            ->where('question_id', $validated['question_id'])
            ->first();

        if ($incomingRev !== null && $existing !== null && (int) $existing->client_revision > (int) $incomingRev) {
            return response()->json([
                'status' => 'noop',
                'reason' => 'stale_revision',
                'client_revision' => (int) $existing->client_revision,
            ]);
        }

        $storedRevision = $incomingRev === null
            ? (int) ($existing?->client_revision ?? 0)
            : max((int) ($existing?->client_revision ?? 0), (int) $incomingRev);

        ExamSessionAnswer::query()->updateOrCreate(
            [
                'exam_session_id' => $examSession->id,
                'question_id' => $validated['question_id'],
            ],
            [
                'answer_text' => null,
                'answer_payload' => $normalized,
                'saved_at' => now(),
                'client_revision' => $storedRevision,
            ],
        );

        $examSession->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'status' => 'saved',
            'client_revision' => $storedRevision,
        ]);
    }

    public function heartbeat(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);

        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $fresh = $examSession->fresh();
        if ($fresh && $fresh->status === 'active') {
            $fresh->forceFill(['last_seen_at' => now()])->save();
        }

        return response()->json(['status' => $fresh?->status ?? $examSession->status]);
    }

    public function proctoringCapability(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'student', 403);

        $validated = $request->validate([
            'hardware_concurrency' => ['nullable', 'integer', 'min:1', 'max:512'],
            'device_memory_gb' => ['nullable', 'numeric', 'min:1'],
            'network_effective_type' => ['nullable', 'string', 'max:32'],
            'save_data' => ['nullable', 'boolean'],
        ]);

        return response()->json(ProctoringCapabilityResolver::resolve($validated));
    }

    public function logProctoringEvent(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentProctoringSession($request, $examSession);
        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'max:100'],
            'severity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'flagged' => ['nullable', 'boolean'],
            'metadata' => ['required', 'array'],
            'metadata.session_id' => ['required', 'string'],
            'metadata.student_id' => ['required', 'integer'],
            'metadata.exam_id' => ['required', 'integer'],
        ]);

        /** @var array<string, mixed> $metadata */
        $metadata = $request->input('metadata', []);
        abort_unless(is_array($metadata), 422, 'Invalid metadata.');

        abort_unless($metadata['session_id'] === $examSession->session_id, 422, 'session_id mismatch.');
        abort_unless((int) $metadata['student_id'] === (int) $examSession->student_id, 422, 'student_id mismatch.');
        abort_unless((int) $metadata['exam_id'] === (int) $examSession->exam_id, 422, 'exam_id mismatch.');

        if (($validated['event_type'] ?? '') === 'essay_clipboard_attempt') {
            $this->validateEssayClipboardAttemptMetadata($metadata);
        }

        if (! $this->allowsProctoringEventIngest((string) $validated['event_type'])) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'proctoring_disabled',
            ]);
        }

        $this->examRedis->enforceProctoringEventBudget($examSession->session_id, 1);

        $decision = $this->orchestrator->ingestEvent(
            examSession: $examSession,
            eventType: $validated['event_type'],
            metadata: $metadata,
            severity: $validated['severity'] ?? null,
            flagged: (bool) ($validated['flagged'] ?? false),
        );

        if ($decision['auto_submit'] === true) {
            $this->submitSession($examSession->fresh(), 'submitted_held', 'violation_threshold');

            return response()->json([
                'status' => 'submitted_held',
                'reason' => 'violation_threshold',
                'message' => 'Your exam has been submitted due to violation detection. Your result is under review. Please contact your lecturer.',
            ]);
        }

        return response()->json([
            'status' => 'logged',
            'violation_score' => $decision['score'],
            'risk_state' => $decision['risk_state'],
            'action' => $decision['action'],
        ]);
    }

    public function logProctoringEventBatch(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentProctoringSession($request, $examSession);
        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $payload = $this->decodeProctoringBatchPayload($request);

        validator($payload, [
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.event_type' => ['required', 'string', 'max:100'],
            'events.*.severity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'events.*.flagged' => ['nullable', 'boolean'],
            'events.*.metadata' => ['required', 'array'],
            'events.*.metadata.session_id' => ['required', 'string'],
            'events.*.metadata.student_id' => ['required', 'integer'],
            'events.*.metadata.exam_id' => ['required', 'integer'],
        ])->validate();

        /** @var list<array<string, mixed>> $events */
        $events = $payload['events'];

        $eventsToProcess = array_values(array_filter(
            $events,
            fn (array $e): bool => $this->allowsProctoringEventIngest((string) ($e['event_type'] ?? '')),
        ));

        if ($eventsToProcess === []) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'proctoring_disabled',
                'processed' => 0,
            ]);
        }

        $this->examRedis->enforceProctoringEventBudget($examSession->session_id, count($eventsToProcess));

        foreach ($eventsToProcess as $eventPayload) {
            abort_unless($eventPayload['metadata']['session_id'] === $examSession->session_id, 422, 'session_id mismatch.');
            abort_unless((int) $eventPayload['metadata']['student_id'] === (int) $examSession->student_id, 422, 'student_id mismatch.');
            abort_unless((int) $eventPayload['metadata']['exam_id'] === (int) $examSession->exam_id, 422, 'exam_id mismatch.');

            if (($eventPayload['event_type'] ?? '') === 'essay_clipboard_attempt') {
                $this->validateEssayClipboardAttemptMetadata($eventPayload['metadata']);
            }

            $decision = $this->orchestrator->ingestEvent(
                examSession: $examSession->fresh(),
                eventType: $eventPayload['event_type'],
                metadata: $eventPayload['metadata'],
                severity: $eventPayload['severity'] ?? null,
                flagged: (bool) ($eventPayload['flagged'] ?? false),
            );

            if ($decision['auto_submit'] === true) {
                $this->submitSession($examSession->fresh(), 'submitted_held', 'violation_threshold');

                return response()->json([
                    'status' => 'submitted_held',
                    'reason' => 'violation_threshold',
                    'message' => 'Your exam has been submitted due to violation detection. Your result is under review. Please contact your lecturer.',
                    'last_decision' => $decision,
                ]);
            }

            $examSession = $examSession->fresh();
        }

        return response()->json([
            'status' => 'logged',
            'processed' => count($eventsToProcess),
            'violation_score' => $examSession->violation_score,
            'risk_state' => $examSession->risk_state,
        ]);
    }

    /**
     * Essay clipboard audits are accepted even when institution proctoring is off (log-only scoring via violation_weights).
     */
    private function allowsProctoringEventIngest(string $eventType): bool
    {
        return $this->examPolicy->isProctoringEnabled()
            || $eventType === 'essay_clipboard_attempt';
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function validateEssayClipboardAttemptMetadata(array $metadata): void
    {
        validator($metadata, [
            'question_id' => ['required', 'integer'],
            'action_type' => ['required', 'string', Rule::in(['paste', 'copy', 'cut', 'drop', 'contextmenu'])],
        ])->validate();
    }

    public function submit(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentOwnsSession($request, $examSession);

        $fresh = $examSession->fresh();
        abort_if($fresh === null, 404);

        if ($fresh->status === 'submitted') {
            return response()->json(array_merge(
                ['status' => 'submitted', 'already_submitted' => true],
                $this->mergeStudentExamStatePayload($fresh),
            ));
        }

        abort_unless(in_array($fresh->status, ['active', 'paused'], true), 422, 'Session is not active.');

        $this->submitSession($examSession, 'submitted', 'manual_submit');

        return response()->json(['status' => 'submitted']);
    }

    public function forceSubmit(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('manageResults', $quiz);

        $this->submitSession($examSession, 'submitted_held', 'force_submit');

        return response()->json(['status' => 'submitted_held', 'reason' => 'force_submit']);
    }

    /**
     * Examiner-only (via route): delete all attempts for this student on this quiz so they may retake.
     */
    public function invalidateForRetake(
        Request $request,
        ExamSession $examSession,
        ExamSessionInvalidateForRetakeService $invalidator,
    ): RedirectResponse {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 404);
        $this->authorize('manageResults', $quiz);

        $invalidator->invalidate((int) $examSession->student_id, (int) $examSession->exam_id);

        return redirect()
            ->back()
            ->with('status', __('This attempt was cleared. The student can start the exam again.'));
    }

    public function reviewTimeline(Request $request, ExamSession $examSession, SensitiveStorageService $sensitiveStorage): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('manageResults', $quiz);

        $events = ProctoringEvent::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->where('metadata->session_id', $examSession->session_id)
            ->orderBy('created_at')
            ->get(['id', 'event_type', 'severity', 'flagged', 'action_taken', 'metadata', 'created_at']);

        $safeEvents = $events->map(fn (ProctoringEvent $event) => $this->sanitizeReviewTimelineEvent(
            $examSession,
            $event,
            $sensitiveStorage,
        ))->values()->all();

        $result = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->first(['score', 'status', 'exam_status']);

        return response()->json([
            'session_id' => $examSession->session_id,
            'exam_status' => $examSession->exam_status,
            'risk_state' => $examSession->risk_state,
            'violation_score' => $examSession->violation_score,
            'events' => $safeEvents,
            'result' => $result ? [
                'score' => $result->score,
                'status' => $result->status,
                'exam_status' => $result->exam_status,
            ] : null,
        ]);
    }

    /**
     * @return array{
     *     id:int,
     *     event_type:string,
     *     action:?string,
     *     flagged:bool,
     *     created_at:?string,
     *     severity:int,
     *     label:?string,
     *     has_evidence:bool,
     *     evidence_url?:string
     * }
     */
    private function sanitizeReviewTimelineEvent(
        ExamSession $examSession,
        ProctoringEvent $event,
        SensitiveStorageService $sensitiveStorage,
    ): array {
        $meta = is_array($event->metadata) ? $event->metadata : [];
        $path = $meta['file_path'] ?? data_get($meta, 'payload.file_path');
        $hasEvidence = is_string($path) && $path !== '' && $sensitiveStorage->existsAnywhere($path);

        $action = $event->action_taken;
        $actionStr = is_string($action) && $action !== '' ? $action : null;

        $label = $actionStr;
        if ($label === null && $event->flagged) {
            $label = 'flagged';
        }

        $row = [
            'id' => (int) $event->id,
            'event_type' => (string) $event->event_type,
            'action' => $actionStr,
            'flagged' => (bool) $event->flagged,
            'created_at' => $event->created_at?->toIso8601String(),
            'severity' => (int) $event->severity,
            'label' => $label,
            'has_evidence' => $hasEvidence,
        ];

        if ($hasEvidence) {
            $row['evidence_url'] = route('examiner.exam-sessions.evidence.event', [$examSession, $event]);
        }

        return $row;
    }

    public function releaseHeldResult(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('manageResults', $quiz);

        $this->applyReviewDecision($examSession, 'released', 'Result released after review.');

        return response()->json(['status' => 'released']);
    }

    public function confirmFail(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('manageResults', $quiz);

        $this->applyReviewDecision($examSession, 'confirmed_fail', 'Result marked failed due to violations.');

        return response()->json(['status' => 'confirmed_fail']);
    }

    public function overrideDecision(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('manageResults', $quiz);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->applyReviewDecision($examSession, 'overridden', $validated['note'] ?? 'Decision overridden by reviewer.');

        return response()->json(['status' => 'overridden']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeProctoringBatchPayload(Request $request): array
    {
        $encoding = strtolower((string) $request->header('X-Qs-Encoding', ''));

        if ($encoding === 'gzip') {
            abort_unless(extension_loaded('zlib'), 415, 'gzip support unavailable.');
            $decoded = gzdecode($request->getContent());
            abort_if($decoded === false, 422, 'Invalid gzip payload.');
            $data = json_decode($decoded, true);
            abort_unless(is_array($data), 422, 'Invalid batch JSON.');

            return $data;
        }

        $data = $request->json()->all();
        abort_unless(is_array($data), 422, 'Invalid batch payload.');

        return $data;
    }

    private function authorizeStudentOwnsSession(Request $request, ExamSession $examSession): void
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'student', 403);
        abort_unless((int) $examSession->student_id === (int) $user->id, 403);
    }

    private function authorizeStudentSession(Request $request, ExamSession $examSession): void
    {
        $this->authorizeStudentOwnsSession($request, $examSession);
        abort_unless($examSession->status === 'active', 422, 'Session is not active.');
    }

    private function authorizeStudentProctoringSession(Request $request, ExamSession $examSession): void
    {
        $this->authorizeStudentOwnsSession($request, $examSession);
        abort_unless(in_array($examSession->status, ['active', 'paused'], true), 422, 'Session is not active.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeStudentExamStatePayload(ExamSession $examSession): array
    {
        $examSession->loadMissing(['exam', 'answers']);

        $base = ExamSessionStateResolver::payload($examSession, $this->globalControl->getControl());
        $runtime = ExamRuntimeStateExtension::forSession($examSession);
        $merged = array_merge($base, $runtime);

        $resultRow = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->first(['status']);

        $held =
            $examSession->status === 'submitted'
            && (
                ($resultRow !== null && $resultRow->status === 'held')
                || $this->resultFinalization->resolveStatus($examSession) === 'held'
            );

        if ($held) {
            return $this->scrubHeldStudentExamPayload($merged);
        }

        return $this->withAssignmentStudentStateHints($merged, $examSession);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function scrubHeldStudentExamPayload(array $payload): array
    {
        unset($payload['violation_score']);

        $payload['result'] = [
            'status' => 'held',
            'message' => 'Your result is under review. Contact your examiner.',
        ];

        if (isset($payload['exam']) && is_array($payload['exam'])) {
            unset($payload['exam']['total_marks']);
        }

        if (! empty($payload['sections']) && is_array($payload['sections'])) {
            foreach ($payload['sections'] as $si => $section) {
                if (empty($section['questions']) || ! is_array($section['questions'])) {
                    continue;
                }
                foreach ($section['questions'] as $qi => $question) {
                    unset($payload['sections'][$si]['questions'][$qi]['marks']);
                }
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function withAssignmentStudentStateHints(array $merged, ExamSession $examSession): array
    {
        $exam = $examSession->exam;
        if ($exam === null || ! $exam->isAssignment()) {
            return $merged;
        }

        $result = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $exam->id)
            ->first(['status', 'score', 'feedback', 'graded_at']);

        $gradesVisible = $exam->assignmentGradesVisibleToStudents();
        $feedbackPlain = null;
        if ($gradesVisible && $result?->status === 'graded') {
            $feedbackPlain = $this->formatAssignmentFeedbackPlain(
                is_array($result->feedback) ? $result->feedback : null,
            );
        }

        $totalMarks = null;
        if (isset($merged['exam']['total_marks']) && is_numeric($merged['exam']['total_marks'])) {
            $totalMarks = (float) $merged['exam']['total_marks'];
        }
        $percentage = null;
        if ($gradesVisible && $result?->status === 'graded' && $totalMarks !== null && $totalMarks > 0) {
            $percentage = round(((float) $result->score) / $totalMarks * 100, 2);
        }

        $merged['assignment_student_view'] = [
            'session_submitted_late' => (bool) ($examSession->submitted_late ?? false),
            'grades_visible_to_student' => $gradesVisible,
            'result_status' => $result?->status ?? 'pending_manual',
            'score' => ($gradesVisible && $result?->status === 'graded') ? (float) $result->score : null,
            'score_percentage' => $percentage,
            'examiner_feedback' => $feedbackPlain,
            'graded_at' => ($gradesVisible && $result?->graded_at) ? $result->graded_at->toAtomString() : null,
            'status_heading' => $examSession->status === 'submitted'
                ? ($examSession->submitted_late ? __('Submitted late') : __('Submitted'))
                : __('In progress'),
            'grade_heading' => ! $gradesVisible
                ? __('Grades not released to students yet')
                : (($result?->status === 'graded')
                    ? __('Grade visible to you')
                    : (($result?->status === 'pending_manual')
                        ? __('Awaiting marking')
                        : __('Result status: :s', ['s' => (string) ($result?->status ?? '—')]))),
        ];

        return $merged;
    }

    /**
     * @param  array<string, mixed>|null  $feedback
     */
    private function formatAssignmentFeedbackPlain(?array $feedback): ?string
    {
        if ($feedback === null || $feedback === []) {
            return null;
        }

        if (isset($feedback['note']) && is_string($feedback['note'])) {
            $t = trim($feedback['note']);

            return $t !== '' ? $t : null;
        }

        if (isset($feedback['text']) && is_string($feedback['text'])) {
            $t = trim($feedback['text']);

            return $t !== '' ? $t : null;
        }

        $encoded = json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $encoded !== '' ? $encoded : null;
    }

    private function autoExpireIfTimedOut(ExamSession $examSession): bool
    {
        $examSession->loadMissing('exam');

        if ($examSession->exam?->isAssignment()) {
            return false;
        }

        $durationMinutes = (int) ($examSession->exam?->duration_minutes ?? 0);
        if ($durationMinutes <= 0) {
            return false;
        }

        $remaining = ExamSessionTimer::timeRemainingSeconds($examSession, $examSession->exam, now());
        if ($remaining <= 0 && $examSession->status !== 'submitted') {
            $this->submitSession($examSession, 'submitted', 'timeout');

            return true;
        }

        return false;
    }

    private function submitSession(ExamSession $examSession, string $examStatus, string $reason): void
    {
        if ($examSession->status === 'submitted') {
            return;
        }

        $examSession->loadMissing('exam');
        $exam = $examSession->exam;

        $submittedLate = false;
        if ($exam !== null && $exam->isAssignment() && $exam->due_at !== null) {
            $submittedLate = now()->isAfter($exam->due_at);
        }

        $examId = (int) $examSession->exam_id;

        $accum = (int) ($examSession->accumulated_pause_seconds ?? 0);
        if ($examSession->pause_segment_started_at !== null) {
            $accum += max(0, $examSession->pause_segment_started_at->diffInSeconds(now()));
        }

        $examSession->update([
            'status' => 'submitted',
            'end_time' => now(),
            'exam_status' => $examStatus,
            'risk_state' => $examStatus === 'submitted_held' ? 'locked' : $examSession->risk_state,
            'accumulated_pause_seconds' => $accum,
            'pause_segment_started_at' => null,
            'submitted_late' => $submittedLate,
        ]);

        $this->examRedis->decrementActiveSessions($examId);

        $submitted = $examSession->fresh(['exam']);
        $this->answerSynthesis->ensureEveryQuestionHasAnswer($submitted);

        $endAt = $submitted->end_time ?? now();
        $timeTaken = ExamSessionTimer::activeWritingSeconds($submitted, $endAt);

        $gradedSession = $submitted->fresh(['exam.questions', 'answers']);
        $this->answerEvaluation->evaluateAndPersist($gradedSession);

        $finalSession = $submitted->fresh(['answers']);
        $this->resultFinalization->syncAfterSubmission(
            $finalSession,
            $timeTaken,
            $reason,
        );

        if ($exam !== null && $exam->isAssignment()) {
            ActivityLog::query()->create([
                'user_id' => $examSession->student_id,
                'quiz_id' => $exam->id,
                'event_type' => 'assignment_submitted',
                'event_data' => [
                    'exam_session_id' => $examSession->id,
                    'session_id' => $examSession->session_id,
                    'submitted_late' => $submittedLate,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);
        }
    }

    private function applyReviewDecision(ExamSession $examSession, string $decision, string $note): void
    {
        $examSession->update([
            'exam_status' => 'reviewed',
            'risk_state' => 'suspicious',
        ]);

        Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->update([
                'exam_status' => 'reviewed',
                'review_decision' => $decision,
                'review_note' => $note,
            ]);

        $this->resultFinalization->refreshResultFromSessionState($examSession->fresh(['answers']));
    }

    private function assertStudentActiveAndOnboardedForExamApi(Request $request): void
    {
        $user = $request->user();

        abort_unless($user !== null && $user->role === 'student', 403);

        abort_unless($user->is_active, 422, __('Your student account is not active. Please contact your coordinator.'));

        abort_unless($user->student_onboarded_at !== null, 422, __('Please complete your student onboarding before starting an exam.'));
    }
}
