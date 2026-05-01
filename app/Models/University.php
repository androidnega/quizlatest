<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
