<?php

namespace App\Policies;

use App\Models\ExaminerCourseAssignment;
use App\Models\Quiz;
use App\Models\User;

class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'examiner') {
            return ExaminerCourseAssignment::query()
                ->where('examiner_user_id', $user->id)
                ->where('is_active', true)
                ->exists();
        }

        return $user->role === 'coordinator'
            && $user->coordinatorAssignments()->where('is_active', true)->exists();
    }

    public function view(User $user, Quiz $exam): bool
    {
        return $this->manageExam($user, $exam);
    }

    public function create(User $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'examiner') {
            return false;
        }

        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function update(User $user, Quiz $exam): bool
    {
        return $this->manageExam($user, $exam);
    }

    public function delete(User $user, Quiz $exam): bool
    {
        return $this->manageExam($user, $exam);
    }

    /**
     * Held-result actions, force-submit, session/result review surfaces for an exam.
     */
    public function manageResults(User $user, Quiz $exam): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'examiner') {
            return false;
        }

        return (int) $exam->created_by === (int) $user->id
            && ExaminerCourseAssignment::query()
                ->where('examiner_user_id', $user->id)
                ->where('course_id', $exam->course_id)
                ->where('is_active', true)
                ->exists();
    }

    private function manageExam(User $user, Quiz $exam): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'examiner') {
            return (int) $exam->created_by === (int) $user->id
                && ExaminerCourseAssignment::query()
                    ->where('examiner_user_id', $user->id)
                    ->where('course_id', $exam->course_id)
                    ->where('is_active', true)
                    ->exists();
        }

        return false;
    }
}
