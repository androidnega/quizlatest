<?php

namespace Tests\Feature;

use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Live-Ops Phase 4 — qs:monitor:snapshot smoke test.
 */
class QsMonitorSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);
    }

    public function test_snapshot_command_runs_and_caches_payload(): void
    {
        $exit = Artisan::call('qs:monitor:snapshot');
        $this->assertSame(0, $exit);

        $snap = cache()->get('qs:ops:snapshot');
        $this->assertIsArray($snap);
        foreach ([
            'captured_at',
            'active_sessions',
            'auto_submits_per_hour',
            'held_per_hour',
            'stale_paused_sessions',
            'proctoring_events_per_minute',
            'violating_sessions_now',
            'log_size_mb',
            'private_storage_mb',
            'failed_requests_per_hour',
            'alerts',
        ] as $key) {
            $this->assertArrayHasKey($key, $snap, "snapshot must include {$key}");
        }

        $this->assertSame(0, (int) $snap['active_sessions'], 'fresh DB has no live sessions');
        $this->assertSame([], $snap['alerts'], 'fresh DB should not breach any thresholds');
    }

    public function test_snapshot_command_supports_json_output_flag(): void
    {
        Artisan::call('qs:monitor:snapshot', ['--json' => true]);
        $output = Artisan::output();
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('captured_at', $decoded);
    }
}
