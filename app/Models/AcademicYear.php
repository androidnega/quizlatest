<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'university_id',
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

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public static function activeForUniversity(int $universityId): ?self
    {
        return self::query()
            ->where('university_id', $universityId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create a default September–August academic year for a new university.
     */
    public static function bootstrapDefaultForUniversity(int $universityId): self
    {
        $startYear = (int) now()->year;
        $start = now()->setDate($startYear, 9, 1)->startOfDay();
        $end = now()->setDate($startYear + 1, 8, 31)->endOfDay();
        $name = $startYear.'/'.($startYear + 1);

        $year = self::query()->create([
            'university_id' => $universityId,
            'name' => $name,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => self::STATUS_CLOSED,
            'is_active' => false,
        ]);

        $term = Term::query()->create([
            'academic_year_id' => $year->id,
            'name' => 'Full year',
            'start_date' => $year->start_date,
            'end_date' => $year->end_date,
            'status' => Term::STATUS_UPCOMING,
            'is_active' => false,
        ]);

        $year->activateExclusive();
        $term->activateExclusive();

        return $year->fresh(['terms']);
    }

    /**
     * Ensure exactly one active row per university when this year is activated.
     */
    public function activateExclusive(): void
    {
        self::query()
            ->where('university_id', $this->university_id)
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
