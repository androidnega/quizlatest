<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSessionQuestion extends Model
{
    protected $fillable = [
        'exam_session_id',
        'question_id',
        'display_order',
        'option_order',
    ];

    protected $casts = [
        'option_order' => 'array',
    ];

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * option_order[display_index] = original option index (for MCQ shuffling).
     *
     * @return list<int>|null
     */
    public function mcqDisplayToOriginal(): ?array
    {
        $order = $this->option_order;
        if (! is_array($order) || $order === []) {
            return null;
        }

        $out = [];
        foreach ($order as $v) {
            if (! is_int($v) && ! (is_string($v) && ctype_digit((string) $v))) {
                return null;
            }
            $out[] = (int) $v;
        }

        return $out;
    }
}
