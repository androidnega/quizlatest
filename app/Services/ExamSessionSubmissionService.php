<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ExamSession;
use App\Support\ExamSessionTimer;
use Illuminate\Support\Facades\DB;

/**
 * Finalize exam sessions (manual submit, timeout, auto-expire on entry).
 */
final class ExamSessionSubmissionService
{
    public function __construct(
        private readonly ExamAnswerSynthesisService $answerSynthesis,
        private readonly AnswerEvaluationService $answerEvaluation,
        private readonly ResultFinalizationService $resultFinalization,
        private readonly ExamRuntimeService $examRuntime,
    ) {}

    public function autoExpireIfTimedOut(ExamSession $examSession): bool
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
            $this->submit($examSession, 'submitted', 'timeout');

            return true;
        }

        return false;
    }

    /**
     * Number of automatic retries Laravel will run for the submission
     * transaction on deadlock or serialization-level failures. The
     * pipeline is fully idempotent (CAS + Result::updateOrCreate +
     * answer upserts), so a retry produces the same end state.
     */
    private const SUBMIT_TRANSACTION_ATTEMPTS = 3;

    public function submit(
        ExamSession $examSession,
        string $examStatus,
        string $reason,
        ?string $autoSubmitReasonCode = null,
    ): void {
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

        $update = [
            'status' => 'submitted',
            'end_time' => now(),
            'exam_status' => $examStatus,
            'risk_state' => $examStatus === 'submitted_held' ? 'locked' : $examSession->risk_state,
            'accumulated_pause_seconds' => $accum,
            'pause_segment_started_at' => null,
            'submitted_late' => $submittedLate,
            'updated_at' => now(),
        ];

        if ($autoSubmitReasonCode !== null && $autoSubmitReasonCode !== '') {
            $update['auto_submit_reason_code'] = $autoSubmitReasonCode;
        }

        // Red-team Phase 4 finding M3: wrap the atomic CAS + the entire
        // post-submit pipeline (answer synthesis, evaluation, result
        // finalisation, assignment activity log) in a single DB
        // transaction. Two guarantees follow:
        //
        //   1. Either the session is fully submitted AND has a Result
        //      row AND every answer has been evaluated, or none of those
        //      side-effects are visible. A connection drop between the
        //      status flip and the result insert no longer leaves a
        //      session "submitted" with no result.
        //
        //   2. Concurrent submit() calls coexist safely: the first
        //      transaction that flips status wins via CAS; the second
        //      sees affected_rows = 0 and rolls back its own no-op
        //      transaction. ResultFinalizationService::syncAfterSubmission
        //      is idempotent (Result::updateOrCreate on user_id+quiz_id),
        //      so a deadlock-driven retry produces the same end state.
        //
        // Side-effects that MUST live outside the transaction (because
        // they cannot be rolled back) are captured here and replayed
        // after the transaction commits.
        $committed = false;

        DB::transaction(function () use (
            $examSession,
            $update,
            $exam,
            $reason,
            $submittedLate,
            &$committed,
        ): void {
            $affected = DB::table('exam_sessions')
                ->where('id', $examSession->id)
                ->where('status', '!=', 'submitted')
                ->update($update);

            if ($affected === 0) {
                return;
            }

            foreach ($update as $col => $val) {
                $examSession->{$col} = $val;
            }
            $examSession->syncOriginal();

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

            $committed = true;
        }, self::SUBMIT_TRANSACTION_ATTEMPTS);

        // Side-effects outside the DB transaction. Only fire when this
        // call was the one that actually flipped the row to 'submitted'
        // — otherwise we'd double-decrement the active-sessions counter
        // on every concurrent caller.
        if ($committed) {
            $this->examRuntime->decrementActiveSessions($examId);
        }
    }
}
