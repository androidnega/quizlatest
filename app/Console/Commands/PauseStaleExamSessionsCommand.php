<?php

namespace App\Console\Commands;

use App\Models\ExamSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PauseStaleExamSessionsCommand extends Command
{
    protected $signature = 'exam:pause-stale-sessions';

    protected $description = 'Pause timed exam sessions when the student has gone offline (no recent activity).';

    public function handle(): int
    {
        $threshold = max(30, (int) config('exam.disconnect_pause_threshold_seconds', 120));
        $cutoff = now()->subSeconds($threshold);

        $ids = ExamSession::query()
            ->where('status', 'active')
            ->whereHas('exam', fn ($q) => $q->where('duration_minutes', '>', 0))
            ->whereRaw('COALESCE(last_seen_at, start_time) < ?', [$cutoff])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($ids as $id) {
            $updated = DB::transaction(function () use ($id, $cutoff): int {
                $row = ExamSession::query()->whereKey($id)->lockForUpdate()->first();
                if ($row === null || $row->status !== 'active') {
                    return 0;
                }

                $row->loadMissing('exam');
                if ((int) ($row->exam?->duration_minutes ?? 0) <= 0) {
                    return 0;
                }

                $lastSignal = $row->last_seen_at ?? $row->start_time;
                if ($lastSignal === null || $lastSignal->greaterThanOrEqualTo($cutoff)) {
                    return 0;
                }

                $row->forceFill([
                    'status' => 'paused',
                    'pause_segment_started_at' => $lastSignal,
                ])->save();

                return 1;
            });
            $count += $updated;
        }

        if ($count > 0) {
            $this->info("Paused {$count} stale exam session(s).");
        }

        return self::SUCCESS;
    }
}
