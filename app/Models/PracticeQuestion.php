<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeQuestion extends Model
{
    protected $fillable = [
        'practice_quiz_id',
        'type',
        'question_text',
        'options',
        'correct_answer',
        'explanation',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answer' => 'array',
        ];
    }

    public function practiceQuiz(): BelongsTo
    {
        return $this->belongsTo(PracticeQuiz::class);
    }
}
