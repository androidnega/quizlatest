<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeAnswer extends Model
{
    protected $fillable = [
        'practice_attempt_id',
        'practice_question_id',
        'answer_payload',
        'points_awarded',
        'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'answer_payload' => 'array',
            'points_awarded' => 'decimal:2',
            'is_correct' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PracticeAttempt::class, 'practice_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(PracticeQuestion::class, 'practice_question_id');
    }
}
