<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function view(User $user, Course $course): bool
    {
        return $this->ownsDepartmentCourse($user, $course);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->ownsDepartmentCourse($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
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
