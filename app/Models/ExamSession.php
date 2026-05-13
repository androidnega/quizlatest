<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'exam_id',
        'session_id',
        'verification_image_path',
        'status',
        'start_time',
        'end_time',
        'violation_count',
        'violation_score',
        'violation_events',
        'last_event_time',
        'risk_state',
        'exam_status',
        'last_seen_at',
        'pause_segment_started_at',
        'accumulated_pause_seconds',
        'submitted_late',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'violation_count' => 'integer',
        'violation_score' => 'integer',
        'violation_events' => 'array',
        'last_event_time' => 'datetime',
        'last_seen_at' => 'datetime',
        'pause_segment_started_at' => 'datetime',
        'accumulated_pause_seconds' => 'integer',
        'submitted_late' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'exam_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamSessionAnswer::class);
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(ExamSessionQuestion::class)->orderBy('display_order');
    }

    public function getRouteKeyName(): string
    {
        return 'session_id';
    }
}
