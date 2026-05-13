<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseMaterial extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const KIND_SUPPLEMENTARY = 'supplementary';

    public const KIND_COURSE_OUTLINE = 'course_outline';

    protected $fillable = [
        'course_id',
        'class_id',
        'uploaded_by',
        'title',
        'material_kind',
        'file_path',
        'file_type',
        'extracted_text_path',
        'status',
        'extraction_error',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function practiceQuizzes(): HasMany
    {
        return $this->hasMany(PracticeQuiz::class, 'course_material_id');
    }

    /**
     * Materials visible to a student (same course via class enrollment; optional class scope).
     *
     * @param  Builder<CourseMaterial>  $query
     */
    public function scopeVisibleToStudent($query, User $student): void
    {
        if ($student->class_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', self::STATUS_READY)
            ->whereHas('course.classCourses', function ($q) use ($student) {
                $q->where('class_id', $student->class_id);
            })
            ->where(function ($q) use ($student) {
                $q->whereNull('class_id')
                    ->orWhere('class_id', $student->class_id);
            });
    }
}
