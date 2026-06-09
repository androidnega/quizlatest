<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('exam:pause-stale-sessions')->everyMinute()->withoutOverlapping();

// Architecture Review Phase 5: any session that has been paused longer
// than exam.max_pause_minutes is force-submitted (status=submitted_held,
// reason=stale_paused) so it lands in the held queue for review.
Schedule::command('exam:auto-submit-stale-paused')->everyMinute()->withoutOverlapping();

// Live-Ops Phase 6: daily backup at 02:00. Keeps 7 days of artefacts
// in storage/app/backups (download externally for true off-host
// retention — see QUIZSNAP_LIVE_EXAM_OPS_PLAN.txt § 6).
Schedule::command('qs:backup:run', ['--keep-days=7'])
    ->dailyAt('02:00')
    ->withoutOverlapping(120)
    ->name('qs.backup.daily');

// Live-Ops Phase 4: capture an operations snapshot every 5 minutes.
// The dashboard reads from cache so it doesn't have to recompute on
// each render. Threshold breaches are written to the log (or to a
// configured monitoring channel — see config/logging.php).
Schedule::command('qs:monitor:snapshot')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->name('qs.ops.snapshot');

// Audit Phase 11 / Section 9.1.2: shared hosting cannot run a persistent
// queue worker, so we drain the database queue once per minute. The
// command exits as soon as the queue is empty so it consumes ~zero
// resources when there's nothing to do.
Schedule::command('queue:work', [
    '--queue=default',
    '--stop-when-empty',
    '--tries=3',
    '--max-time=50',
    '--memory=128',
])->everyMinute()->withoutOverlapping(60)->runInBackground();

// Audit P1.4 / Section 8.2.4: prune stale .log files (keeps storage from
// filling). The `daily` channel auto-rotates but old per-channel files
// can still accumulate over months.
Schedule::call(function (): void {
    $cutoff = now()->subDays(30)->getTimestamp();
    foreach ((array) File::glob(storage_path('logs/*.log')) as $path) {
        if (is_file($path) && filemtime($path) < $cutoff) {
            @unlink($path);
        }
    }
})->dailyAt('03:15')->name('quizsnap.prune-old-logs');

// Audit Phase 12 / Section 8.2.5: trim ancient proctoring + exam session
// telemetry. Proctoring events are useful for review for ~6 months after
// a result is finalised; older ones are bloat that slows every history
// page query.
Schedule::call(function (): void {
    $cutoff = now()->subMonths(6);
    DB::table('proctoring_events')
        ->where('created_at', '<', $cutoff)
        ->limit(5000)
        ->delete();
})->weeklyOn(0, '03:30')->name('quizsnap.prune-old-proctoring-events');
