<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\Quiz;
use Illuminate\Support\Carbon;

final class AssignmentDueCountdown
{
    /** Show live countdown this many days before the relevant deadline. */
    public const WINDOW_DAYS = 5;

    /**
     * @return array{ends_at: string, prefix: string}|null
     */
    public static function resolve(Quiz $exam, ?Carbon $now = null, ?ExamSession $session = null): ?array
    {
        if (! $exam->isAssignment() || $exam->due_at === null) {
            return null;
        }

        if ($session !== null && $session->status === 'submitted') {
            return null;
        }

        $now ??= Carbon::now();
        $within = $now->copy()->addDays(self::WINDOW_DAYS);
        $due = $exam->due_at->copy();

        if ($now->lessThanOrEqualTo($due) && $due->lessThanOrEqualTo($within)) {
            return [
                'ends_at' => $due->toIso8601String(),
                'prefix' => __('Due in'),
            ];
        }

        if ($now->greaterThan($due) && $exam->isAvailableForStudentToStart($now)) {
            $hours = (int) data_get($exam->proctoring_settings, 'late_acceptance_hours', 168);
            $hours = max(0, min($hours, 24 * 30));
            $closeAt = $due->copy()->addHours($hours);

            if ($closeAt->lessThanOrEqualTo($within)) {
                return [
                    'ends_at' => $closeAt->toIso8601String(),
                    'prefix' => __('Closes in'),
                ];
            }
        }

        return null;
    }
}
