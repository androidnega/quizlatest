<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\User;

/**
 * Rules for which in-progress sessions block starting another assessment.
 *
 * Assignments may stay open while a student starts a timed quiz; only one timed
 * quiz/exam may be active at a time. Expired timed sessions are auto-submitted on check.
 */
final class StudentExamSessionGateService
{
    public function __construct(
        private readonly ExamSessionSubmissionService $submission,
    ) {}

    public function reconcileExpiredTimedSessions(User $student): void
    {
        $sessions = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->with('exam')
            ->get();

        foreach ($sessions as $session) {
            $this->submission->autoExpireIfTimedOut($session);
        }
    }

    /**
     * Active timed (non-assignment) session, if any — for pause banners and similar UI.
     */
    public function timedActiveSession(User $student): ?ExamSession
    {
        $this->reconcileExpiredTimedSessions($student);

        return ExamSession::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->whereHas('exam', fn ($q) => $q->where('assessment_type', '!=', 'assignment'))
            ->with(['exam.course:id,code,title'])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Session that blocks starting the target assessment, or null if entry is allowed.
     *
     * When non-null and same exam_id, caller should redirect to continue that session.
     */
    public function blockingSessionFor(User $student, Quiz $targetExam): ?ExamSession
    {
        $this->reconcileExpiredTimedSessions($student);

        $activeSessions = ExamSession::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'paused'])
            ->with('exam')
            ->orderByDesc('id')
            ->get();

        foreach ($activeSessions as $active) {
            if ((int) $active->exam_id === (int) $targetExam->id) {
                return $active;
            }

            if ($active->exam?->isAssignment()) {
                continue;
            }

            if ($targetExam->isAssignment()) {
                continue;
            }

            return $active;
        }

        return null;
    }

    public function assertCanStart(User $student, Quiz $targetExam): void
    {
        $blocking = $this->blockingSessionFor($student, $targetExam);

        if ($blocking === null) {
            return;
        }

        if ((int) $blocking->exam_id === (int) $targetExam->id) {
            return;
        }

        abort(422, __('You already have a timed assessment in progress. Finish or submit it before starting another.'));
    }
}
