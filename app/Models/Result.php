<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'score',
        'time_taken',
        'status',
        'graded_by',
        'feedback',
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'feedback' => 'array',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
