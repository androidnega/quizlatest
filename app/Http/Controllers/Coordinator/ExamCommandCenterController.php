<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\ExamCommandCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live-Ops Phase 2 — Exam Command Center.
 *
 * Two endpoints, same data:
 *   GET  /dashboard/coordinator/command-center        → HTML shell
 *   GET  /dashboard/coordinator/command-center/metrics → JSON payload
 *
 * The HTML view polls the JSON endpoint every 10 seconds. Every
 * metric is scoped to the coordinator's university_id so a
 * coordinator at university A never sees university B's load.
 */
class ExamCommandCenterController extends Controller
{
    public function __construct(
        private readonly ExamCommandCenterService $commandCenter,
    ) {}

    public function index(Request $request): View
    {
        $universityId = (int) $request->user()->university_id;

        return view('coordinator.command-center.index', [
            'universityId' => $universityId,
            'metricsUrl' => route('coordinator.command-center.metrics'),
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $universityId = (int) $request->user()->university_id;

        return response()->json($this->commandCenter->metrics($universityId), 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
