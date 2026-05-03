<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\University;
use App\Models\User;
use App\Services\ExamRedisService;
use App\Services\RedisHealthService;
use App\Services\SystemExamPolicyService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ExamRedisService $examRedis,
        private readonly RedisHealthService $redisHealth,
        private readonly SystemExamPolicyService $examPolicy,
    ) {}

    public function index(): View
    {
        $publicBytes = $this->estimatePublicDiskUsageBytes();
        $privateBytes = $this->estimatePrivateDiskUsageBytes();

        return view('admin.dashboard', [
            'universityCount' => University::count(),
            'coordinatorCount' => User::query()->where('role', 'coordinator')->count(),
            'studentCount' => User::query()->where('role', 'student')->count(),
            'publishedExamCount' => Quiz::query()->where('status', 'published')->count(),
            'activeExamSessions' => $this->examRedis->getGlobalActiveSessionCount(),
            'redisAvailable' => $this->redisHealth->isAvailable(),
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
