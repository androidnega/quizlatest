<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function view(User $user, Classroom $classroom): bool
    {
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
