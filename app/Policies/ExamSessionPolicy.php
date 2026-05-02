<?php

namespace App\Policies;

use App\Models\ExamSession;
use App\Models\User;

class ExamSessionPolicy
{
    public function view(User $user, ExamSession $examSession): bool
    {
        $examSession->loadMissing('exam');

        if ($examSession->exam === null) {
            return false;
        }

        return $user->can('view', $examSession->exam);
    }
}
