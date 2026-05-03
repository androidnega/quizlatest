<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeAttempt extends Model
{
    protected $fillable = [
        'practice_quiz_id',
        'student_id',
        'score',
        'total_marks',
        'percentage',
        'started_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'total_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function practiceQuiz(): BelongsTo
    {
        return $this->belongsTo(PracticeQuiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PracticeAnswer::class);
    }
}
