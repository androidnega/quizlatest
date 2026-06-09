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
     * Wall-clock anchor for the student writing timer (full duration from first take-page load).
     */
    public static function writingAnchor(ExamSession $session): ?Carbon
    {
        return $session->writing_started_at ?? $session->start_time;
    }

    /**
     * Start the writing timer on first load of the take page (not at prepare / session create).
     */
    public static function ensureWritingTimerStarted(ExamSession $session, Quiz $exam): bool
    {
        if ((int) ($exam->duration_minutes ?? 0) <= 0 || $exam->isAssignment()) {
            return false;
        }

        if ($session->writing_started_at !== null) {
            return false;
        }

        $session->forceFill(['writing_started_at' => now()])->save();

        return true;
    }

    /**
     * Seconds counting toward the exam timer (excludes completed + in-flight pause wall time).
     */
    public static function activeWritingSeconds(ExamSession $session, Carbon $now): int
    {
        $start = self::writingAnchor($session);
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

        // Live-Ops Phase 5: examiner-granted extra time is added to the
        // budget. ExaminerEmergencyController is the only writer of
        // extra_seconds, and every write produces an audit-log entry.
        $budget = ($durationMinutes * 60) + max(0, (int) ($session->extra_seconds ?? 0));
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
