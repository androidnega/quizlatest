<?php

namespace App\Console\Commands;

use App\Models\ExamSession;
use App\Services\ExamSessionSubmissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 (Sprint 2 — defensive): auto-submit sessions that have been
 * paused for longer than `exam.max_pause_minutes`.
 *
 * The PauseStaleExamSessionsCommand already moves a silent active
 * session to "paused" after `disconnect_pause_threshold_seconds`. That
 * by itself is not enough — a student could leave a paused window open
 * indefinitely and resume hours/days later, distorting every "writing
 * time" metric and blocking late-submit policy. After
 * `exam.max_pause_minutes` of continuous pause, the session is force-
 * submitted via {@see ExamSessionSubmissionService::submit()} with
 * exam_status=submitted_held and reason=stale_paused so the result
 * lands in the held queue for examiner review.
 */
class AutoSubmitStalePausedSessionsCommand extends Command
{
    protected $signature = 'exam:auto-submit-stale-paused';

    protected $description = 'Force-submit exam sessions that have been paused longer than exam.max_pause_minutes.';

    public function __construct(
        private readonly ExamSessionSubmissionService $sessionSubmission,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $maxMinutes = (int) config('exam.max_pause_minutes', 10);
        if ($maxMinutes <= 0) {
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes($maxMinutes);

        $ids = ExamSession::query()
            ->where('status', 'paused')
            ->whereNotNull('pause_segment_started_at')
            ->where('pause_segment_started_at', '<', $cutoff)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return self::SUCCESS;
        }

        $submitted = 0;
        foreach ($ids as $id) {
            $row = ExamSession::query()->whereKey($id)->first();
            if ($row === null || $row->status !== 'paused') {
                continue;
            }

            // Defensive: re-check the pause window inside the loop in
            // case a parallel resume fired after pluck() but before the
            // submit transaction starts. ExamSessionSubmissionService
            // uses an atomic CAS so a racing manual submit will win.
            if ($row->pause_segment_started_at === null
                || $row->pause_segment_started_at->greaterThanOrEqualTo($cutoff)
            ) {
                continue;
            }

            $this->sessionSubmission->submit(
                $row,
                'submitted_held',
                'stale_paused',
                'stale_paused',
            );

            $submitted++;
        }

        if ($submitted > 0) {
            $this->info("Auto-submitted {$submitted} stale paused session(s).");
        }

        return self::SUCCESS;
    }
}
