<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /** Default class accent when none is stored (#166534 matches app --qs-primary). */
    public const DEFAULT_ACCENT_COLOR = '#166534';

    protected $table = 'classes';

    protected $fillable = [
        'university_id',
        'program_id',
        'level_id',
        'name',
        'section',
        'academic_year',
        'academic_year_id',
        'is_active',
        'accent_color',
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

    public function academicYearStruct(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    /** @return HasMany<User> */
    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'class_id')->where('role', 'student');
    }

    /** Normalized #RRGGBB accent for UI (validated storage or default). */
    public function accentHex(): string
    {
        $c = $this->accent_color;
        if (is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c)) {
            return '#'.strtoupper(ltrim($c, '#'));
        }

        return self::DEFAULT_ACCENT_COLOR;
    }

    /** sRGB relative luminance in 0–1 (for hover text contrast). */
    public function accentRelativeLuminance(): float
    {
        $hex = ltrim($this->accentHex(), '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $linear = static function (float $c): float {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $r = $linear($r);
        $g = $linear($g);
        $b = $linear($b);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** True when solid accent background should use light (e.g. white) foreground. */
    public function accentUsesLightForeground(): bool
    {
        return $this->accentRelativeLuminance() < 0.5;
    }
}
