<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmissionFile;
use App\Models\ExamSessionAnswer;
use App\Models\Quiz;
use App\Models\User;
use App\Services\AssessmentAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentAnalyticsController extends Controller
{
    private const TABS = ['overview', 'questions', 'sections', 'students', 'proctoring'];

    public function __construct(
        private readonly AssessmentAnalyticsService $analytics,
    ) {}

    public function show(Request $request, Quiz $exam): View
    {
        $this->authorize('view', $exam);

        $tab = (string) $request->query('tab', 'overview');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'overview';
        }

        $filter = $request->query('filter');
        $filter = is_string($filter) && $filter !== '' && $filter !== 'all' ? $filter : null;

        $cohort = $this->analytics->cohortOverview($exam);
        $assignmentExtras = $this->analytics->assignmentExtras($exam);
        $questionRows = $this->analytics->questionPerformance($exam);
        $sectionRows = $this->analytics->sectionPerformance($exam, $questionRows);
        $topicRows = $this->analytics->topicPerformance($questionRows);
        $studentRows = $this->analytics->studentPerformanceRows($exam, $filter);
        $proctoring = $this->analytics->proctoringBlock($exam);

        return view('examiner.analytics.show', [
            'exam' => $exam,
            'tab' => $tab,
            'filter' => $filter,
            'cohort' => $cohort,
            'assignmentExtras' => $assignmentExtras,
            'questionRows' => $questionRows,
            'sectionRows' => $sectionRows,
            'topicRows' => $topicRows,
            'studentRows' => $studentRows,
            'proctoring' => $proctoring,
        ]);
    }

    public function exportStudentsCsv(Request $request, Quiz $exam): StreamedResponse
    {
        $this->authorize('view', $exam);

        $filter = $request->query('filter');
        $filter = is_string($filter) && $filter !== '' && $filter !== 'all' ? $filter : null;
        $rows = $this->analytics->studentPerformanceRows($exam, $filter);

        $filename = 'student-performance-'.$exam->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'name',
                'index_number',
                'class',
                'session_status',
                'started_at',
                'submitted_at',
                'duration_seconds',
                'score',
                'percentage',
                'result_status',
                'risk_state',
                'auto_submit_reason',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    (string) ($r['name'] ?? ''),
                    (string) ($r['index_number'] ?? ''),
                    (string) ($r['class'] ?? ''),
                    (string) ($r['session_status'] ?? ''),
                    $this->csvDate($r['started_at'] ?? null),
                    $this->csvDate($r['submitted_at'] ?? null),
                    $r['duration_seconds'] ?? '',
                    $r['score'] ?? '',
                    $r['percentage'] ?? '',
                    (string) ($r['result_status'] ?? ''),
                    (string) ($r['risk_state'] ?? ''),
                    (string) ($r['auto_submit_reason'] ?? ''),
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportQuestionsCsv(Request $request, Quiz $exam): StreamedResponse
    {
        $this->authorize('view', $exam);

        $rows = $this->analytics->questionPerformance($exam);
        $filename = 'question-performance-'.$exam->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'question_id',
                'section',
                'topic',
                'type',
                'preview',
                'marks',
                'answered',
                'correct',
                'wrong',
                'unanswered',
                'avg_score',
                'difficulty',
                'mcq_correct_label',
                'mcq_most_wrong_label',
                'mcq_distribution',
                'essay_graded',
                'essay_pending',
                'essay_avg',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['question_id'] ?? '',
                    $r['section'] ?? '',
                    $r['topic'] ?? '',
                    $r['type'] ?? '',
                    $r['preview'] ?? '',
                    $r['marks'] ?? '',
                    $r['answered'] ?? '',
                    $r['correct'] ?? '',
                    $r['wrong'] ?? '',
                    $r['unanswered'] ?? '',
                    $r['avg_score'] ?? '',
                    $r['difficulty'] ?? '',
                    $r['mcq_correct_label'] ?? '',
                    $r['mcq_most_wrong_label'] ?? '',
                    json_encode($r['mcq_distribution'] ?? []),
                    $r['essay_graded'] ?? '',
                    $r['essay_pending'] ?? '',
                    $r['essay_avg'] ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportProctoringCsv(Request $request, Quiz $exam): StreamedResponse
    {
        $this->authorize('view', $exam);

        $block = $this->analytics->proctoringBlock($exam);
        $filename = 'proctoring-events-'.$exam->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($block): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['student', 'event_type', 'time', 'risk_level', 'action', 'metadata_summary']);
            foreach ($block['timeline'] ?? [] as $row) {
                fputcsv($handle, [
                    (string) ($row['student'] ?? ''),
                    (string) ($row['event_type'] ?? ''),
                    $this->csvDate($row['at'] ?? null),
                    (string) ($row['risk_level'] ?? ''),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['summary'] ?? ''),
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportAssignmentSubmissionsCsv(Request $request, Quiz $exam): StreamedResponse
    {
        $this->authorize('view', $exam);

        abort_unless($exam->isAssignment(), 404);

        $filename = 'assignment-submissions-'.$exam->id.'-'.now()->format('Y-m-d-His').'.csv';

        $eligible = $this->analytics->eligibleStudentIds($exam);
        $students = User::query()
            ->whereIn('id', $eligible->all())
            ->with(['classroom:id,name,section'])
            ->orderBy('name')
            ->get(['id', 'name', 'index_number', 'class_id']);

        $latest = $this->analytics->latestSessionsByStudent($exam);

        return response()->streamDownload(function () use ($exam, $students, $latest): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'index_number',
                'name',
                'class',
                'session_status',
                'submitted_at',
                'has_text_response',
                'attachment_count',
                'submitted_late',
            ]);

            foreach ($students as $stu) {
                $session = $latest->get($stu->id);
                $sessionStatus = $session ? ($session->status === 'submitted' ? 'submitted' : 'in_progress') : 'not_started';
                $hasText = false;
                $fileCount = 0;
                if ($session) {
                    $hasText = ExamSessionAnswer::query()
                        ->where('exam_session_id', $session->id)
                        ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
                        ->where(function ($q): void {
                            $q->where(function ($q2): void {
                                $q2->whereNotNull('answer_text')->where('answer_text', '!=', '');
                            })->orWhereNotNull('answer_payload');
                        })
                        ->exists();
                    $fileCount = (int) AssignmentSubmissionFile::query()
                        ->where('exam_session_id', $session->id)
                        ->where('quiz_id', (int) $exam->id)
                        ->count();
                }

                fputcsv($handle, [
                    (string) ($stu->index_number ?? ''),
                    (string) $stu->name,
                    $stu->classroom ? trim($stu->classroom->name.' '.$stu->classroom->section) : '—',
                    $sessionStatus,
                    $this->csvDate($session?->end_time),
                    $hasText ? 'yes' : 'no',
                    $fileCount,
                    $session && $session->submitted_late ? 'yes' : 'no',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function csvDate(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }

        return '';
    }
}
