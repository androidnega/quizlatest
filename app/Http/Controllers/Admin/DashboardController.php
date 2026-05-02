<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\University;
use App\Models\User;
use App\Services\ExamRedisService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ExamRedisService $examRedis,
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'universityCount' => University::count(),
            'coordinatorCount' => User::query()->where('role', 'coordinator')->count(),
            'studentCount' => User::query()->where('role', 'student')->count(),
            'activeExamSessions' => $this->examRedis->getGlobalActiveSessionCount(),
        ]);
    }
}
