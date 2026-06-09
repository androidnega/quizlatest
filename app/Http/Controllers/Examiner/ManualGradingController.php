<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSessionAnswer;
use App\Models\Quiz;
use App\Services\AssignmentEssayAiGradingService;
use App\Services\ResultFinalizationService;
use App\Services\SystemSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManualGradingController extends Controller
{
    public function __construct(
        private readonly ResultFinalizationService $resultFinalization,
    ) {}

    public function index(Request $request, SystemSettingsService $systemSettings): View
    {
        $examFilter = null;
        $examId = (int) $request->integer('exam');
        if ($examId > 0) {
            $examFilter = Quiz::query()->find($examId);
            if ($examFilter !== null) {
                $this->authorize('update', $examFilter);
            }
        }

        $query = $this->pendingEssayQuery($request);
        if ($examId > 0) {
            $query->whereHas('examSession', fn ($s) => $s->where('exam_id', $examId));
        }

        $answers = $query
            ->with(['question', 'examSession.student', 'examSession.exam'])
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        // AI assist is gated on the system "enable_ai" setting. The pending
        // queue is already assignment-only (see pendingEssayQuery() below),
        // so the only remaining checks are: AI is enabled, an assignment is
        // selected to scope the bulk action, and there's at least one
        // pending submission to process.
        $aiEnabled = $systemSettings->getBool('enable_ai', true);
        $aiAssistAvailable = $aiEnabled
            && $examFilter !== null
            && $answers->total() > 0;

        // When the queue is NOT filtered to one assignment, list every assignment
        // that currently has pending essays so the examiner can launch AI assist
        // straight from here. Previously the "AI assist" button only appeared
        // after the user filtered, which made the AI grading feature feel hidden.
        $assignmentsWithPending = [];
        if ($aiEnabled && $examFilter === null) {
            $assignmentsWithPending = $this->pendingEssayQuery($request)
                ->with('question.quiz')
                ->get(['exam_session_answers.id', 'exam_session_answers.exam_session_id', 'exam_session_answers.question_id'])
                ->groupBy(fn (ExamSessionAnswer $a) => (int) ($a->question?->quiz_id ?? 0))
                ->filter(fn ($_, $quizId) => $quizId > 0)
                ->map(function ($group, $quizId) {
                    $quiz = $group->first()?->question?->quiz;

                    return [
                        'quiz_id' => (int) $quizId,
                        'title' => (string) ($quiz?->title ?? __('Untitled assignment')),
                        'submissions' => $group->pluck('exam_session_id')->unique()->count(),
                        'answers' => $group->count(),
                    ];
                })
                ->sortByDesc('submissions')
                ->values()
                ->all();
        }

        // Flash-driven "Recently AI-drafted" panel. When the examiner just
        // ran AI assist on an assignment, the rows we graded are no longer
        // pending_manual (they're manual_graded), so they fall out of the
        // table above. We pull them back here so the examiner can see the
        // AI's draft, jump straight to review, and release. The session
        // payload is produced by ExamBuilderController::gradeAssignmentWithAi.
        $aiJustCompleted = session('ai_grade_just_completed');
        $aiJustGradedAnswers = collect();
        if (is_array($aiJustCompleted) && ! empty($aiJustCompleted['answer_ids'])) {
            $ids = array_values(array_filter(array_map(
                'intval',
                (array) $aiJustCompleted['answer_ids'],
            )));
            if ($ids !== []) {
                $aiJustGradedAnswers = ExamSessionAnswer::query()
                    ->whereIn('id', $ids)
                    ->with(['question', 'examSession.student', 'examSession.exam'])
                    ->get();
            }
        }

        return view('examiner.grading.index', [
            'answers' => $answers,
            'examFilter' => $examFilter,
            'aiEnabled' => $aiEnabled,
            'aiAssistAvailable' => $aiAssistAvailable,
            'assignmentsWithPending' => $assignmentsWithPending,
            'aiJustGradedAnswers' => $aiJustGradedAnswers,
            'aiJustCompletedMeta' => is_array($aiJustCompleted) ? $aiJustCompleted : null,
        ]);
    }

    public function show(Request $request, ExamSessionAnswer $answer, SystemSettingsService $systemSettings): View
    {
        $answer->load(['question.quiz.course', 'examSession.student']);
        $this->authorize('update', $answer->question->quiz);

        abort_unless($answer->question->isEssay(), 404);
        // Manual essay grading is assignment-only — never show legacy quiz/exam essays here.
        abort_unless((bool) $answer->question->quiz?->isAssignment(), 404);
        abort_unless(
            in_array($answer->evaluation_status, ['pending_manual', 'manual_graded'], true),
            404,
        );

        $aiEnabled = $systemSettings->getBool('enable_ai', true);
        // AI suggest is only useful on a typed answer that isn't already graded.
        $rawText = (string) ($answer->answer_payload['text'] ?? '');
        $hasTypedAnswer = trim(\App\Support\EssayAnswerHtml::toPlainText($rawText)) !== '';
        $aiSuggestAvailable = $aiEnabled
            && $hasTypedAnswer
            && $answer->evaluation_status === 'pending_manual';

        return view('examiner.grading.show', [
            'answer' => $answer,
            'aiEnabled' => $aiEnabled,
            'aiSuggestAvailable' => $aiSuggestAvailable,
        ]);
    }

    /**
     * Run AI grading on a single pending essay answer. The grade is committed via
     * the existing AI service (status -> manual_graded), but the examiner can
     * immediately review/override from the same Grade screen.
     */
    public function aiAssistAnswer(
        Request $request,
        ExamSessionAnswer $answer,
        AssignmentEssayAiGradingService $aiGrading,
        SystemSettingsService $systemSettings,
    ): RedirectResponse {
        $answer->load(['question.quiz', 'examSession']);
        $this->authorize('update', $answer->question->quiz);

        abort_unless($answer->question?->isEssay(), 404);
        abort_unless((bool) $answer->question->quiz?->isAssignment(), 404);
        abort_unless($answer->evaluation_status === 'pending_manual', 422);

        if (! $systemSettings->getBool('enable_ai', true)) {
            return back()->withErrors([
                'ai' => __('AI is disabled for this institution.'),
            ]);
        }

        try {
            $aiGrading->applyAiGrade($answer, $request->user(), $answer->question->quiz);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors([
                'ai' => __('AI suggestion failed: :msg', ['msg' => $e->getMessage()]),
            ]);
        }

        return redirect()
            ->route('examiner.grading.show', $answer->fresh())
            ->with('status', __('AI suggested a grade — review the marks and feedback, then override if you disagree.'));
    }

    public function grade(Request $request, ExamSessionAnswer $answer): RedirectResponse
    {
        $answer->load(['question.quiz', 'examSession']);
        $this->authorize('update', $answer->question->quiz);

        abort_unless($answer->question->isEssay(), 404);

        $isOverride = $answer->evaluation_status === 'manual_graded';
        abort_unless(
            in_array($answer->evaluation_status, ['pending_manual', 'manual_graded'], true),
            422,
        );

        $max = (float) $answer->question->marks;

        $rules = [
            'points_awarded' => ['required', 'numeric', 'min:0', 'max:'.$max],
            'grader_feedback' => ['nullable', 'string', 'max:5000'],
        ];
        if ($isOverride) {
            $rules['override_reason'] = ['required', 'string', 'min:3', 'max:2000'];
        }

        $validated = $request->validate($rules);

        $prev = is_array($answer->evaluation_detail) ? $answer->evaluation_detail : [];
        $history = $prev['grading_history'] ?? [];
        $history[] = [
            'graded_at' => now()->toIso8601String(),
            'grader_id' => $request->user()->id,
            'points_awarded' => (float) $validated['points_awarded'],
            'grader_feedback' => $validated['grader_feedback'] ?? null,
            'override_reason' => $isOverride ? (string) $validated['override_reason'] : null,
            'action' => $isOverride ? 'override' : 'initial',
        ];

        $detail = array_merge($prev, [
            'graded' => true,
            'grading_history' => $history,
            'last_points_awarded' => (float) $validated['points_awarded'],
        ]);

        $answer->update([
            'points_awarded' => (float) $validated['points_awarded'],
            'evaluation_status' => 'manual_graded',
            'evaluation_detail' => $detail,
            'grader_feedback' => $validated['grader_feedback'] ?? null,
        ]);

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'quiz_id' => $answer->question->quiz_id,
            'event_type' => $this->manualGradeEventType($answer, $isOverride),
            'event_data' => [
                'exam_session_answer_id' => $answer->id,
                'exam_session_id' => $answer->exam_session_id,
                'points_awarded' => (float) $validated['points_awarded'],
                'override_reason' => $isOverride ? (string) $validated['override_reason'] : null,
            ],
            'created_at' => now(),
        ]);

        $session = $answer->examSession;
        if ($session !== null) {
            $this->resultFinalization->finalizeAfterManualGrading($session->fresh(['answers']), $request->user());
        }

        return redirect()
            ->route('examiner.grading.pending')
            ->with('status', 'Grade saved.');
    }

    private function manualGradeEventType(ExamSessionAnswer $answer, bool $isOverride): string
    {
        $quiz = $answer->question->quiz;
        if ($quiz !== null && $quiz->isAssignment()) {
            return $isOverride ? 'assignment_manual_grade_override' : 'assignment_manual_grade';
        }

        return $isOverride ? 'essay_manual_grade_override' : 'essay_manual_grade';
    }

    /**
     * @return Builder<ExamSessionAnswer>
     */
    private function pendingEssayQuery(Request $request)
    {
        $courseIds = $this->manageableCourseIds($request);
        $examinerId = (int) $request->user()->id;

        // Essays only exist on assignments now — quizzes/exams never need
        // manual essay grading. Restricting here also hides any legacy
        // essay rows that may have been imported into non-assignment pools.
        return ExamSessionAnswer::query()
            ->where('evaluation_status', 'pending_manual')
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->whereHas('examSession.exam', fn ($q) => $q
                ->where('assessment_type', 'assignment')
                ->whereIn('course_id', $courseIds)
                ->where('created_by', $examinerId));
    }

    /**
     * Essay grading queue is limited to courses where the user is an assigned examiner.
     *
     * @return array<int, int>
     */
    private function manageableCourseIds(Request $request): array
    {
        return ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $request->user()->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
