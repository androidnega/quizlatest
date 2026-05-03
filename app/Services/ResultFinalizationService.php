<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Result;
use App\Models\User;

class ResultFinalizationService
{
    /**
     * Persist {@see Result} after submission: score from summed answer points; status rules applied.
     */
    public function syncAfterSubmission(
        ExamSession $examSession,
        int $timeTakenSeconds,
        string $reviewNote,
    ): void {
        $examSession->load(['answers']);

        $total = $this->sumPoints($examSession);
        $status = $this->resolveStatus($examSession);

        $examSession->loadMissing('exam');

        Result::query()->updateOrCreate(
            [
                'user_id' => $examSession->student_id,
                'quiz_id' => $examSession->exam_id,
            ],
            [
                'score' => round($total, 2),
                'time_taken' => $timeTakenSeconds,
                'status' => $status,
                'exam_status' => $examSession->exam_status,
                'review_note' => $reviewNote,
                'submitted_at' => now(),
                'academic_year_id' => $examSession->exam?->academic_year_id,
                'term_id' => $examSession->exam?->term_id,
            ],
        );
    }

    /**
     * Recalculate score and workflow status from session + answers (held release, manual grading, etc.).
     */
    public function refreshResultFromSessionState(ExamSession $examSession, ?User $gradedBy = null): void
    {
        $examSession->load(['answers', 'exam']);

        $total = $this->sumPoints($examSession);
        $status = $this->resolveStatus($examSession);

        $existing = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->first();

        $payload = [
            'score' => round($total, 2),
            'status' => $status,
            'exam_status' => $examSession->exam_status,
            'academic_year_id' => $examSession->exam?->academic_year_id,
            'term_id' => $examSession->exam?->term_id,
        ];

        if ($status === 'graded' && $gradedBy !== null) {
            $payload['graded_by'] = $gradedBy->id;
            $payload['graded_at'] = now();
        } elseif ($status === 'graded' && $existing?->graded_at !== null) {
            $payload['graded_at'] = $existing->graded_at;
            $payload['graded_by'] = $existing->graded_by;
        }

        Result::query()->updateOrCreate(
            [
                'user_id' => $examSession->student_id,
                'quiz_id' => $examSession->exam_id,
            ],
            $payload,
        );
    }

    /**
     * After essay grades change; wraps {@see refreshResultFromSessionState()} with grader attribution when applicable.
     */
    public function finalizeAfterManualGrading(ExamSession $examSession, ?User $gradedBy = null): void
    {
        $examSession->refresh();
        $this->refreshResultFromSessionState($examSession->fresh(['answers']), $gradedBy);
    }

    /**
     * Strict precedence:
     * 1. Any answer pending_manual → pending_manual
     * 2. Session exam_status held-like → held
     * 3. graded
     */
    public function resolveStatus(ExamSession $examSession): string
    {
        $examSession->loadMissing('answers');

        $pendingManual = $examSession->answers->contains(
            fn (ExamSessionAnswer $a) => $a->evaluation_status === 'pending_manual',
        );

        if ($pendingManual) {
            return 'pending_manual';
        }

        if (in_array((string) $examSession->exam_status, [
            'submitted_held',
            'locked_by_admin',
        ], true)) {
            return 'held';
        }

        return 'graded';
    }

    private function sumPoints(ExamSession $examSession): float
    {
        return (float) ExamSessionAnswer::query()
            ->where('exam_session_id', $examSession->id)
            ->sum('points_awarded');
    }
}
