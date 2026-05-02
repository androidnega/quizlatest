<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSessionAnswer extends Model
{
    protected $fillable = [
        'exam_session_id',
        'question_id',
        'answer_text',
        'answer_payload',
        'points_awarded',
        'evaluation_status',
        'evaluation_detail',
        'grader_feedback',
        'saved_at',
        'client_revision',
    ];

    protected $hidden = [
        'evaluation_detail',
    ];

    protected $casts = [
        'answer_payload' => 'array',
        'evaluation_detail' => 'array',
        'saved_at' => 'datetime',
        'client_revision' => 'integer',
    ];

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
