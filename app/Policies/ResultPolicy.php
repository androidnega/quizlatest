<?php

namespace App\Policies;

use App\Models\Result;
use App\Models\User;

class ResultPolicy
{
    public function view(User $user, Result $result): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'student' && (int) $result->user_id === (int) $user->id) {
            return true;
        }

        if ($user->role === 'examiner') {
            $result->loadMissing('quiz');

            return (int) ($result->quiz?->created_by ?? 0) === (int) $user->id;
        }

        if ($user->role !== 'coordinator') {
            return false;
        }

        $result->loadMissing('quiz.course');
        $course = $result->quiz?->course;
        if ($course === null) {
            return false;
        }

        $deptIds = $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array((int) $course->department_id, $deptIds, true);
    }

    public function update(User $user, Result $result): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'examiner') {
            return false;
        }

        $result->loadMissing('quiz');

        return (int) ($result->quiz?->created_by ?? 0) === (int) $user->id;
    }
}
