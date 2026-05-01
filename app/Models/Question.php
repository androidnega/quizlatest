<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'quiz_id',
        'quiz_section_id',
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
        'correct_answer' => 'array',
        'answer_schema' => 'array',
        'metadata' => 'array',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuizSection::class, 'quiz_section_id');
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
