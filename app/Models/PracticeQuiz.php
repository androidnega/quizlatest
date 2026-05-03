<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeQuiz extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'student_id',
        'course_id',
        'class_id',
        'course_material_id',
        'title',
        'quiz_type',
        'difficulty',
        'question_count',
        'status',
        'generated_by_ai',
        'generation_error',
    ];

    protected function casts(): array
    {
        return [
            'generated_by_ai' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'course_material_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PracticeQuestion::class)->orderBy('display_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PracticeAttempt::class);
    }
}
