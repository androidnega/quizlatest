<?php

namespace App\Policies;

use App\Models\University;
use App\Models\User;

class UniversityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, University $university): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, University $university): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, University $university): bool
    {
        return $user->role === 'admin';
    }
}
