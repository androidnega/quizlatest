<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
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
use App\Services\ProctoringGlobalControlService;
use App\Services\ProctoringOrchestratorService;
use App\Services\ResultFinalizationService;
use App\Services\SystemExamPolicyService;
use App\Support\ExamRuntimeStateExtension;
use App\Support\ExamSessionStateResolver;
use App\Support\ProctoringCapabilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:quizzes,id'],
            'face_embedding' => ['nullable', 'array', 'min:3'],
            'face_embedding.*' => ['numeric'],
            'face_retry_attempt' => ['nullable', 'integer', 'min:0', 'max:1'],
            'hardware_concurrency' => ['nullable', 'integer', 'min:1', 'max:512'],
            'device_memory_gb' => ['nullable', 'numeric', 'min:1'],
            'network_effective_type' => ['nullable', 'string', 'max:32'],
            'save_data' => ['nullable', 'boolean'],
        ]);

        $payload = $this->entryPipeline->execute($request, $validated);
        $httpStatus = (($payload['status'] ?? '') === 'service_unavailable') ? 503 : 200;

        return response()->json($payload, $httpStatus);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'student', 403);

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

        return response()->json($this->mergeStudentExamStatePayload($fresh));
    }

    public function storeVerificationImage(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);

        if ($examSession->verification_image_path !== null && $examSession->verification_image_path !== '') {
            return response()->json(['status' => 'already_stored']);
        }

        $validated = $request->validate([
            'snapshot' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $safeSessionKey = preg_replace('/[^A-Za-z0-9_-]/', '', $examSession->session_id) ?: 'session';
        $dir = sprintf(
            'proctoring/user_%d/session_%s',
            (int) $examSession->student_id,
            $safeSessionKey,
        );

        $filename = 'verification_'.now()->format('YmdHis').'_'.Str::random(8).'.jpg';
        $path = $dir.'/'.$filename;

        Storage::disk('public')->put($path, file_get_contents($validated['snapshot']->getRealPath()));

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

        try {
            $normalized = AnswerPayloadValidator::validate($question, $validated['answer_payload']);
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

        return response()->json(['status' => $examSession->status]);
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
        $this->authorizeStudentSession($request, $examSession);
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

        abort_unless($validated['metadata']['session_id'] === $examSession->session_id, 422, 'session_id mismatch.');
        abort_unless((int) $validated['metadata']['student_id'] === (int) $examSession->student_id, 422, 'student_id mismatch.');
        abort_unless((int) $validated['metadata']['exam_id'] === (int) $examSession->exam_id, 422, 'exam_id mismatch.');

        if (! $this->examPolicy->isProctoringEnabled()) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'proctoring_disabled',
            ]);
        }

        $this->examRedis->enforceProctoringEventBudget($examSession->session_id, 1);

        $decision = $this->orchestrator->ingestEvent(
            examSession: $examSession,
            eventType: $validated['event_type'],
            metadata: $validated['metadata'],
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
        $this->authorizeStudentSession($request, $examSession);
        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $payload = $this->decodeProctoringBatchPayload($request);

        $validated = validator($payload, [
            'events' => ['required', 'array', 'min:1', 'max:25'],
            'events.*.event_type' => ['required', 'string', 'max:100'],
            'events.*.severity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'events.*.flagged' => ['nullable', 'boolean'],
            'events.*.metadata' => ['required', 'array'],
            'events.*.metadata.session_id' => ['required', 'string'],
            'events.*.metadata.student_id' => ['required', 'integer'],
            'events.*.metadata.exam_id' => ['required', 'integer'],
        ])->validate();

        if (! $this->examPolicy->isProctoringEnabled()) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'proctoring_disabled',
                'processed' => 0,
            ]);
        }

        $this->examRedis->enforceProctoringEventBudget($examSession->session_id, count($validated['events']));

        foreach ($validated['events'] as $eventPayload) {
            abort_unless($eventPayload['metadata']['session_id'] === $examSession->session_id, 422, 'session_id mismatch.');
            abort_unless((int) $eventPayload['metadata']['student_id'] === (int) $examSession->student_id, 422, 'student_id mismatch.');
            abort_unless((int) $eventPayload['metadata']['exam_id'] === (int) $examSession->exam_id, 422, 'exam_id mismatch.');

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
            'processed' => count($validated['events']),
            'violation_score' => $examSession->violation_score,
            'risk_state' => $examSession->risk_state,
        ]);
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
        $this->authorize('reviewHeldResults', $quiz);

        $this->submitSession($examSession, 'submitted_held', 'force_submit');

        return response()->json(['status' => 'submitted_held', 'reason' => 'force_submit']);
    }

    public function reviewTimeline(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('view', $quiz);

        $events = ProctoringEvent::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->where('metadata->session_id', $examSession->session_id)
            ->orderBy('created_at')
            ->get(['event_type', 'severity', 'flagged', 'metadata', 'created_at']);

        $capturedImages = $events->filter(function ($event) {
            return ! empty($event->metadata['payload']['file_path']);
        })->values();

        $result = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->first(['score', 'status', 'exam_status']);

        if ($result !== null) {
            $this->authorize('view', $result);
        }

        return response()->json([
            'session_id' => $examSession->session_id,
            'exam_status' => $examSession->exam_status,
            'risk_state' => $examSession->risk_state,
            'violation_score' => $examSession->violation_score,
            'events' => $events,
            'captured_images' => $capturedImages,
            'result' => $result ? [
                'score' => $result->score,
                'status' => $result->status,
                'exam_status' => $result->exam_status,
            ] : null,
        ]);
    }

    public function releaseHeldResult(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('reviewHeldResults', $quiz);

        $this->applyReviewDecision($examSession, 'released', 'Result released after review.');

        return response()->json(['status' => 'released']);
    }

    public function confirmFail(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('reviewHeldResults', $quiz);

        $this->applyReviewDecision($examSession, 'confirmed_fail', 'Result marked failed due to violations.');

        return response()->json(['status' => 'confirmed_fail']);
    }

    public function overrideDecision(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 403);
        $this->authorize('reviewHeldResults', $quiz);

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

        if ($examSession->status === 'submitted' && $this->resultFinalization->resolveStatus($examSession) === 'held') {
            return $this->scrubHeldStudentExamPayload($merged);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function scrubHeldStudentExamPayload(array $payload): array
    {
        unset($payload['violation_score']);

        $payload['result_visible'] = false;
        $payload['result_message'] = 'Your result is under review. Please contact your lecturer.';

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

    private function autoExpireIfTimedOut(ExamSession $examSession): bool
    {
        $examSession->loadMissing('exam');

        $durationMinutes = (int) ($examSession->exam?->duration_minutes ?? 0);
        if ($durationMinutes <= 0) {
            return false;
        }

        $expiresAt = $examSession->start_time?->copy()->addMinutes($durationMinutes);
        if ($expiresAt && now()->greaterThanOrEqualTo($expiresAt) && $examSession->status !== 'submitted') {
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

        $examId = (int) $examSession->exam_id;

        $examSession->update([
            'status' => 'submitted',
            'end_time' => now(),
            'exam_status' => $examStatus,
            'risk_state' => $examStatus === 'submitted_held' ? 'locked' : $examSession->risk_state,
        ]);

        $this->examRedis->decrementActiveSessions($examId);

        $submitted = $examSession->fresh();
        $this->answerSynthesis->ensureEveryQuestionHasAnswer($submitted);

        $timeTaken = max(0, $submitted->start_time?->diffInSeconds($submitted->end_time ?? now()) ?? 0);

        $gradedSession = $submitted->fresh(['exam.questions', 'answers']);
        $this->answerEvaluation->evaluateAndPersist($gradedSession);

        $finalSession = $submitted->fresh(['answers']);
        $this->resultFinalization->syncAfterSubmission(
            $finalSession,
            $timeTaken,
            $reason,
        );
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
}
