<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Course;
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

        return view('coordinator.grading.index', [
            'answers' => $answers,
        ]);
    }

    public function show(Request $request, ExamSessionAnswer $answer): View
    {
        $answer->load(['question.quiz.course', 'examSession.student']);
        $this->authorize('update', $answer->question->quiz);

        abort_unless($answer->question->isEssay(), 404);
        abort_unless($answer->evaluation_status === 'pending_manual', 404);

        return view('coordinator.grading.show', [
            'answer' => $answer,
        ]);
    }

    public function grade(Request $request, ExamSessionAnswer $answer): RedirectResponse
    {
        $answer->load(['question.quiz', 'examSession']);
        $this->authorize('update', $answer->question->quiz);

        abort_unless($answer->question->isEssay(), 404);
        abort_unless($answer->evaluation_status === 'pending_manual', 422);

        $max = (float) $answer->question->marks;

        $validated = $request->validate([
            'points_awarded' => ['required', 'numeric', 'min:0', 'max:'.$max],
            'grader_feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $answer->update([
            'points_awarded' => (float) $validated['points_awarded'],
            'evaluation_status' => 'manual_graded',
            'evaluation_detail' => ['graded' => true],
            'grader_feedback' => $validated['grader_feedback'] ?? null,
        ]);

        $session = $answer->examSession;
        if ($session !== null) {
            $this->resultFinalization->finalizeAfterManualGrading($session->fresh(['answers']), $request->user());
        }

        return redirect()
            ->route('coordinator.grading.pending')
            ->with('status', 'Grade saved.');
    }

    /**
     * @return Builder<ExamSessionAnswer>
     */
    private function pendingEssayQuery(Request $request)
    {
        $courseIds = $this->manageableCourseIds($request);

        return ExamSessionAnswer::query()
            ->where('evaluation_status', 'pending_manual')
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->whereHas('examSession.exam', fn ($q) => $q->whereIn('course_id', $courseIds));
    }

    /**
     * @return array<int, int>
     */
    private function coordinatorDepartmentIds(Request $request): array
    {
        return $request->user()
            ->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function manageableCourseIds(Request $request): array
    {
        $fromAssignments = ExaminerCourseAssignment::query()
            ->where('examiner_user_id', $request->user()->id)
            ->where('is_active', true)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fromDepartments = Course::query()
            ->whereIn('department_id', $this->coordinatorDepartmentIds($request))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($fromAssignments, $fromDepartments)));
    }
}
