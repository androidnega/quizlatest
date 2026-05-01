<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSection extends Model
{
    protected $fillable = [
        'quiz_id',
        'title',
        'description',
        'section_order',
        'question_limit',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'quiz_section_id');
    }
}
