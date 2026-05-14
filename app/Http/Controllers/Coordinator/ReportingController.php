<?php

namespace App\Http\Controllers\Coordinator;

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
        abort_unless($user !== null && $user->role === 'coordinator', 403);

        $departmentIds = $this->analytics->coordinatorDepartmentIds($user);
        $snapshot = $this->analytics->coordinatorSnapshot($departmentIds);
        $classRows = $this->analytics->coordinatorClassCompletionRows($departmentIds);
        $courseRows = $this->analytics->coordinatorCoursePerformanceRows($departmentIds);
        $examinerRows = $this->analytics->coordinatorExaminerActivityRows($departmentIds);

        return view('coordinator.reporting.index', [
            'departmentIds' => $departmentIds,
            'snapshot' => $snapshot,
            'classRows' => $classRows,
            'courseRows' => $courseRows,
            'examinerRows' => $examinerRows,
        ]);
    }

    public function exportClassCompletionCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'coordinator', 403);

        $departmentIds = $this->analytics->coordinatorDepartmentIds($user);
        $rows = $this->analytics->coordinatorClassCompletionRows($departmentIds);
        $filename = 'class-completion-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['class_id', 'class', 'program', 'students', 'published_assessments', 'submitted_sessions']);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['class_id'] ?? '',
                    $r['class_label'] ?? '',
                    $r['program'] ?? '',
                    $r['students'] ?? '',
                    $r['published_assessments'] ?? '',
                    $r['submitted_session_count'] ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportCoursePerformanceCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'coordinator', 403);

        $departmentIds = $this->analytics->coordinatorDepartmentIds($user);
        $rows = $this->analytics->coordinatorCoursePerformanceRows($departmentIds);
        $filename = 'course-performance-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'course_id',
                'code',
                'title',
                'published_assessments',
                'pending_grading',
                'avg_score',
                'results_published',
                'graded_unpublished',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['course_id'] ?? '',
                    $r['code'] ?? '',
                    $r['title'] ?? '',
                    $r['published_assessments'] ?? '',
                    $r['pending_grading'] ?? '',
                    $r['avg_score'] ?? '',
                    $r['results_published'] ?? '',
                    $r['graded_unpublished'] ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
