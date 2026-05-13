<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admin-only system settings.
     */
    public function manageSystemSettings(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Admin-only coordinator account CRUD (directory routes).
     */
    public function manageCoordinatorDirectory(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Coordinator student directory (list, upload, bulk actions).
     */
    public function viewStudentDirectory(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    /**
     * Coordinator may manage students in their assigned departments.
     */
    public function manageStudentInScope(User $coordinator, User $student): bool
    {
        if ($coordinator->role !== 'coordinator' || $student->role !== 'student') {
            return false;
        }

        $student->loadMissing('program');
        $departmentId = (int) ($student->program?->department_id ?? 0);
        if ($departmentId <= 0) {
            return false;
        }

        return $coordinator->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->contains($departmentId);
    }

    /**
     * Admin-only maintenance of coordinator accounts.
     */
    public function manageCoordinatorAccount(User $admin, User $coordinator): bool
    {
        return $admin->role === 'admin' && $coordinator->role === 'coordinator';
    }

    /**
     * Super admin: browse staff accounts (admins, coordinators, examiners). Single rule: `is_super_admin` on the user.
     */
    public function manageGlobalUserAccounts(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Super admin: view or update any staff account (not students).
     */
    public function manageUserAsAdmin(User $admin, User $target): bool
    {
        return $admin->isSuperAdmin() && $target->role !== 'student';
    }
}
