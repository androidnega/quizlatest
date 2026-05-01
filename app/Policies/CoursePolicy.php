<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function view(User $user, Course $course): bool
    {
        return $this->canAccessCourseForExamWork($user, $course);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->canAccessCourseForExamWork($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->ownsDepartmentCourse($user, $course);
    }

    /**
     * Coordinators (department), examiners (assignment), and admins may act on a course for exams.
     */
    private function canAccessCourseForExamWork(User $user, Course $course): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if (ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->exists()) {
            return true;
        }

        return $this->ownsDepartmentCourse($user, $course);
    }

    private function ownsDepartmentCourse(User $user, Course $course): bool
    {
        if ($user->role !== 'coordinator') {
            return false;
        }

        $ids = $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return in_array((int) $course->department_id, $ids, true);
    }
}
