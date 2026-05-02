<?php

namespace App\Policies;

use App\Models\ExamSession;
use App\Models\User;

class ExamSessionPolicy
{
    public function view(User $user, ExamSession $examSession): bool
    {
        if ($user->role !== 'coordinator') {
            return false;
        }

        $examSession->loadMissing('exam');

        if ($examSession->exam === null) {
            return false;
        }

        return $user->can('manageResults', $examSession->exam);
    }
}
