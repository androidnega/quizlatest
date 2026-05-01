<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function view(User $user, Program $program): bool
    {
        return $this->ownsDepartment($user, (int) $program->department_id);
    }

    public function create(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function update(User $user, Program $program): bool
    {
        return $this->ownsDepartment($user, (int) $program->department_id);
    }

    public function delete(User $user, Program $program): bool
    {
        return $this->ownsDepartment($user, (int) $program->department_id);
    }

    private function ownsDepartment(User $user, int $departmentId): bool
    {
        if ($user->role !== 'coordinator') {
            return false;
        }

        return $user->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->contains($departmentId);
    }
}
