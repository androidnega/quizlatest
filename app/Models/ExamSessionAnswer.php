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
        'saved_at',
    ];

    protected $casts = [
        'answer_payload' => 'array',
        'saved_at' => 'datetime',
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
