<?php

namespace App\Policies;

use App\Models\ExamSession;
use App\Models\Result;
use App\Models\User;

class ExamSessionPolicy
{
    public function view(User $user, ExamSession $examSession): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        $examSession->loadMissing('exam');

        if ($examSession->exam === null) {
            return false;
        }

        return $user->can('manageResults', $examSession->exam);
    }

    /**
     * Student portal: view own submitted exam result (HTML/PDF). Coordinators/admins must not use this gate.
     */
    public function viewStudentResult(User $user, ExamSession $examSession): bool
    {
        if ($user->role !== 'student') {
            return false;
        }

        return (int) $examSession->student_id === (int) $user->id
            && $examSession->status === 'submitted';
    }

    /**
     * PDF export only when the underlying result row is fully graded (not held / pending manual).
     */
    public function downloadStudentResultPdf(User $user, ExamSession $examSession): bool
    {
        if (! $this->viewStudentResult($user, $examSession)) {
            return false;
        }

        $status = Result::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $examSession->exam_id)
            ->value('status');

        return $status === 'graded';
    }
}
