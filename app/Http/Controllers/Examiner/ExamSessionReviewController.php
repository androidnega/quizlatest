<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\Quiz;
use App\Models\Result;
use App\Services\ResultFinalizationService;
use App\Services\SensitiveStorageService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExamSessionReviewController extends Controller
{
    private const RISK_STATES = ['normal', 'warning', 'suspicious', 'critical', 'locked'];

    /** @var list<string> */
    private const VIOLATION_EVENT_TYPES = ['tab_switch', 'phone_detected', 'face_missing', 'fullscreen_exit', 'essay_clipboard_attempt'];

    public function index(Request $request, Quiz $exam): View
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

        return view('examiner.exam_sessions.index', [
            'exam' => $exam,
            'sessions' => $sessions,
            'riskStates' => self::RISK_STATES,
            'analytics' => $analytics,
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
            'is_warning' => ($e->action_taken ?? '') === 'warn' || $e->flagged,
            'is_auto_submit' => ($e->action_taken ?? '') === 'autosubmit',
        ]);

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
        ]);
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
}
