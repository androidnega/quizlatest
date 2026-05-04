<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\University;
use App\Models\User;
use App\Services\ExamRedisService;
use App\Services\ExamRuntimeInfraGate;
use App\Services\RedisHealthService;
use App\Services\SystemExamPolicyService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ExamRedisService $examRedis,
        private readonly RedisHealthService $redisHealth,
        private readonly SystemExamPolicyService $examPolicy,
        private readonly ExamRuntimeInfraGate $infraGate,
    ) {}

    public function index(): View
    {
        $publicBytes = $this->estimatePublicDiskUsageBytes();
        $privateBytes = $this->estimatePrivateDiskUsageBytes();

        $redisPing = $this->redisHealth->isAvailable();
        $redisAdminOn = $this->infraGate->redisRuntimeEnabledByAdmin();
        $redisFallback = $this->infraGate->allowRedisFallback();

        $redisMode = ! $redisAdminOn
            ? 'disabled_by_admin'
            : ($redisPing ? 'connected' : ($redisFallback ? 'fallback_active' : 'unavailable'));

        $liveAdminOn = $this->infraGate->enableLiveSockets();
        $reverbConfigured = $this->infraGate->reverbEnvConfigured();
        $pollingFallback = $this->infraGate->allowPollingFallback();

        $liveSocketsMode = ! $liveAdminOn
            ? 'disabled_by_admin'
            : (! $reverbConfigured ? 'misconfigured' : 'enabled_configured');

        $liveSocketsClientHint = (! $liveAdminOn || ! $reverbConfigured) && $pollingFallback
            ? 'fallback_polling_available'
            : ($pollingFallback ? 'polling_available' : 'polling_disabled');

        $manifestPath = public_path('build/manifest.json');
        $viteBuildDirPresent = is_dir(public_path('build'));
        $viteBuildPresent = is_file($manifestPath);

        $dbConnected = false;
        try {
            DB::select('select 1 as ok');
            $dbConnected = true;
        } catch (\Throwable) {
            //
        }

        $privateWritable = false;
        try {
            $probe = '.qs_health_probe_'.uniqid();
            Storage::disk('local')->put($probe, '1');
            Storage::disk('local')->delete($probe);
            $privateWritable = true;
        } catch (\Throwable) {
            //
        }

        return view('admin.dashboard', [
            'universityCount' => University::count(),
            'coordinatorCount' => User::query()->where('role', 'coordinator')->count(),
            'studentCount' => User::query()->where('role', 'student')->count(),
            'publishedExamCount' => Quiz::query()->where('status', 'published')->count(),
            'activeSessions' => $this->examRedis->activeSessionCountSnapshot(),
            'redisPing' => $redisPing,
            'redisMode' => $redisMode,
            'redisAdminOn' => $redisAdminOn,
            'redisFallback' => $redisFallback,
            'liveSocketsMode' => $liveSocketsMode,
            'liveSocketsClientHint' => $liveSocketsClientHint,
            'viteBuildDirPresent' => $viteBuildDirPresent,
            'viteBuildPresent' => $viteBuildPresent,
            'dbConnected' => $dbConnected,
            'privateWritable' => $privateWritable,
            'queueDriver' => (string) config('queue.default'),
            'otpEnabled' => $this->examPolicy->isOtpEnabled(),
            'smsEnabled' => $this->examPolicy->isSmsEnabled(),
            'publicStorageBytes' => $publicBytes,
            'privateStorageBytes' => $privateBytes,
        ]);
    }

    private function estimatePublicDiskUsageBytes(): ?int
    {
        try {
            $disk = Storage::disk('public');
            $total = 0;
            foreach ($disk->allFiles() as $path) {
                try {
                    $total += $disk->size($path);
                } catch (\Throwable) {
                    //
                }
            }

            return $total;
        } catch (\Throwable) {
            return null;
        }
    }

    private function estimatePrivateDiskUsageBytes(): ?int
    {
        try {
            $disk = Storage::disk('local');
            $total = 0;
            foreach ($disk->allFiles() as $path) {
                try {
                    $total += $disk->size($path);
                } catch (\Throwable) {
                    //
                }
            }

            return $total;
        } catch (\Throwable) {
            return null;
        }
    }
}
