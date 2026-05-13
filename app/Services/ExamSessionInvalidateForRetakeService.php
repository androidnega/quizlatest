<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\Result;
use Illuminate\Support\Facades\DB;

/**
 * Removes all exam attempts for a student on a quiz so they may start again.
 * Deletes sessions (and cascaded answers), results, and proctoring events for that pair.
 */
final class ExamSessionInvalidateForRetakeService
{
    public function invalidate(int $studentId, int $examId): void
    {
        DB::transaction(function () use ($studentId, $examId): void {
            ProctoringEvent::query()
                ->where('user_id', $studentId)
                ->where('quiz_id', $examId)
                ->delete();

            Result::query()
                ->where('user_id', $studentId)
                ->where('quiz_id', $examId)
                ->delete();

            ExamSession::query()
                ->where('student_id', $studentId)
                ->where('exam_id', $examId)
                ->delete();
        });
    }
}
