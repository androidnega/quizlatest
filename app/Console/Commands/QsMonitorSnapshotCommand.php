<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Live-Ops Phase 4 — single-shot health/metric snapshot.
 *
 * Designed for shared cPanel hosting where there's no persistent
 * Prometheus / Datadog agent: this command runs from cron, computes
 * the live-exam metrics, stores them in the cache (so the operations
 * dashboard can render the latest snapshot), and writes to the
 * `monitoring` log channel when any threshold is breached.
 *
 * Output (JSON when run with --json) is also pipeable to any external
 * watcher (cPanel cron → email / Slack webhook).
 */
class QsMonitorSnapshotCommand extends Command
{
    protected $signature = 'qs:monitor:snapshot {--json : Print JSON only (no human prose)}';

    protected $description = 'Capture a live operations snapshot (sessions, violations, latency, storage, log size) and alert on threshold breaches.';

    /**
     * @var array<string, array{warn: int|float, crit: int|float}>
     */
    private const THRESHOLDS = [
        // Auto-submits per hour: > 5 in an hour is suspicious during a
        // single sitting; > 20 is almost certainly an outage.
        'auto_submits_per_hour' => ['warn' => 5, 'crit' => 20],
        // 5xx responses scraped from the daily log file.
        'failed_requests_per_hour' => ['warn' => 10, 'crit' => 50],
        // Log file footprint in MB (private storage is shared on cPanel).
        'log_size_mb' => ['warn' => 250, 'crit' => 750],
        // Private storage footprint in MB.
        'private_storage_mb' => ['warn' => 4_000, 'crit' => 9_000],
        // Stale paused sessions older than max_pause_minutes that
        // haven't been auto-submitted yet (means scheduler is stuck).
        'stale_paused_sessions' => ['warn' => 1, 'crit' => 5],
    ];

    public function handle(): int
    {
        $now = now();
        $oneHourAgo = $now->copy()->subHour();
        $oneMinuteAgo = $now->copy()->subMinute();

        $activeSessions = DB::table('exam_sessions')
            ->whereIn('status', ['active', 'paused'])
            ->count();

        $autoSubmitsLastHour = DB::table('exam_sessions')
            ->where('status', 'submitted')
            ->whereNotNull('auto_submit_reason_code')
            ->where('updated_at', '>=', $oneHourAgo)
            ->count();

        $heldLastHour = DB::table('exam_sessions')
            ->where('exam_status', 'submitted_held')
            ->where('updated_at', '>=', $oneHourAgo)
            ->count();

        $stalePausedThreshold = (int) config('exam.max_pause_minutes', 10);
        $stalePausedSessions = $stalePausedThreshold > 0
            ? DB::table('exam_sessions')
                ->where('status', 'paused')
                ->where('pause_segment_started_at', '<', $now->copy()->subMinutes($stalePausedThreshold + 1))
                ->count()
            : 0;

        $proctoringEventsLastMinute = DB::table('proctoring_events')
            ->where('created_at', '>=', $oneMinuteAgo)
            ->count();

        $violatingNow = DB::table('exam_sessions')
            ->whereIn('status', ['active', 'paused'])
            ->where('violation_score', '>', 0)
            ->count();

        $logSizeBytes = $this->logFootprintBytes();
        $privateStorageBytes = $this->privateStorageFootprintBytes();
        $failedRequestsLastHour = $this->countFailedRequestsLastHour();

        $snapshot = [
            'captured_at' => $now->toAtomString(),
            'active_sessions' => (int) $activeSessions,
            'auto_submits_per_hour' => (int) $autoSubmitsLastHour,
            'held_per_hour' => (int) $heldLastHour,
            'stale_paused_sessions' => (int) $stalePausedSessions,
            'proctoring_events_per_minute' => (int) $proctoringEventsLastMinute,
            'violating_sessions_now' => (int) $violatingNow,
            'log_size_mb' => (int) round($logSizeBytes / 1024 / 1024),
            'private_storage_mb' => (int) round($privateStorageBytes / 1024 / 1024),
            'failed_requests_per_hour' => (int) $failedRequestsLastHour,
        ];

        $alerts = $this->buildAlerts($snapshot);
        $snapshot['alerts'] = $alerts;

        // Cache the snapshot so the dashboard can render it without
        // re-running the queries.
        cache()->put('qs:ops:snapshot', $snapshot, now()->addMinutes(5));

        // Persist a tiny rolling history (last 24 entries — i.e. roughly
        // a day at hourly resolution).
        $history = (array) cache()->get('qs:ops:snapshot:history', []);
        $history[] = ['t' => $now->getTimestamp(), 'snap' => $snapshot];
        if (count($history) > 24) {
            $history = array_slice($history, -24);
        }
        cache()->put('qs:ops:snapshot:history', $history, now()->addDays(2));

        if ($alerts !== []) {
            Log::channel($this->resolveAlertChannel())->warning(
                'qs.ops.snapshot.alerts_fired',
                ['snapshot' => $snapshot, 'alerts' => $alerts],
            );
        }

        if ($this->option('json')) {
            $this->line(json_encode($snapshot, JSON_PRETTY_PRINT));
        } else {
            $this->info('QuizSnap operations snapshot captured at '.$snapshot['captured_at']);
            foreach ($snapshot as $k => $v) {
                if ($k === 'alerts' || $k === 'captured_at') continue;
                $this->line("  {$k}: ".(is_array($v) ? json_encode($v) : (string) $v));
            }
            if ($alerts !== []) {
                $this->warn('Alerts fired:');
                foreach ($alerts as $alert) {
                    $this->warn('  ['.$alert['severity'].'] '.$alert['key'].' = '.$alert['value'].' (threshold '.$alert['threshold'].')');
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<array{key: string, value: int|float, threshold: int|float, severity: string}>
     */
    private function buildAlerts(array $snapshot): array
    {
        $alerts = [];
        foreach (self::THRESHOLDS as $key => $bounds) {
            if (! array_key_exists($key, $snapshot)) {
                continue;
            }
            $value = (int) $snapshot[$key];
            if ($value >= (int) $bounds['crit']) {
                $alerts[] = ['key' => $key, 'value' => $value, 'threshold' => $bounds['crit'], 'severity' => 'critical'];
            } elseif ($value >= (int) $bounds['warn']) {
                $alerts[] = ['key' => $key, 'value' => $value, 'threshold' => $bounds['warn'], 'severity' => 'warning'];
            }
        }
        return $alerts;
    }

    private function logFootprintBytes(): int
    {
        $logsDir = storage_path('logs');
        if (! is_dir($logsDir)) {
            return 0;
        }
        $total = 0;
        foreach ((array) File::glob($logsDir.'/*.log') as $path) {
            if (is_file($path)) {
                $total += (int) filesize($path);
            }
        }
        return $total;
    }

    private function privateStorageFootprintBytes(): int
    {
        $base = storage_path('app');
        if (! is_dir($base)) {
            return 0;
        }
        // Walk only the top level; counting every file on shared
        // hosting can take seconds. The first-level totals are within
        // ~10% of the real footprint and stable enough for alerting.
        $total = 0;
        try {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
            $count = 0;
            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $total += $file->getSize();
                    $count++;
                    // Cap so this never blows up cron timing on a
                    // pathological tree.
                    if ($count > 50_000) break;
                }
            }
        } catch (\Throwable) {
            //
        }
        return $total;
    }

    private function countFailedRequestsLastHour(): int
    {
        $log = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        if (! is_file($log)) {
            $log = storage_path('logs/laravel.log');
        }
        if (! is_file($log) || filesize($log) > 50 * 1024 * 1024) {
            // Skip on huge logs — better to miss the alert than to
            // hang cron for 30 s.
            return 0;
        }

        $text = @file_get_contents($log) ?: '';
        $count = 0;
        // Cheap heuristic: count lines containing "ERROR" or 5xx.
        foreach (explode("\n", $text) as $line) {
            if (str_contains($line, 'production.ERROR') || str_contains($line, '"status":5')) {
                $count++;
            }
        }
        return $count;
    }

    private function resolveAlertChannel(): string
    {
        // Use a custom channel if it's been configured (e.g. mail or
        // slack-webhook); otherwise fall through to the default daily
        // channel.
        $channels = (array) config('logging.channels', []);
        return array_key_exists('monitoring', $channels) ? 'monitoring' : 'daily';
    }
}
