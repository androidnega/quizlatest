<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\Quiz;
use App\Models\Result;
use App\Services\ResultFinalizationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ExamSessionReviewController extends Controller
{
    private const RISK_STATES = ['normal', 'warning', 'suspicious', 'critical', 'locked'];

    public function index(Request $request, Quiz $exam): View
    {
        $this->authorize('view', $exam);

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

        return view('coordinator.exam_sessions.index', [
            'exam' => $exam,
            'sessions' => $sessions,
            'riskStates' => self::RISK_STATES,
        ]);
    }

    public function show(ExamSession $examSession): View
    {
        $this->authorize('view', $examSession);

        $examSession->load(['student', 'exam.course']);

        $result = Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->first();

        $examSession->setRelation('result', $result);

        if ($result !== null) {
            $this->authorize('view', $result);
        }

        $finalization = app(ResultFinalizationService::class);
        $workflowStatus = $finalization->resolveStatus($examSession);

        $events = ProctoringEvent::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->where('metadata->session_id', $examSession->session_id)
            ->orderBy('created_at')
            ->get(['event_type', 'severity', 'flagged', 'action_taken', 'created_at', 'metadata']);

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
            if (! Storage::disk('public')->exists($path)) {
                continue;
            }
            $thumbnails[] = [
                'url' => Storage::disk('public')->url($path),
                'event_type' => $e->event_type,
                'at' => $e->created_at,
            ];
        }

        return view('coordinator.exam_sessions.show', [
            'session' => $examSession,
            'workflowStatus' => $workflowStatus,
            'timeline' => $timeline,
            'thumbnails' => $thumbnails,
            'isHeld' => $workflowStatus === 'held',
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
