<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\Quiz;
use App\Services\ExaminerEmergencyAuditService;
use App\Services\ExamSessionSubmissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Live-Ops Phase 5 — examiner-only emergency tools for stuck or
 * compromised exam attempts.
 *
 * Every action here:
 *   1. requires the examiner (or admin) to authorize against the parent
 *      Quiz via the `manageResults` policy ability — same gate the
 *      existing forceSubmit/releaseHeldResult flow already uses;
 *   2. is wrapped in a database transaction;
 *   3. writes a structured audit row through ExaminerEmergencyAuditService
 *      so the action is permanently recorded against the session.
 *
 * These actions never bypass the submission pipeline — they always
 * delegate to ExamSessionSubmissionService::submit(), which means
 * Result rows / answer evaluation / event broadcasting still run.
 */
class ExaminerEmergencyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ExaminerEmergencyAuditService $audit,
        private readonly ExamSessionSubmissionService $sessionSubmission,
    ) {}

    /**
     * Add (or remove) extra time on a live timed session.
     *
     * Body:
     *   minutes: signed integer (-60 … 240). Positive adds time,
     *            negative removes time. The value is clamped so the
     *            cumulative extra_seconds field never drops below zero.
     *   reason:  optional string, audit-only.
     */
    public function extendTime(Request $request, ExamSession $examSession): JsonResponse
    {
        $quiz = $this->authorizeAndLoadQuiz($examSession);

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'between:-60,240'],
            'reason' => ['nullable', 'string', 'max:280'],
        ]);

        $delta = (int) $validated['minutes'] * 60;

        $result = DB::transaction(function () use ($examSession, $delta) {
            $row = ExamSession::query()->whereKey($examSession->id)->lockForUpdate()->first();
            $previous = (int) ($row->extra_seconds ?? 0);
            $next = max(0, $previous + $delta);
            $row->forceFill(['extra_seconds' => $next])->save();

            return ['previous' => $previous, 'next' => $next];
        });

        $this->audit->record(
            $request->user(),
            $examSession,
            ExaminerEmergencyAuditService::EVENT_EXTEND_TIME,
            [
                'minutes_delta' => (int) $validated['minutes'],
                'previous_extra_seconds' => $result['previous'],
                'extra_seconds' => $result['next'],
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return response()->json([
            'status' => 'ok',
            'extra_seconds' => $result['next'],
            'extra_minutes' => (int) round($result['next'] / 60),
        ]);
    }

    /**
     * Unlock (resume) a paused or stuck session so the student can
     * continue. The session's status is forced back to "active" and
     * last_seen_at is bumped to "now" so the disconnect-pause sweep
     * does not re-pause the row in the next minute.
     *
     * If the session was paused (pause_segment_started_at is set),
     * the pause segment is closed and the elapsed time is folded into
     * accumulated_pause_seconds — preserving the student's remaining
     * timer budget.
     */
    public function unlockSession(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeAndLoadQuiz($examSession);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:280'],
        ]);

        $result = DB::transaction(function () use ($examSession, $request) {
            $row = ExamSession::query()->whereKey($examSession->id)->lockForUpdate()->first();

            if ($row->status === 'submitted') {
                abort(422, 'A submitted session cannot be unlocked. Use invalidate-for-retake or override-decision instead.');
            }

            $previousStatus = (string) $row->status;
            $update = [
                'status' => 'active',
                'last_seen_at' => now(),
                'examiner_unlocked_at' => now(),
                'examiner_unlocked_by' => (int) $request->user()->id,
            ];

            // Close any in-flight pause segment so the student does not
            // lose timer budget for the seconds spent paused.
            if ($previousStatus === 'paused' && $row->pause_segment_started_at !== null) {
                $segment = max(0, $row->pause_segment_started_at->diffInSeconds(now()));
                $update['accumulated_pause_seconds'] = (int) ($row->accumulated_pause_seconds ?? 0) + $segment;
                $update['pause_segment_started_at'] = null;
            }

            $row->forceFill($update)->save();

            return ['previous_status' => $previousStatus];
        });

        $this->audit->record(
            $request->user(),
            $examSession,
            ExaminerEmergencyAuditService::EVENT_UNLOCK_SESSION,
            [
                'previous_status' => $result['previous_status'],
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return response()->json(['status' => 'unlocked']);
    }

    /**
     * Examiner emergency override of an exam-status decision after
     * submission (e.g. a session that auto-submitted into the held
     * queue but on review should be released as graded).
     *
     * Body:
     *   exam_status: one of {graded, submitted_held, flagged_for_review,
     *                        submitted_late}.
     *   reason:      required, audit-only.
     */
    public function override(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeAndLoadQuiz($examSession);

        $validated = $request->validate([
            'exam_status' => ['required', 'string', 'in:graded,submitted_held,flagged_for_review,submitted_late'],
            'reason' => ['required', 'string', 'min:8', 'max:280'],
        ]);

        $previousStatus = (string) $examSession->exam_status;

        DB::table('exam_sessions')
            ->where('id', $examSession->id)
            ->update([
                'exam_status' => $validated['exam_status'],
                'updated_at' => now(),
            ]);

        $this->audit->record(
            $request->user(),
            $examSession,
            ExaminerEmergencyAuditService::EVENT_OVERRIDE_DECISION,
            [
                'previous_exam_status' => $previousStatus,
                'new_exam_status' => $validated['exam_status'],
                'reason' => $validated['reason'],
            ],
        );

        return response()->json([
            'status' => 'ok',
            'exam_status' => $validated['exam_status'],
        ]);
    }

    /**
     * Snapshot of the audit trail for one session — used by the
     * coordinator command center and by the examiner detail panel.
     */
    public function auditTrail(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeAndLoadQuiz($examSession);

        $rows = DB::table('activity_logs')
            ->where('quiz_id', $examSession->exam_id)
            ->where('event_type', 'like', 'examiner_emergency.%')
            ->where('event_data', 'like', '%"exam_session_id":'.$examSession->id.'%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'user_id', 'event_type', 'event_data', 'created_at']);

        return response()->json([
            'session_id' => (int) $examSession->id,
            'events' => $rows->map(function ($r) {
                $data = json_decode((string) $r->event_data, true) ?: [];
                return [
                    'id' => (int) $r->id,
                    'event_type' => (string) $r->event_type,
                    'actor_user_id' => (int) $r->user_id,
                    'created_at' => (string) $r->created_at,
                    'payload' => $data,
                ];
            })->all(),
        ]);
    }

    private function authorizeAndLoadQuiz(ExamSession $examSession): Quiz
    {
        $quiz = Quiz::query()->find((int) $examSession->exam_id);
        abort_if($quiz === null, 404);
        $this->authorize('manageResults', $quiz);

        return $quiz;
    }
}
