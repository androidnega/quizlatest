<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicResetSnapshot extends Model
{
    protected $fillable = [
        'department_id',
        'academic_year_id',
        'initiated_by',
        'reset_type',
        'payload',
        'summary',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'summary' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
