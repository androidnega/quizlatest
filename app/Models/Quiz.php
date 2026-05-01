<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = [
        'university_id',
        'course_id',
        'created_by',
        'title',
        'description',
        'assessment_type',
        'status',
        'duration_minutes',
        'total_marks',
        'proctoring_settings',
        'available_from',
        'available_to',
    ];

    protected $casts = [
        'proctoring_settings' => 'array',
        'available_from' => 'datetime',
        'available_to' => 'datetime',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class, 'exam_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function proctoringEvents(): HasMany
    {
        return $this->hasMany(ProctoringEvent::class);
    }
}
