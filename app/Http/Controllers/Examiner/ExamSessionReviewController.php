<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmissionFile;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use App\Services\ExamSessionInvalidateForRetakeService;
use App\Services\ResultFinalizationService;
use App\Services\SensitiveStorageService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExamSessionReviewController extends Controller
{
    private const RISK_STATES = ['normal', 'warning', 'suspicious', 'critical', 'locked'];

    /** @var list<string> */
    private const INTEGRITY_SESSION_FILTERS = ['flagged', 'auto_submitted', 'phone_detected', 'tab_switch_limit'];

    /** @var list<string> */
    private const VIOLATION_EVENT_TYPES = [
        'tab_switch',
        'phone_detected',
        'face_missing',
        'face_covered',
        'face_obstructed',
        'face_not_clear',
        'fullscreen_exit',
        'essay_clipboard_attempt',
        'exam_integrity_signal',
        'possible_screenshot_attempt',
        'external_display_risk',
        'proctoring_overlay_resolved',
    ];

    /**
     * Data for the Sessions tab inside the quiz workspace (no full-page navigation).
     *
     * @return array<string, mixed>
     */
    public function buildSessionsWorkspacePayload(Request $request, Quiz $exam): array
    {
        $this->authorize('manageResults', $exam);

        $analytics = $this->examAnalyticsSnapshot($exam);

        $query = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->with(['student'])
            ->orderByDesc('end_time')
            ->orderByDesc('id');

        $statusFilter = $request->query('status');
        if (is_string($statusFilter) && $statusFilter !== '') {
            match ($statusFilter) {
                'in_progress' => $query->where('status', '!=', 'submitted'),
                'submitted' => $query->where('status', 'submitted')->whereNotExists(
                    fn (Builder $sub) => $this->scopedResultSubquery($sub, $exam->id),
                ),
                'held', 'pending_manual', 'graded' => $query->where('status', 'submitted')->whereExists(
                    fn (Builder $sub) => $this->scopedResultSubquery($sub, $exam->id, $statusFilter),
                ),
                default => null,
            };
        }

        $riskFilter = $request->query('risk_state');
        if (is_string($riskFilter) && in_array($riskFilter, self::RISK_STATES, true)) {
            $query->where('risk_state', $riskFilter);
        }

        $integrityFilterRaw = $request->query('integrity');
        $integrityFilter = is_string($integrityFilterRaw) ? $integrityFilterRaw : null;
        if ($integrityFilter !== null && in_array($integrityFilter, self::INTEGRITY_SESSION_FILTERS, true)) {
            $examId = (int) $exam->id;
            match ($integrityFilter) {
                'flagged' => $query->where(function ($q) use ($examId): void {
                    $q->whereIn('risk_state', ['suspicious', 'critical', 'locked'])
                        ->orWhereExists(function (Builder $sub) use ($examId): void {
                            $sub->from('results')
                                ->whereColumn('results.user_id', 'exam_sessions.student_id')
                                ->where('results.quiz_id', $examId)
                                ->where('results.status', 'held')
                                ->selectRaw('1');
                        });
                }),
                'auto_submitted' => $query->whereNotNull('auto_submit_reason_code'),
                'phone_detected' => $query->where(function ($q) use ($examId): void {
                    $q->where('auto_submit_reason_code', 'phone_detected')
                        ->orWhereExists(function (Builder $sub) use ($examId): void {
                            $sub->from('proctoring_events')
                                ->whereColumn('proctoring_events.user_id', 'exam_sessions.student_id')
                                ->where('proctoring_events.quiz_id', $examId)
                                ->where('proctoring_events.event_type', 'phone_detected')
                                ->whereColumn('proctoring_events.metadata->session_id', 'exam_sessions.session_id')
                                ->selectRaw('1');
                        });
                }),
                'tab_switch_limit' => $query->where('auto_submit_reason_code', 'tab_switch_limit'),
                default => null,
            };
        }

        $search = $request->query('q');
        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->whereHas('student', function ($q) use ($term): void {
                $q->where('index_number', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        $sessions = $query->paginate(25)->withQueryString();

        $studentIds = $sessions->getCollection()->pluck('student_id')->unique()->values();
        $resultsByStudentId = Result::query()
            ->where('quiz_id', $exam->id)
            ->whereIn('user_id', $studentIds)
            ->get()
            ->keyBy('user_id');

        $sessions->getCollection()->each(function (ExamSession $session) use ($resultsByStudentId) {
            $session->setRelation('result', $resultsByStudentId->get($session->student_id));
            $session->setAttribute('workflow_display_status', $this->workflowDisplayStatus($session));
        });

        $resultsByClassCount = Classroom::query()
            ->whereHas('courses', fn ($q) => $q->whereKey($exam->course_id))
            ->count();
        $overviewAttemptsCount = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->count();
        $flaggedStudentsCount = $analytics['flagged_sessions']->count();

        $scoreBounds = Result::query()
            ->where('quiz_id', $exam->id)
            ->selectRaw('MIN(score) as lo, MAX(score) as hi')
            ->first();

        $violationEventTotal = array_sum($analytics['violation_totals']);
        $studentsWithViolations = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->where('violation_count', '>', 0)
            ->distinct()
            ->count('student_id');

        return [
            'exam' => $exam->loadMissing('course:id,code,title'),
            'sessions' => $sessions,
            'riskStates' => self::RISK_STATES,
            'integrityFilter' => $integrityFilter !== null && in_array($integrityFilter, self::INTEGRITY_SESSION_FILTERS, true)
                ? $integrityFilter
                : null,
            'analytics' => $analytics,
            'resultsByClassCount' => $resultsByClassCount,
            'overviewAttemptsCount' => $overviewAttemptsCount,
            'flaggedStudentsCount' => $flaggedStudentsCount,
            'scoreLow' => $scoreBounds->lo ?? null,
            'scoreHigh' => $scoreBounds->hi ?? null,
            'violationEventTotal' => $violationEventTotal,
            'studentsWithViolations' => $studentsWithViolations,
        ];
    }

    /**
     * Legacy URL: sessions list now lives in the quiz workspace tab (client-side switch).
     */
    public function index(Request $request, Quiz $exam): RedirectResponse
    {
        $this->authorize('manageResults', $exam);

        $params = ['exam' => $exam, 'tab' => 'sessions'];
        foreach (['status', 'risk_state', 'integrity', 'q', 'page'] as $key) {
            $v = $request->query($key);
            if ($v !== null && $v !== '') {
                $params[$key] = $v;
            }
        }

        return redirect()->route('examiner.quizzes.workspace', $params);
    }

    public function invalidateSessionsInRange(
        Request $request,
        Quiz $exam,
        ExamSessionInvalidateForRetakeService $invalidator,
    ): RedirectResponse {
        $this->authorize('manageResults', $exam);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);

        $studentIds = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->whereNotNull('end_time')
            ->whereBetween('end_time', [$from, $to])
            ->distinct()
            ->pluck('student_id')
            ->unique()
            ->values();

        $cleared = 0;
        foreach ($studentIds as $studentId) {
            $invalidator->invalidate((int) $studentId, (int) $exam->id);
            $cleared++;
        }

        return back()->with(
            'status',
            __('Cleared :n student record(s) for this quiz who completed within the selected window. Each student can start again.', ['n' => $cleared]),
        );
    }

    public function exportCsv(Request $request, Quiz $exam): StreamedResponse
    {
        $this->authorize('manageResults', $exam);

        $query = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->with(['student'])
            ->orderByDesc('end_time')
            ->orderByDesc('id');

        $statusFilter = $request->query('status');
        if (is_string($statusFilter) && $statusFilter !== '') {
            match ($statusFilter) {
                'in_progress' => $query->where('status', '!=', 'submitted'),
                'submitted' => $query->where('status', 'submitted')->whereNotExists(
                    fn (Builder $sub) => $this->scopedResultSubquery($sub, $exam->id),
                ),
                'held', 'pending_manual', 'graded' => $query->where('status', 'submitted')->whereExists(
                    fn (Builder $sub) => $this->scopedResultSubquery($sub, $exam->id, $statusFilter),
                ),
                default => null,
            };
        }

        $riskFilter = $request->query('risk_state');
        if (is_string($riskFilter) && in_array($riskFilter, self::RISK_STATES, true)) {
            $query->where('risk_state', $riskFilter);
        }

        $integrityFilterRaw = $request->query('integrity');
        $integrityFilter = is_string($integrityFilterRaw) ? $integrityFilterRaw : null;
        if ($integrityFilter !== null && in_array($integrityFilter, self::INTEGRITY_SESSION_FILTERS, true)) {
            $examId = (int) $exam->id;
            match ($integrityFilter) {
                'flagged' => $query->where(function ($q) use ($examId): void {
                    $q->whereIn('risk_state', ['suspicious', 'critical', 'locked'])
                        ->orWhereExists(function (Builder $sub) use ($examId): void {
                            $sub->from('results')
                                ->whereColumn('results.user_id', 'exam_sessions.student_id')
                                ->where('results.quiz_id', $examId)
                                ->where('results.status', 'held')
                                ->selectRaw('1');
                        });
                }),
                'auto_submitted' => $query->whereNotNull('auto_submit_reason_code'),
                'phone_detected' => $query->where(function ($q) use ($examId): void {
                    $q->where('auto_submit_reason_code', 'phone_detected')
                        ->orWhereExists(function (Builder $sub) use ($examId): void {
                            $sub->from('proctoring_events')
                                ->whereColumn('proctoring_events.user_id', 'exam_sessions.student_id')
                                ->where('proctoring_events.quiz_id', $examId)
                                ->where('proctoring_events.event_type', 'phone_detected')
                                ->whereColumn('proctoring_events.metadata->session_id', 'exam_sessions.session_id')
                                ->selectRaw('1');
                        });
                }),
                'tab_switch_limit' => $query->where('auto_submit_reason_code', 'tab_switch_limit'),
                default => null,
            };
        }

        $search = $request->query('q');
        if (is_string($search) && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->whereHas('student', function ($q) use ($term): void {
                $q->where('index_number', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        $rows = $query->get();
        $studentIds = $rows->pluck('student_id')->unique()->values();
        $resultsByStudentId = Result::query()
            ->where('quiz_id', $exam->id)
            ->whereIn('user_id', $studentIds)
            ->get()
            ->keyBy('user_id');

        $filename = 'sessions-'.$exam->id.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows, $resultsByStudentId): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['index_number', 'name', 'status', 'risk_state', 'violations', 'score', 'end_time']);
            foreach ($rows as $session) {
                $session->setRelation('result', $resultsByStudentId->get($session->student_id));
                $session->setAttribute('workflow_display_status', $this->workflowDisplayStatus($session));
                $result = $session->result;
                fputcsv($handle, [
                    $session->student?->index_number ?? '',
                    $session->student?->name ?? '',
                    str_replace('_', ' ', (string) $session->workflow_display_status),
                    $session->risk_state,
                    (string) $session->violation_count,
                    $result !== null ? (string) $result->score : '',
                    $session->end_time?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function classSummary(Request $request, Quiz $exam): View
    {
        $this->authorize('manageResults', $exam);

        $classrooms = Classroom::query()
            ->whereHas('courses', fn ($q) => $q->whereKey($exam->course_id))
            ->withCount('students')
            ->orderBy('name')
            ->get(['id', 'name', 'section']);

        $statsRows = Result::query()
            ->selectRaw('users.class_id as class_id')
            ->selectRaw('COUNT(results.id) as submitted_count')
            ->selectRaw('AVG(results.score) as average_score')
            ->selectRaw("SUM(CASE WHEN results.status = 'pending_manual' THEN 1 ELSE 0 END) as pending_manual_count")
            ->selectRaw("SUM(CASE WHEN results.status = 'held' THEN 1 ELSE 0 END) as held_count")
            ->join('users', 'users.id', '=', 'results.user_id')
            ->where('users.role', 'student')
            ->where('results.quiz_id', $exam->id)
            ->whereIn('users.class_id', $classrooms->pluck('id'))
            ->groupBy('users.class_id')
            ->get()
            ->keyBy('class_id');

        return view('examiner.exam_sessions.class-summary', [
            'exam' => $exam->loadMissing('course'),
            'classrooms' => $classrooms,
            'statsRows' => $statsRows,
        ]);
    }

    public function classResults(Request $request, Quiz $exam, Classroom $classroom): View
    {
        $this->authorize('manageResults', $exam);
        $this->assertClassLinkedToExamCourse($classroom, (int) $exam->course_id);

        $students = User::query()
            ->where('role', 'student')
            ->where('class_id', $classroom->id)
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $studentIds = $students->getCollection()->pluck('id')->all();

        $sessions = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('id')
            ->get(['id', 'session_id', 'student_id', 'status', 'risk_state', 'violation_count', 'start_time', 'end_time']);

        $latestSessionByStudentId = $sessions
            ->groupBy('student_id')
            ->map(fn (Collection $group): ?ExamSession => $group->first());

        $resultsByStudentId = Result::query()
            ->where('quiz_id', $exam->id)
            ->whereIn('user_id', $studentIds)
            ->get()
            ->keyBy('user_id');

        return view('examiner.exam_sessions.class-results', [
            'exam' => $exam->loadMissing('course'),
            'classroom' => $classroom,
            'students' => $students,
            'latestSessionByStudentId' => $latestSessionByStudentId,
            'resultsByStudentId' => $resultsByStudentId,
        ]);
    }

    public function courseOverview(Request $request, Course $course): View
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            abort_unless($user->role === 'examiner', 403);
            abort_unless($this->isAssignedExaminerForCourse((int) $user->id, (int) $course->id), 403);
        }

        return view('examiner.courses.show', [
            'course' => $course->loadMissing('department'),
        ]);
    }

    public function courseClassOverview(Request $request, Course $course, Classroom $classroom): View
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            abort_unless($user->role === 'examiner', 403);
            abort_unless($this->isAssignedExaminerForCourse((int) $user->id, (int) $course->id), 403);
        }

        $this->assertClassLinkedToExamCourse($classroom, (int) $course->id);

        $myExams = Quiz::query()
            ->where('course_id', $course->id)
            ->where('created_by', $user->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'status', 'assessment_type', 'total_marks', 'created_at']);

        $examRows = Result::query()
            ->selectRaw('results.quiz_id as quiz_id')
            ->selectRaw('COUNT(results.id) as submitted_count')
            ->selectRaw('AVG(results.score) as average_score')
            ->selectRaw("SUM(CASE WHEN results.status = 'pending_manual' THEN 1 ELSE 0 END) as pending_manual_count")
            ->selectRaw("SUM(CASE WHEN results.status = 'held' THEN 1 ELSE 0 END) as held_count")
            ->join('users', 'users.id', '=', 'results.user_id')
            ->where('users.role', 'student')
            ->where('users.class_id', $classroom->id)
            ->whereIn('results.quiz_id', $myExams->pluck('id'))
            ->groupBy('results.quiz_id')
            ->get()
            ->keyBy('quiz_id');

        return view('examiner.courses.class-overview', [
            'course' => $course,
            'classroom' => $classroom->loadMissing('level:id,name,code'),
            'myExams' => $myExams,
            'examRows' => $examRows,
        ]);
    }

    /**
     * Lightweight aggregates for the session list page (no event bodies, no images).
     *
     * @return array{
     *     total_students:int,
     *     submitted_count:int,
     *     held_count:int,
     *     pending_manual_count:int,
     *     average_score:?float,
     *     high_risk_session_count:int,
     *     risk_distribution: array{normal:int,warning:int,suspicious:int,critical:int},
     *     violation_totals: array<string,int>,
     *     flagged_sessions: Collection<int, ExamSession>
     * }
     */
    private function examAnalyticsSnapshot(Quiz $exam): array
    {
        $examId = (int) $exam->id;

        $sessionRow = ExamSession::query()
            ->where('exam_id', $examId)
            ->selectRaw('COUNT(DISTINCT student_id) as total_students')
            ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count")
            ->selectRaw("SUM(CASE WHEN risk_state IN ('suspicious','critical','locked') THEN 1 ELSE 0 END) as high_risk_session_count")
            ->first();

        $resultRow = Result::query()
            ->where('quiz_id', $examId)
            ->selectRaw("SUM(CASE WHEN status = 'held' THEN 1 ELSE 0 END) as held_count")
            ->selectRaw("SUM(CASE WHEN status = 'pending_manual' THEN 1 ELSE 0 END) as pending_manual_count")
            ->selectRaw('AVG(score) as average_score')
            ->first();

        $riskCounts = ExamSession::query()
            ->where('exam_id', $examId)
            ->select(['risk_state', DB::raw('COUNT(*) as c')])
            ->groupBy('risk_state')
            ->pluck('c', 'risk_state');

        $riskDistribution = [
            'normal' => (int) ($riskCounts['normal'] ?? 0),
            'warning' => (int) ($riskCounts['warning'] ?? 0),
            'suspicious' => (int) ($riskCounts['suspicious'] ?? 0),
            'critical' => (int) (($riskCounts['critical'] ?? 0) + ($riskCounts['locked'] ?? 0)),
        ];

        $violationTotals = ProctoringEvent::query()
            ->where('quiz_id', $examId)
            ->whereIn('event_type', self::VIOLATION_EVENT_TYPES)
            ->select(['event_type', DB::raw('COUNT(*) as cnt')])
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type');

        $violationTotalsFilled = Collection::make(self::VIOLATION_EVENT_TYPES)
            ->mapWithKeys(fn (string $type) => [$type => (int) ($violationTotals[$type] ?? 0)])
            ->all();

        $flaggedSessions = ExamSession::query()
            ->where('exam_id', $examId)
            ->where(function ($q) use ($examId): void {
                $q->whereIn('risk_state', ['suspicious', 'critical', 'locked'])
                    ->orWhereExists(function (Builder $sub) use ($examId): void {
                        $sub->from('results')
                            ->whereColumn('results.user_id', 'exam_sessions.student_id')
                            ->where('results.quiz_id', $examId)
                            ->where('results.status', 'held')
                            ->selectRaw('1');
                    });
            })
            ->with(['student:id,name'])
            ->orderByDesc('violation_count')
            ->limit(100)
            ->get(['id', 'session_id', 'student_id', 'risk_state', 'violation_count']);

        $avg = optional($resultRow)->average_score;

        return [
            'total_students' => (int) ($sessionRow->total_students ?? 0),
            'submitted_count' => (int) ($sessionRow->submitted_count ?? 0),
            'held_count' => (int) (optional($resultRow)->held_count ?? 0),
            'pending_manual_count' => (int) (optional($resultRow)->pending_manual_count ?? 0),
            'average_score' => $avg !== null ? round((float) $avg, 2) : null,
            'high_risk_session_count' => (int) ($sessionRow->high_risk_session_count ?? 0),
            'risk_distribution' => $riskDistribution,
            'violation_totals' => $violationTotalsFilled,
            'flagged_sessions' => $flaggedSessions,
        ];
    }

    public function show(Request $request, ExamSession $examSession, SensitiveStorageService $sensitiveStorage): View
    {
        $this->authorize('view', $examSession);

        $examSession->load(['student', 'exam.course']);

        $result = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->first();

        $examSession->setRelation('result', $result);

        $finalization = app(ResultFinalizationService::class);
        $workflowStatus = $finalization->resolveStatus($examSession);

        $exam = $examSession->exam;
        $canManageResults = $exam !== null && Gate::forUser($request->user())->allows('manageResults', $exam);

        $events = ProctoringEvent::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->where('metadata->session_id', $examSession->session_id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'event_type', 'severity', 'flagged', 'action_taken', 'created_at', 'metadata'])
            ->sortBy('created_at')
            ->values();

        $timeline = $events->map(fn (ProctoringEvent $e) => [
            'at' => $e->created_at,
            'event_type' => $e->event_type,
            'action' => $e->action_taken ?? '—',
            'metadata_summary' => $this->summarizeProctoringMetadata(is_array($e->metadata) ? $e->metadata : []),
            'is_warning' => ($e->action_taken ?? '') === 'warn' || $e->flagged,
            'is_auto_submit' => ($e->action_taken ?? '') === 'autosubmit',
        ]);

        $assignmentSubmissionFiles = AssignmentSubmissionFile::query()
            ->where('exam_session_id', $examSession->id)
            ->orderBy('id')
            ->get(['id', 'original_filename', 'mime_type', 'file_size', 'uploaded_at']);

        $thumbnails = [];
        foreach ($events as $e) {
            $meta = is_array($e->metadata) ? $e->metadata : [];
            $path = $meta['file_path'] ?? data_get($meta, 'payload.file_path');
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (! $sensitiveStorage->existsAnywhere($path)) {
                continue;
            }
            $thumbnails[] = [
                'url' => route('examiner.exam-sessions.evidence.event', [$examSession, $e]),
                'event_type' => $e->event_type,
                'at' => $e->created_at,
            ];
        }

        $verificationPath = $examSession->verification_image_path;
        $verificationEvidenceUrl = null;
        if (is_string($verificationPath) && $verificationPath !== '' && $sensitiveStorage->existsAnywhere($verificationPath)) {
            $verificationEvidenceUrl = route('examiner.exam-sessions.evidence.verification', $examSession);
        }

        $classResultsUrl = null;
        if ($exam !== null && $examSession->class_id) {
            $cr = Classroom::query()->find((int) $examSession->class_id);
            if ($cr !== null && $cr->courses()->whereKey((int) $exam->course_id)->exists()) {
                $classResultsUrl = route('examiner.exams.classes.results', [$exam, $cr]);
            }
        }

        $isAssignmentSession = (bool) ($exam?->isAssignment());
        $assignmentStudentResponse = null;
        $assignmentPasteAttemptCount = 0;
        if ($isAssignmentSession) {
            $assignmentPasteAttemptCount = $events->where('event_type', 'essay_clipboard_attempt')->count();
            $examSession->loadMissing(['answers.question']);
            foreach ($examSession->answers as $ans) {
                $q = $ans->question;
                if ($q?->type !== 'essay') {
                    continue;
                }
                $pl = $ans->answer_payload;
                if (is_array($pl) && isset($pl['text']) && is_string($pl['text'])) {
                    $assignmentStudentResponse = $pl['text'];
                } elseif (is_string($pl)) {
                    $assignmentStudentResponse = $pl;
                }
                break;
            }
        }

        $assignmentSessionContext = null;
        if ($isAssignmentSession && $exam !== null) {
            $assignmentSessionContext = [
                'instructions' => (string) ($exam->description ?? ''),
                'due_at' => $exam->due_at,
                'grades_released' => $exam->grades_released_at !== null,
                'submitted_late' => (bool) $examSession->submitted_late,
                'allows_text' => (bool) ($exam->assignment_allows_text ?? true),
                'allows_files' => (bool) ($exam->assignment_allows_files ?? false),
                'attachment_required' => (bool) ($exam->assignment_attachment_required ?? false),
                'disable_paste' => (bool) ($exam->assignment_disable_paste ?? true),
            ];
        }

        return view('examiner.exam_sessions.show', [
            'session' => $examSession,
            'workflowStatus' => $workflowStatus,
            'timeline' => $timeline,
            'thumbnails' => $thumbnails,
            'isHeld' => $workflowStatus === 'held',
            'canManageResults' => $canManageResults,
            'verificationEvidenceUrl' => $verificationEvidenceUrl,
            'releaseUrl' => route('exam-sessions.review.release', $examSession),
            'confirmFailUrl' => route('exam-sessions.review.confirm-fail', $examSession),
            'overrideUrl' => route('exam-sessions.review.override', $examSession),
            'invalidateForRetakeUrl' => $canManageResults
                ? route('examiner.exam-sessions.invalidate-for-retake', $examSession)
                : null,
            'classResultsUrl' => $classResultsUrl,
            'isAssignmentSession' => $isAssignmentSession,
            'assignmentSessionContext' => $assignmentSessionContext,
            'assignmentSubmissionFiles' => $assignmentSubmissionFiles,
            'assignmentStudentResponse' => $assignmentStudentResponse,
            'assignmentPasteAttemptCount' => $assignmentPasteAttemptCount,
        ]);
    }

    public function downloadAssignmentSubmission(
        Request $request,
        ExamSession $examSession,
        AssignmentSubmissionFile $assignmentFile,
        SensitiveStorageService $sensitiveStorage,
    ): StreamedResponse {
        $this->authorize('view', $examSession);
        abort_unless((int) $assignmentFile->exam_session_id === (int) $examSession->id, 404);

        return $sensitiveStorage->downloadResponse($assignmentFile->stored_path, $assignmentFile->original_filename);
    }

    private function summarizeProctoringMetadata(array $metadata): string
    {
        $payload = $metadata['payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $parts = [];
        if (isset($payload['confidence']) && is_numeric($payload['confidence'])) {
            $parts[] = 'confidence '.round((float) $payload['confidence'], 3);
        }
        if (isset($payload['keys']) && is_string($payload['keys'])) {
            $parts[] = 'keys: '.$payload['keys'];
        }
        if (isset($payload['screen_count']) && is_numeric($payload['screen_count'])) {
            $parts[] = 'screens '.(int) $payload['screen_count'];
        }
        if (isset($payload['obstruction_signal']) && is_string($payload['obstruction_signal'])) {
            $parts[] = 'signal: '.$payload['obstruction_signal'];
        }
        if (isset($metadata['question_id']) && is_numeric($metadata['question_id'])) {
            $parts[] = 'question '.(int) $metadata['question_id'];
        }
        if (isset($metadata['action_type']) && is_string($metadata['action_type'])) {
            $parts[] = 'clipboard: '.$metadata['action_type'];
        }

        return $parts !== [] ? implode(' · ', array_slice($parts, 0, 5)) : '—';
    }

    private function scopedResultSubquery(Builder $sub, int $quizId, ?string $status = null): void
    {
        $sub->from('results')
            ->whereColumn('results.user_id', 'exam_sessions.student_id')
            ->where('results.quiz_id', $quizId)
            ->selectRaw('1');

        if ($status !== null) {
            $sub->where('results.status', $status);
        }
    }

    private function workflowDisplayStatus(ExamSession $session): string
    {
        if ($session->status !== 'submitted') {
            return 'in_progress';
        }

        $rowResult = $session->result;
        if ($rowResult === null) {
            return 'submitted';
        }

        return $rowResult->status;
    }

    private function assertClassLinkedToExamCourse(Classroom $classroom, int $courseId): void
    {
        $linked = $classroom->courses()->whereKey($courseId)->exists();
        if (! $linked) {
            throw new HttpException(403, 'Class is not linked to this exam course.');
        }
    }

    private function isAssignedExaminerForCourse(int $examinerId, int $courseId): bool
    {
        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $examinerId)
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->exists();
    }
}
