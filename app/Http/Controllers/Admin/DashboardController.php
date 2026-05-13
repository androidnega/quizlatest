<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExamSession;
use App\Models\PracticeAttempt;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\University;
use App\Models\User;
use App\Services\ExamRedisService;
use App\Services\ExamRuntimeInfraGate;
use App\Services\RedisHealthService;
use App\Services\SystemExamPolicyService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

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

        $recentActivity = ActivityLog::query()
            ->with(['user:id,name'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        $userManagementOverview = null;
        if (auth()->user()?->isSuperAdmin()) {
            $byRole = User::query()
                ->selectRaw('role, COUNT(*) as aggregate')
                ->groupBy('role')
                ->pluck('aggregate', 'role');
            $adminN = (int) ($byRole['admin'] ?? 0);
            $coordN = (int) ($byRole['coordinator'] ?? 0);
            $examN = (int) ($byRole['examiner'] ?? 0);
            $userManagementOverview = [
                'staff_total' => $adminN + $coordN + $examN,
                'admin' => $adminN,
                'coordinator' => $coordN,
                'examiner' => $examN,
                'student' => (int) ($byRole['student'] ?? 0),
            ];
        }

        $redisUi = $this->redisUiState($redisMode);
        $liveSocketUi = $this->liveSocketUiState($liveSocketsMode, $liveSocketsClientHint);

        $platformChecksTotal = 5;
        $platformChecksPassed = 0;
        if ($dbConnected) {
            $platformChecksPassed++;
        }
        if ($viteBuildPresent && $viteBuildDirPresent) {
            $platformChecksPassed++;
        }
        if ($privateWritable) {
            $platformChecksPassed++;
        }
        if ($redisUi['tone'] !== 'danger') {
            $platformChecksPassed++;
        }
        if ($liveSocketUi['tone'] !== 'danger') {
            $platformChecksPassed++;
        }
        $platformChecksPercent = (int) round(100 * $platformChecksPassed / $platformChecksTotal);

        $publishedExamCount = Quiz::query()->where('status', 'published')->count();
        $quizTotal = Quiz::query()->count();

        $sessionsThisWeekHours = (int) round($this->sumExamSessionSeconds(since: now()->startOfWeek()) / 3600);
        $totalSessionHours = (int) round($this->sumExamSessionSeconds(since: null) / 3600);
        $pendingHeldReviews = Result::query()->where('exam_status', 'submitted_held')->count();
        $pendingManualResultsCount = Result::query()->where('status', 'pending_manual')->count();

        $sessionStatusCounts = ExamSession::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $gradedOrPublishedResults = Result::query()->whereIn('status', ['graded', 'published'])->count();

        $tasksCompleted = Result::query()->whereNotNull('submitted_at')->count()
            + PracticeAttempt::query()->whereNotNull('submitted_at')->count();

        return view('admin.dashboard', [
            'universityCount' => University::count(),
            'coordinatorCount' => User::query()->where('role', 'coordinator')->count(),
            'studentCount' => User::query()->where('role', 'student')->count(),
            'totalUsers' => User::count(),
            'publishedExamCount' => $publishedExamCount,
            'quizTotal' => $quizTotal,
            'tasksCompleted' => $tasksCompleted,
            'questionsBankCount' => Question::query()->count(),
            'sessionsThisWeekHours' => $sessionsThisWeekHours,
            'totalSessionHours' => $totalSessionHours,
            'pendingHeldReviews' => $pendingHeldReviews,
            'pendingManualResultsCount' => $pendingManualResultsCount,
            'examUtilizationPercent' => $quizTotal > 0 ? round(100 * $publishedExamCount / $quizTotal, 1) : 0.0,
            'draftExamCount' => max(0, $quizTotal - $publishedExamCount),
            'sessionStatusCounts' => $sessionStatusCounts,
            'gradedOrPublishedResults' => $gradedOrPublishedResults,
            'activeSessions' => $this->examRedis->activeSessionCountSnapshot(),
            'redisPing' => $redisPing,
            'redisMode' => $redisMode,
            'redisUi' => $redisUi,
            'redisAdminOn' => $redisAdminOn,
            'redisFallback' => $redisFallback,
            'liveSocketsMode' => $liveSocketsMode,
            'liveSocketsClientHint' => $liveSocketsClientHint,
            'liveSocketUi' => $liveSocketUi,
            'platformChecksPassed' => $platformChecksPassed,
            'platformChecksTotal' => $platformChecksTotal,
            'platformChecksPercent' => $platformChecksPercent,
            'viteBuildDirPresent' => $viteBuildDirPresent,
            'viteBuildPresent' => $viteBuildPresent,
            'dbConnected' => $dbConnected,
            'privateWritable' => $privateWritable,
            'queueDriver' => (string) config('queue.default'),
            'otpEnabled' => $this->examPolicy->isOtpEnabled(),
            'smsEnabled' => $this->examPolicy->isSmsEnabled(),
            'publicStorageBytes' => $publicBytes,
            'privateStorageBytes' => $privateBytes,
            'recentActivity' => $recentActivity,
            'userManagementOverview' => $userManagementOverview,
        ]);
    }

    public function healthSnapshot(): JsonResponse
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
        $viteOk = $viteBuildPresent && $viteBuildDirPresent;

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

        $activeSessions = $this->examRedis->activeSessionCountSnapshot();
        $redisUi = $this->redisUiState($redisMode);
        $liveSocketUi = $this->liveSocketUiState($liveSocketsMode, $liveSocketsClientHint);

        $sessionsDetail = $activeSessions['value'] !== null
            ? ($activeSessions['source'] === 'redis' ? __('Counter source: Redis.') : ($activeSessions['source'] === 'database_estimate' ? __('Counter source: database estimate.') : __('Counter source: unavailable.')))
            : __('Live session counter could not be read from Redis or the database.');

        return response()->json([
            'active_sessions' => [
                'value' => $activeSessions['value'],
                'pill' => $activeSessions['value'] !== null ? number_format((int) $activeSessions['value']) : (string) __('N/A'),
                'detail' => $sessionsDetail,
            ],
            'redis' => $redisUi,
            'live_updates' => $liveSocketUi,
            'vite' => [
                'label' => $viteOk ? (string) __('Ready') : (string) __('Incomplete'),
                'detail' => $viteOk ? (string) __('Production assets manifest is present.') : (string) __('Run npm run build before deploying; manifest or build folder is missing.'),
                'tone' => $viteOk ? 'ok' : 'danger',
            ],
            'database' => [
                'label' => $dbConnected ? (string) __('OK') : (string) __('Fail'),
                'detail' => $dbConnected ? (string) __('Application can run queries on the default connection.') : (string) __('The default database connection failed during this check.'),
                'tone' => $dbConnected ? 'ok' : 'danger',
            ],
            'private_storage' => [
                'label' => $privateWritable ? (string) __('OK') : (string) __('Fail'),
                'detail' => $privateBytes !== null
                    ? (string) __('Local disk footprint: :size.', ['size' => Number::fileSize($privateBytes, 1)])
                    : (string) __('Writable private disk used for uploads and evidence.'),
                'tone' => $privateWritable ? 'ok' : 'danger',
            ],
            'public_storage' => [
                'label' => $publicBytes !== null ? Number::fileSize($publicBytes, 1) : '—',
                'detail' => (string) __('Legacy public disk usage (optional).'),
                'tone' => $publicBytes !== null ? 'info' : 'muted',
            ],
            'queue_otp_sms' => [
                'label' => (string) config('queue.default'),
                'detail' => (string) __('OTP :otp · SMS :sms', [
                    'otp' => $this->examPolicy->isOtpEnabled() ? __('on') : __('off'),
                    'sms' => $this->examPolicy->isSmsEnabled() ? __('on') : __('off'),
                ]),
                'tone' => 'muted',
            ],
        ]);
    }

    /**
     * Human-friendly Redis row: fallback is operational, not a hard failure.
     *
     * @return array{label: string, detail: string, tone: 'ok'|'warn'|'muted'|'danger'}
     */
    private function redisUiState(string $redisMode): array
    {
        return match ($redisMode) {
            'connected' => [
                'label' => __('Connected'),
                'detail' => __('TCP ping to Redis succeeded.'),
                'tone' => 'ok',
            ],
            'fallback_active' => [
                'label' => __('Active'),
                'detail' => __('Running without Redis: counters and cache use your database and app cache. No action required unless you intend to use Redis.'),
                'tone' => 'ok',
            ],
            'disabled_by_admin' => [
                'label' => __('Disabled'),
                'detail' => __('Redis runtime is turned off in system settings.'),
                'tone' => 'muted',
            ],
            default => [
                'label' => __('Unavailable'),
                'detail' => __('Redis did not respond and fallback is disabled. Enable fallback or restore Redis.'),
                'tone' => 'danger',
            ],
        };
    }

    /**
     * Live sockets: misconfigured Reverb with polling enabled is still OK for clients.
     *
     * @return array{label: string, detail: string, tone: 'ok'|'warn'|'muted'|'danger'}
     */
    private function liveSocketUiState(string $liveSocketsMode, string $liveSocketsClientHint): array
    {
        if ($liveSocketsMode === 'enabled_configured') {
            return [
                'label' => __('WebSockets ready'),
                'detail' => __('Reverb is configured; browsers can use live sockets.'),
                'tone' => 'ok',
            ];
        }

        if ($liveSocketsMode === 'disabled_by_admin') {
            return [
                'label' => __('Sockets off'),
                'detail' => __('Live WebSockets are disabled in system settings.'),
                'tone' => 'muted',
            ];
        }

        $pollingOk = in_array($liveSocketsClientHint, ['fallback_polling_available', 'polling_available'], true);

        if ($pollingOk) {
            return [
                'label' => __('Polling mode'),
                'detail' => __('WebSocket server is not fully configured, but HTTP polling is enabled so sessions still receive updates.'),
                'tone' => 'warn',
            ];
        }

        return [
            'label' => __('Needs attention'),
            'detail' => __('WebSockets are not configured and polling fallback is disabled. Students may miss live updates.'),
            'tone' => 'danger',
        ];
    }

    private function sumExamSessionSeconds(?CarbonInterface $since = null): float
    {
        $query = ExamSession::query()
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->when($since, fn ($q) => $q->where('start_time', '>=', $since));

        return (float) match (DB::connection()->getDriverName()) {
            'sqlite' => $query->clone()->selectRaw(
                "COALESCE(SUM(CAST(strftime('%s', end_time) AS INTEGER) - CAST(strftime('%s', start_time) AS INTEGER)), 0) as s"
            )->value('s'),
            'pgsql' => $query->clone()->selectRaw(
                'COALESCE(SUM(EXTRACT(EPOCH FROM (end_time - start_time))), 0) as s'
            )->value('s'),
            default => $query->clone()->selectRaw(
                'COALESCE(SUM(TIMESTAMPDIFF(SECOND, start_time, end_time)), 0) as s'
            )->value('s'),
        };
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
