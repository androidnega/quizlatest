<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'quiz_id',
        'section_id',
        'question_text',
        'type',
        'options',
        'correct_answer',
        'answer_schema',
        'marks',
        'question_order',
        'metadata',
    ];

    protected $casts = [
        'options' => 'array',
        'answer_schema' => 'array',
        'metadata' => 'array',
    ];

    /**
     * @return Attribute<mixed, mixed>
     */
    protected function correctAnswer(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): mixed {
                if ($value === null || $value === '') {
                    return null;
                }

                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            },
            set: fn (mixed $value): ?string => $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR),
        );
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class, 'section_id');
    }

    public function isMCQ(): bool
    {
        return $this->type === 'mcq';
    }

    public function isTrueFalse(): bool
    {
        return $this->type === 'true_false';
    }

    public function isFillBlank(): bool
    {
        return $this->type === 'fill_blank';
    }

    public function isEssay(): bool
    {
        return $this->type === 'essay';
    }
}
