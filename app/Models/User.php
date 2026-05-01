<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'university_id',
        'program_id',
        'level_id',
        'class_id',
        'name',
        'email',
        'index_number',
        'phone',
        'role',
        'is_active',
        'face_embedding',
        'face_image_path',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'face_embedding' => 'array',
            'password' => 'hashed',
        ];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function createdQuizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function gradedResults(): HasMany
    {
        return $this->hasMany(Result::class, 'graded_by');
    }

    public function coordinatorAssignments(): HasMany
    {
        return $this->hasMany(CoordinatorAssignment::class);
    }

    public function examinerCourseAssignments(): HasMany
    {
        return $this->hasMany(ExaminerCourseAssignment::class, 'examiner_user_id');
    }

    public function assignedClassCourses(): HasMany
    {
        return $this->hasMany(ClassCourse::class, 'assigned_by');
    }

    public function assignedExaminerCourses(): HasMany
    {
        return $this->hasMany(ExaminerCourseAssignment::class, 'assigned_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function proctoringEvents(): HasMany
    {
        return $this->hasMany(ProctoringEvent::class);
    }

    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class, 'student_id');
    }
}
