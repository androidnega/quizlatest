<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSessionAnswer;
use App\Services\ResultFinalizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualGradingController extends Controller
{
    public function __construct(
        private readonly ResultFinalizationService $resultFinalization,
    ) {}

    public function index(Request $request): View
    {
        $answers = $this->pendingEssayQuery($request)
            ->with(['question', 'examSession.student', 'examSession'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('examiner.grading.index', [
            'answers' => $answers,
        ]);
    }

    public function show(Request $request, ExamSessionAnswer $answer): View
    {
        $answer->load(['question.quiz.course', 'examSession.student']);
        $this->authorize('update', $answer->question->quiz);

        abort_unless($answer->question->isEssay(), 404);
        abort_unless(
            in_array($answer->evaluation_status, ['pending_manual', 'manual_graded'], true),
            404,
        );

        return view('examiner.grading.show', [
            'answer' => $answer,
        ]);
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
            'event_type' => $isOverride ? 'essay_manual_grade_override' : 'essay_manual_grade',
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

    /**
     * @return Builder<ExamSessionAnswer>
     */
    private function pendingEssayQuery(Request $request)
    {
        $courseIds = $this->manageableCourseIds($request);
        $examinerId = (int) $request->user()->id;

        return ExamSessionAnswer::query()
            ->where('evaluation_status', 'pending_manual')
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->whereHas('examSession.exam', fn ($q) => $q
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
