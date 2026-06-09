<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Live-Ops Phase 2 — Exam Command Center metrics.
 *
 * One service, one entry point: `metrics(int $universityId)` returns
 * everything the operations dashboard needs in a single payload. Each
 * sub-method is also public so the same data can be exposed through
 * artisan commands (`qs:monitor:snapshot`) or external monitors.
 *
 * Every query is scoped by university_id (which all of QuizSnap's
 * core tables already carry), so a coordinator never sees another
 * institution's traffic — even if they have the route URL.
 *
 * IMPORTANT: queries are intentionally COUNT(*) only — no joins,
 * no DISTINCT-by-large-strings, no JSON path lookups. Each runs in
 * < 5 ms on the indexed columns shipped by 2026_06_06_120000_add_performance_indexes.
 */
class ExamCommandCenterService
{
    public function metrics(int $universityId): array
    {
        return [
            'sessions' => $this->liveSessionCounters($universityId),
            'violations' => $this->violationCounters($universityId),
            'submissions' => $this->submissionCounters($universityId),
            'latency' => $this->latencyHints(),
            'snapshot' => cache()->get('qs:ops:snapshot'),
            'snapshot_history' => cache()->get('qs:ops:snapshot:history', []),
            'captured_at' => now()->toAtomString(),
        ];
    }

    /**
     * @return array{active: int, paused: int, exams_running: int, students_writing: int}
     */
    public function liveSessionCounters(int $universityId): array
    {
        $rows = DB::table('exam_sessions')
            ->join('quizzes', 'quizzes.id', '=', 'exam_sessions.exam_id')
            ->where('quizzes.university_id', $universityId)
            ->whereIn('exam_sessions.status', ['active', 'paused'])
            ->select('exam_sessions.status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('exam_sessions.status')
            ->pluck('cnt', 'status');

        $active = (int) ($rows['active'] ?? 0);
        $paused = (int) ($rows['paused'] ?? 0);

        $examsRunning = DB::table('exam_sessions')
            ->join('quizzes', 'quizzes.id', '=', 'exam_sessions.exam_id')
            ->where('quizzes.university_id', $universityId)
            ->whereIn('exam_sessions.status', ['active', 'paused'])
            ->distinct('exam_sessions.exam_id')
            ->count('exam_sessions.exam_id');

        return [
            'active' => $active,
            'paused' => $paused,
            'exams_running' => (int) $examsRunning,
            'students_writing' => $active + $paused,
        ];
    }

    /**
     * @return array{
     *     with_any_violation: int,
     *     face_missing: int,
     *     phone_detected: int,
     *     tab_switch: int,
     *     overlay_dismissals: int,
     *     critical_risk_now: int,
     * }
     */
    public function violationCounters(int $universityId): array
    {
        $sinceLastHour = now()->subHour();

        $base = DB::table('exam_sessions')
            ->join('quizzes', 'quizzes.id', '=', 'exam_sessions.exam_id')
            ->where('quizzes.university_id', $universityId)
            ->whereIn('exam_sessions.status', ['active', 'paused']);

        $withViolation = (clone $base)
            ->where('exam_sessions.violation_score', '>', 0)
            ->count();

        $criticalRiskNow = (clone $base)
            ->whereIn('exam_sessions.risk_state', ['critical', 'locked'])
            ->count();

        // Drilldown by event_type from the indexed proctoring_events
        // table (architecture review phase 3). The user_id+quiz_id+
        // event_type+created_at composite index makes each of these a
        // covered seek scoped by a single condition.
        $faceMissing = $this->countEventTypeRecent($universityId, 'face_obstruction', $sinceLastHour);
        $phoneDetected = $this->countEventTypeRecent($universityId, 'phone_detected', $sinceLastHour);
        $tabSwitch = $this->countEventTypeRecent($universityId, 'tab_switch', $sinceLastHour);
        $overlayDismissals = $this->countEventTypeRecent($universityId, 'proctoring_overlay_resolved', $sinceLastHour);

        return [
            'with_any_violation' => $withViolation,
            'face_missing' => $faceMissing,
            'phone_detected' => $phoneDetected,
            'tab_switch' => $tabSwitch,
            'overlay_dismissals' => $overlayDismissals,
            'critical_risk_now' => $criticalRiskNow,
        ];
    }

    /**
     * @return array{
     *     submitted_today: int,
     *     held_today: int,
     *     auto_submitted_today: int,
     *     auto_submit_breakdown: array<string, int>,
     *     graded_today: int,
     * }
     */
    public function submissionCounters(int $universityId): array
    {
        $sinceMidnight = now()->startOfDay();

        $base = DB::table('exam_sessions')
            ->join('quizzes', 'quizzes.id', '=', 'exam_sessions.exam_id')
            ->where('quizzes.university_id', $universityId)
            ->where('exam_sessions.updated_at', '>=', $sinceMidnight);

        $submittedToday = (clone $base)->where('exam_sessions.status', 'submitted')->count();
        $heldToday = (clone $base)->where('exam_sessions.exam_status', 'submitted_held')->count();
        $autoSubmittedToday = (clone $base)
            ->where('exam_sessions.status', 'submitted')
            ->whereNotNull('exam_sessions.auto_submit_reason_code')
            ->count();
        $gradedToday = (clone $base)->where('exam_sessions.exam_status', 'graded')->count();

        $autoSubmitBreakdown = (clone $base)
            ->where('exam_sessions.status', 'submitted')
            ->whereNotNull('exam_sessions.auto_submit_reason_code')
            ->select('exam_sessions.auto_submit_reason_code', DB::raw('COUNT(*) as cnt'))
            ->groupBy('exam_sessions.auto_submit_reason_code')
            ->pluck('cnt', 'auto_submit_reason_code')
            ->mapWithKeys(fn ($v, $k) => [(string) $k => (int) $v])
            ->all();

        return [
            'submitted_today' => $submittedToday,
            'held_today' => $heldToday,
            'auto_submitted_today' => $autoSubmittedToday,
            'auto_submit_breakdown' => $autoSubmitBreakdown,
            'graded_today' => $gradedToday,
        ];
    }

    /**
     * Latency hints are pulled from the cached snapshot — qs:monitor:snapshot
     * captures `proctoring_events_per_minute` etc. The dashboard treats
     * these as "system load" not "average request time": shared hosting
     * has no easy way to measure per-request timing without a debug bar.
     *
     * @return array{events_per_minute: int, snapshot_age_seconds: int}
     */
    public function latencyHints(): array
    {
        $snap = cache()->get('qs:ops:snapshot');
        if (! is_array($snap)) {
            return ['events_per_minute' => 0, 'snapshot_age_seconds' => -1];
        }
        $captured = isset($snap['captured_at']) ? strtotime((string) $snap['captured_at']) : 0;
        $age = $captured > 0 ? max(0, time() - $captured) : -1;

        return [
            'events_per_minute' => (int) ($snap['proctoring_events_per_minute'] ?? 0),
            'snapshot_age_seconds' => $age,
        ];
    }

    private function countEventTypeRecent(int $universityId, string $eventType, $since): int
    {
        return (int) DB::table('proctoring_events')
            ->join('quizzes', 'quizzes.id', '=', 'proctoring_events.quiz_id')
            ->where('quizzes.university_id', $universityId)
            ->where('proctoring_events.event_type', $eventType)
            ->where('proctoring_events.created_at', '>=', $since)
            ->count();
    }
}
