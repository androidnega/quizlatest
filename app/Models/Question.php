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
        'question_type',
        'options',
        'correct_answer',
        'marks',
        'question_order',
        'metadata',
    ];

    protected $casts = [
        'options' => 'array',
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
}
