<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Result;
use App\Models\User;

class ResultFinalizationService
{
    /**
     * Sync {@see Result} row after submission autograding using session outcome rules.
     *
     * Priority: pending manual essays → violation held → graded.
     */
    public function syncAfterSubmission(
        ExamSession $examSession,
        float $score,
        int $timeTakenSeconds,
        string $submitExamStatus,
        string $reviewNote,
    ): void {
        $examSession->loadMissing('answers');

        $status = $this->resolveStatus($examSession, $submitExamStatus);

        Result::query()->updateOrCreate(
            [
                'user_id' => $examSession->student_id,
                'quiz_id' => $examSession->exam_id,
            ],
            [
                'score' => $score,
                'time_taken' => $timeTakenSeconds,
                'status' => $status,
                'exam_status' => $submitExamStatus,
                'review_note' => $reviewNote,
                'submitted_at' => now(),
            ],
        );
    }

    /**
     * Recalculate score and status after manual essay grading (or batch completion).
     */
    public function finalizeAfterManualGrading(ExamSession $examSession, ?User $gradedBy = null): void
    {
        $examSession->refresh();
        $examSession->load(['answers']);

        $total = (float) ExamSessionAnswer::query()
            ->where('exam_session_id', $examSession->id)
            ->sum('points_awarded');

        $result = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->first();

        if ($result === null) {
            return;
        }

        $status = $this->resolveStatus($examSession, (string) $examSession->exam_status);

        $result->update([
            'score' => round($total, 2),
            'status' => $status,
            'graded_at' => $status === 'graded' ? now() : $result->graded_at,
            'graded_by' => $status === 'graded' && $gradedBy ? $gradedBy->id : $result->graded_by,
        ]);
    }

    /**
     * @param  string  $submitExamStatus  {@see ExamSession::exam_status} at submission time
     */
    public function resolveStatus(ExamSession $examSession, string $submitExamStatus): string
    {
        $examSession->loadMissing('answers');

        $pendingManual = $examSession->answers->contains(
            fn (ExamSessionAnswer $a) => $a->evaluation_status === 'pending_manual',
        );

        if ($pendingManual) {
            return 'pending_manual';
        }

        $violationHeld = in_array($submitExamStatus, [
            'submitted_held',
            'locked_by_admin',
        ], true);

        if ($violationHeld) {
            return 'held';
        }

        return 'graded';
    }
}
