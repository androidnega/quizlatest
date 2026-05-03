<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentCourseAccessService
{
    /**
     * Course IDs linked to the student's class via class_course.
     *
     * @return Collection<int, int>
     */
    public function enrolledCourseIds(User $student): Collection
    {
        if ($student->class_id === null) {
            return collect();
        }

        return DB::table('class_course')
            ->where('class_id', $student->class_id)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id);
    }

    public function canAccessCourse(User $student, int $courseId): bool
    {
        return $this->enrolledCourseIds($student)->contains($courseId);
    }
}
