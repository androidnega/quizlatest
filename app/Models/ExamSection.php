<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSection extends Model
{
    protected $table = 'exam_sections';

    protected $fillable = [
        'exam_id',
        'title',
        'section_order',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'exam_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'section_id');
    }
}
