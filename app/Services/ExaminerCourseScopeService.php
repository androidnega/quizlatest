<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\User;

class ExaminerCourseScopeService
{
    /**
     * @return list<int>
     */
    public function manageableCourseIds(User $user): array
    {
        $fromAssignments = ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fromDepartments = Course::query()
            ->whereIn('department_id', $this->coordinatorDepartmentIds($user))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($fromAssignments, $fromDepartments)));
    }

    public function canManageCourse(User $user, int $courseId): bool
    {
        return in_array($courseId, $this->manageableCourseIds($user), true);
    }

    /**
     * @return list<int>
     */
    public function coordinatorDepartmentIds(User $user): array
    {
        return $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
