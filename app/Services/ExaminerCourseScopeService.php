<?php

namespace App\Services;

use App\Models\ExaminerCourseAssignment;
use App\Models\User;

class ExaminerCourseScopeService
{
    /**
     * @return list<int>
     */
    public function manageableCourseIds(User $user): array
    {
        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $user->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canManageCourse(User $user, int $courseId): bool
    {
        return in_array($courseId, $this->manageableCourseIds($user), true);
    }

}
