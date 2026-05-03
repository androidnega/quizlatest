<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        'published_at',
        'duration_minutes',
        'total_marks',
        'proctoring_settings',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'proctoring_settings' => 'array',
        'published_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
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

    /**
     * When true, student result views may include correct-answer summaries (exam-level setting).
     */
    public function revealsCorrectAnswersForStudentResults(): bool
    {
        return filter_var(
            data_get($this->proctoring_settings, 'show_correct_answers_to_students', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * Students may open the prepare page and start a session only when published and inside the optional window.
     */
    public function isAvailableForStudentToStart(Carbon $at): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->start_time !== null && $at->lt($this->start_time)) {
            return false;
        }

        if ($this->end_time !== null && $at->gt($this->end_time)) {
            return false;
        }

        return true;
    }
}
