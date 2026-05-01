<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\Result;
use App\Services\AnswerEvaluationService;
use App\Services\ExamEntryPipelineService;
use App\Services\ProctoringGlobalControlService;
use App\Services\ProctoringOrchestratorService;
use App\Support\ExamSessionStateResolver;
use App\Support\ProctoringCapabilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ProctoringOrchestratorService $orchestrator,
        private readonly ExamEntryPipelineService $entryPipeline,
        private readonly ProctoringGlobalControlService $globalControl,
        private readonly AnswerEvaluationService $answerEvaluation,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:quizzes,id'],
            'face_embedding' => ['required', 'array', 'min:3'],
            'face_embedding.*' => ['numeric'],
            'face_retry_attempt' => ['nullable', 'integer', 'min:0', 'max:1'],
            'hardware_concurrency' => ['nullable', 'integer', 'min:1', 'max:512'],
            'device_memory_gb' => ['nullable', 'numeric', 'min:1'],
            'network_effective_type' => ['nullable', 'string', 'max:32'],
            'save_data' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->entryPipeline->execute($request, $validated));
    }

    public function state(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentOwnsSession($request, $examSession);

        $fresh = $examSession->fresh();
        if ($fresh && $fresh->status !== 'submitted') {
            $this->autoExpireIfTimedOut($fresh);
        }

        return response()->json(
            ExamSessionStateResolver::payload($examSession->fresh(), $this->globalControl->getControl()),
        );
    }

    public function saveAnswer(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);
        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'answer_text' => ['nullable', 'string'],
            'answer_payload' => ['nullable', 'array'],
        ]);

        $questionBelongsToExam = Question::query()
            ->where('id', $validated['question_id'])
            ->where('quiz_id', $examSession->exam_id)
            ->exists();
        abort_unless($questionBelongsToExam, 422, 'Question does not belong to this exam.');

        ExamSessionAnswer::query()->updateOrCreate(
            [
                'exam_session_id' => $examSession->id,
                'question_id' => $validated['question_id'],
            ],
            [
                'answer_text' => $validated['answer_text'] ?? null,
                'answer_payload' => $validated['answer_payload'] ?? null,
                'saved_at' => now(),
            ],
        );

        return response()->json(['status' => 'saved']);
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
        $this->authorizeStudentSession($request, $examSession);
        $this->submitSession($examSession, 'submitted', 'manual_submit');

        return response()->json(['status' => 'submitted']);
    }

    public function forceSubmit(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $this->submitSession($examSession, 'submitted_held', 'force_submit');

        return response()->json(['status' => 'submitted_held', 'reason' => 'force_submit']);
    }

    public function reviewTimeline(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);

        $events = ProctoringEvent::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->where('metadata->session_id', $examSession->session_id)
            ->orderBy('created_at')
            ->get(['event_type', 'severity', 'flagged', 'metadata', 'created_at']);

        $capturedImages = $events->filter(function ($event) {
            return ! empty($event->metadata['payload']['file_path']);
        })->values();

        return response()->json([
            'session_id' => $examSession->session_id,
            'exam_status' => $examSession->exam_status,
            'risk_state' => $examSession->risk_state,
            'violation_score' => $examSession->violation_score,
            'events' => $events,
            'captured_images' => $capturedImages,
            'result' => Result::query()
                ->where('user_id', $examSession->student_id)
                ->where('quiz_id', $examSession->exam_id)
                ->first(['score', 'status', 'exam_status']),
        ]);
    }

    public function releaseHeldResult(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $this->applyReviewDecision($examSession, 'released', 'Result released after review.');

        return response()->json(['status' => 'released']);
    }

    public function confirmFail(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $this->applyReviewDecision($examSession, 'confirmed_fail', 'Result marked failed due to violations.');

        return response()->json(['status' => 'confirmed_fail']);
    }

    public function overrideDecision(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
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

        $examSession->update([
            'status' => 'submitted',
            'end_time' => now(),
            'exam_status' => $examStatus,
            'risk_state' => $examStatus === 'submitted_held' ? 'locked' : $examSession->risk_state,
        ]);

        $timeTaken = max(0, $examSession->start_time?->diffInSeconds($examSession->end_time ?? now()) ?? 0);

        $submitted = $examSession->fresh();
        $eval = $this->answerEvaluation->evaluateAndPersist($submitted);
        $score = $eval['total_score'];

        Result::query()->updateOrCreate(
            [
                'user_id' => $examSession->student_id,
                'quiz_id' => $examSession->exam_id,
            ],
            [
                'score' => $score,
                'time_taken' => $timeTaken,
                'status' => 'submitted',
                'exam_status' => $examStatus,
                'review_note' => $reason,
                'submitted_at' => now(),
            ],
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
    }
}
