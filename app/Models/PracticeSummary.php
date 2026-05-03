<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeSummary extends Model
{
    protected $fillable = [
        'student_id',
        'course_id',
        'course_material_id',
        'title',
        'body',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'course_material_id');
    }
}
