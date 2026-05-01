<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminerCourseAssignment extends Model
{
    protected $fillable = [
        'course_id',
        'examiner_user_id',
        'assigned_by',
        'is_active',
        'permissions',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'permissions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function examinerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examiner_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
