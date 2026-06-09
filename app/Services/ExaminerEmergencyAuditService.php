<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ExamSession;
use App\Models\User;

/**
 * Live-Ops Phase 5 — every emergency action an examiner takes against
 * a live exam session (extend time, unlock, force-submit, override
 * decision, invalidate-for-retake) is journaled here. The records are
 * append-only and never updated, so a coordinator / admin can always
 * reconstruct who did what to a stuck attempt — and when.
 *
 * Stored in the existing `activity_logs` table to avoid a separate
 * audit schema. event_type is namespaced with `examiner_emergency.*`
 * so filtering is trivial in the dashboard SQL.
 */
class ExaminerEmergencyAuditService
{
    public const EVENT_EXTEND_TIME = 'examiner_emergency.extend_time';
    public const EVENT_UNLOCK_SESSION = 'examiner_emergency.unlock_session';
    public const EVENT_FORCE_SUBMIT = 'examiner_emergency.force_submit';
    public const EVENT_OVERRIDE_DECISION = 'examiner_emergency.override_decision';
    public const EVENT_INVALIDATE_FOR_RETAKE = 'examiner_emergency.invalidate_for_retake';
    public const EVENT_RELEASE_HELD = 'examiner_emergency.release_held';
    public const EVENT_CONFIRM_FAIL = 'examiner_emergency.confirm_fail';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        User $actor,
        ExamSession $session,
        string $event,
        array $payload = [],
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $actor->id,
            'quiz_id' => (int) $session->exam_id,
            'event_type' => $event,
            'event_data' => array_merge([
                'exam_session_id' => $session->id,
                'session_id' => $session->session_id,
                'student_id' => $session->student_id,
                'actor_role' => $actor->role,
            ], $payload),
            'created_at' => now(),
        ]);
    }
}
