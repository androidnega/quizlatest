<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'academic_year_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public static function activeForAcademicYear(int $academicYearId): ?self
    {
        return self::query()
            ->where('academic_year_id', $academicYearId)
            ->where('is_active', true)
            ->first();
    }

    public function activateExclusive(): void
    {
        self::query()
            ->where('academic_year_id', $this->academic_year_id)
            ->whereKeyNot($this->id)
            ->update([
                'is_active' => false,
                'status' => self::STATUS_CLOSED,
            ]);

        $this->forceFill([
            'is_active' => true,
            'status' => self::STATUS_ACTIVE,
        ])->save();
    }
}
