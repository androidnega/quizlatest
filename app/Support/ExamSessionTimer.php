<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\Quiz;
use Illuminate\Support\Carbon;

/**
 * Pause-aware elapsed time for timed exams (disconnect / resume).
 */
final class ExamSessionTimer
{
    /**
     * Seconds counting toward the exam timer (excludes completed + in-flight pause wall time).
     */
    public static function activeWritingSeconds(ExamSession $session, Carbon $now): int
    {
        $start = $session->start_time;
        if ($start === null) {
            return 0;
        }

        $wall = max(0, $start->diffInSeconds($now));
        $accum = (int) ($session->accumulated_pause_seconds ?? 0);
        $ongoingPause = 0;
        if ($session->status === 'paused' && $session->pause_segment_started_at !== null) {
            $ongoingPause = max(0, $session->pause_segment_started_at->diffInSeconds($now));
        }

        return max(0, $wall - $accum - $ongoingPause);
    }

    public static function timeRemainingSeconds(ExamSession $session, Quiz $exam, Carbon $now): int
    {
        $durationMinutes = (int) ($exam->duration_minutes ?? 0);
        if ($durationMinutes <= 0 || $session->status === 'submitted') {
            return 0;
        }

        $budget = $durationMinutes * 60;
        $used = self::activeWritingSeconds($session, $now);

        return max(0, $budget - $used);
    }

    public static function examEndAtIso(ExamSession $session, Quiz $exam, Carbon $now): ?string
    {
        $remaining = self::timeRemainingSeconds($session, $exam, $now);
        if ($remaining <= 0 && (int) ($exam->duration_minutes ?? 0) <= 0) {
            return null;
        }
        if ((int) ($exam->duration_minutes ?? 0) <= 0) {
            return null;
        }

        return $now->copy()->addSeconds($remaining)->toAtomString();
    }

    public static function timerPaused(ExamSession $session): bool
    {
        return $session->status === 'paused';
    }
}
