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
        'status',
        'start_time',
        'end_time',
        'violation_count',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'violation_count' => 'integer',
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
}
