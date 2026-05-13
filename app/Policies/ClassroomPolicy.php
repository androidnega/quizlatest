<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\ExaminerCourseAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->role === 'examiner') {
            return $this->examinerMayViewClassForTeaching($user, $classroom);
        }

        return $this->ownsProgramDepartment($user, $classroom);
    }

    public function create(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $this->ownsProgramDepartment($user, $classroom);
    }

    public function assignCourses(User $user, Classroom $classroom): bool
    {
        return $this->ownsProgramDepartment($user, $classroom);
    }

    /**
     * Examiners may add a student to a class roster (same scope as teaching class view), not remove.
     */
    public function addStudent(User $user, Classroom $classroom): bool
    {
        if ($user->role !== 'examiner') {
            return false;
        }

        return $this->examinerMayViewClassForTeaching($user, $classroom);
    }

    /**
     * Examiners may open a class in read-only teaching context when (and only when) they are assigned
     * to at least one course linked to that class. This does not grant coordinator-style management.
     */
    private function examinerMayViewClassForTeaching(User $examiner, Classroom $classroom): bool
    {
        if ((int) $examiner->university_id !== (int) $classroom->university_id) {
            return false;
        }

        $courseIds = ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $examiner->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($courseIds === []) {
            return false;
        }

        return DB::table('class_course')
            ->where('class_id', $classroom->id)
            ->whereIn('course_id', $courseIds)
            ->exists();
    }

    private function ownsProgramDepartment(User $user, Classroom $classroom): bool
    {
        if ($user->role !== 'coordinator') {
            return false;
        }

        $classroom->loadMissing('program');
        $departmentId = (int) ($classroom->program?->department_id ?? 0);

        return $departmentId > 0 && $this->departmentIds($user)->contains($departmentId);
    }

    /** @return Collection<int, int> */
    private function departmentIds(User $user): Collection
    {
        return $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id);
    }
}
