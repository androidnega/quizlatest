<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'university_id',
        'department_id',
        'code',
        'title',
        'credit_hours',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'class_course', 'course_id', 'class_id')->withTimestamps();
    }

    public function classCourses(): HasMany
    {
        return $this->hasMany(ClassCourse::class);
    }

    public function examinerCourseAssignments(): HasMany
    {
        return $this->hasMany(ExaminerCourseAssignment::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function courseMaterials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }
}
