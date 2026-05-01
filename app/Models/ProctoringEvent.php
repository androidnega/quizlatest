<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProctoringEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'quiz_id',
        'event_type',
        'severity',
        'flagged',
        'action_taken',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'flagged' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
