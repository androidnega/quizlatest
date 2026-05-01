<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'university_id',
        'program_id',
        'level_id',
        'name',
        'section',
        'academic_year',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'class_course', 'class_id', 'course_id')->withTimestamps();
    }

    public function classCourses(): HasMany
    {
        return $this->hasMany(ClassCourse::class, 'class_id');
    }
}
