<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Quiz extends Model
{
    protected $fillable = [
        'university_id',
        'academic_year_id',
        'term_id',
        'course_id',
        'created_by',
        'title',
        'description',
        'assessment_type',
        'selected_question_types',
        'status',
        'published_at',
        'duration_minutes',
        'total_marks',
        'questions_per_student',
        'randomize_questions',
        'randomize_options',
        'proctoring_settings',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'proctoring_settings' => 'array',
        'selected_question_types' => 'array',
        'published_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'randomize_questions' => 'boolean',
        'randomize_options' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Quiz $quiz): void {
            if (! filled($quiz->share_token)) {
                $quiz->share_token = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'share_token';
    }

    public function getRouteKey(): string
    {
        if (filled($this->share_token)) {
            return (string) $this->share_token;
        }

        return (string) $this->getAttribute($this->getKeyName());
    }

    /**
     * @param  mixed  $value
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $value = (string) $value;
        if (preg_match('/^[0-9]+$/', $value)) {
            return static::query()->whereKey((int) $value)->firstOrFail();
        }

        return static::query()->where('share_token', $value)->firstOrFail();
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * When non-empty, only students in these class groups (who are also enrolled in the quiz course) may see and start this quiz.
     * When empty, any student in a class group linked to the quiz course may access it (legacy behaviour).
     */
    public function targetClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'quiz_class', 'quiz_id', 'class_id');
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
