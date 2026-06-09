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
        'writing_started_at',
        'end_time',
        'violation_count',
        'violation_score',
        'violation_events',
        'last_event_time',
        'last_violation_at',
        'risk_state',
        'exam_status',
        'last_seen_at',
        'pause_segment_started_at',
        'accumulated_pause_seconds',
        'submitted_late',
        'tab_switch_count',
        'auto_submit_reason_code',
        'proctoring_blur_active',
        'proctoring_blur_reason',
        'face_covered_strike_count',
        'extra_seconds',
        'examiner_unlocked_at',
        'examiner_unlocked_by',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'writing_started_at' => 'datetime',
        'end_time' => 'datetime',
        'violation_count' => 'integer',
        'violation_score' => 'integer',
        'violation_events' => 'array',
        'last_event_time' => 'datetime',
        'last_violation_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'pause_segment_started_at' => 'datetime',
        'accumulated_pause_seconds' => 'integer',
        'submitted_late' => 'boolean',
        'tab_switch_count' => 'integer',
        'proctoring_blur_active' => 'boolean',
        'face_covered_strike_count' => 'integer',
        'extra_seconds' => 'integer',
        'examiner_unlocked_at' => 'datetime',
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

    public function assignmentSubmissionFiles(): HasMany
    {
        return $this->hasMany(AssignmentSubmissionFile::class);
    }

    /** @deprecated Use assignmentSubmissionFiles() */
    public function assignmentFiles(): HasMany
    {
        return $this->assignmentSubmissionFiles();
    }

    public function getRouteKeyName(): string
    {
        // Numeric primary key in URLs. UUID session_id is still accepted by
        // resolveRouteBinding() below so legacy bookmarks keep resolving.
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        if ($field === 'id') {
            if (is_numeric($value)) {
                return $this->newQuery()->whereKey((int) $value)->first();
            }

            // Legacy UUID share-token in routes — fall back to session_id.
            return $this->newQuery()->where('session_id', $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
