<?php

namespace App\Policies;

use App\Models\Level;
use App\Models\User;

class LevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'coordinator';
    }

    public function view(User $user, Level $level): bool
    {
        return $this->update($user, $level);
    }

    public function update(User $user, Level $level): bool
    {
        return $user->role === 'coordinator'
            && (int) $level->university_id === (int) $user->university_id;
    }
}
