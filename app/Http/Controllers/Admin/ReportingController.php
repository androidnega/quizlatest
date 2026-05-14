<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AssessmentAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function __construct(
        private readonly AssessmentAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'admin', 403);

        $snapshot = $this->analytics->adminSystemSnapshot();

        return view('admin.reporting.index', [
            'snapshot' => $snapshot,
        ]);
    }

    public function exportSystemSummaryCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'admin', 403);

        $s = $this->analytics->adminSystemSnapshot();
        $filename = 'system-summary-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($s): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['metric', 'value']);
            foreach ([
                'assessments_total' => $s['assessments_total'] ?? '',
                'submissions_total' => $s['submissions_total'] ?? '',
                'students_total' => $s['students_total'] ?? '',
                'examiners_total' => $s['examiners_total'] ?? '',
                'coordinators_total' => $s['coordinators_total'] ?? '',
                'universities' => $s['universities'] ?? '',
                'departments' => $s['departments'] ?? '',
                'classes_active' => $s['classes_active'] ?? '',
                'flagged_sessions' => $s['flagged_sessions'] ?? '',
                'results_graded' => $s['results_graded'] ?? '',
                'results_published' => $s['results_published'] ?? '',
                'pending_manual' => $s['pending_manual'] ?? '',
            ] as $k => $v) {
                fputcsv($handle, [$k, $v]);
            }
            fputcsv($handle, ['by_assessment_type_json', json_encode($s['by_assessment_type'] ?? [])]);
            fputcsv($handle, ['submissions_by_type_json', json_encode($s['submissions_by_type'] ?? [])]);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
