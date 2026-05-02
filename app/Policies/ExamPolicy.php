<?php

namespace App\Policies;

use App\Models\Course;
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

        if ($user->role !== 'coordinator') {
            return false;
        }

        if ($user->coordinatorAssignments()->where('is_active', true)->exists()) {
            return true;
        }

        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function view(User $user, Quiz $exam): bool
    {
        return $this->manageExam($user, $exam);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
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
     * Release / confirm / override held results and force-submit (invigilator tools).
     * Department coordinators without an examiner assignment cannot perform these actions.
     */
    public function reviewHeldResults(User $user, Quiz $exam): bool
    {
        if ($user->role !== 'coordinator') {
            return false;
        }

        $course = Course::query()->find($exam->course_id);
        if ($course === null) {
            return false;
        }

        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->exists();
    }

    private function manageExam(User $user, Quiz $exam): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'coordinator') {
            return false;
        }

        $course = Course::query()->find($exam->course_id);
        if ($course === null) {
            return false;
        }

        if (ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->exists()) {
            return true;
        }

        $deptIds = $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array((int) $course->department_id, $deptIds, true);
    }
}
