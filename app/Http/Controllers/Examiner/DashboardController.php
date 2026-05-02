<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Quiz;
use App\Models\Result;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quiz::class);

        $user = $request->user();
        $manageableCourseIds = $this->manageableCourseIds($request);

        $assignedCourses = Course::query()
            ->whereIn('id', ExaminerCourseAssignment::query()
                ->where('examiner_user_id', $user->id)
                ->where('is_active', true)
                ->pluck('course_id'))
            ->orderBy('title')
            ->get(['id', 'title', 'code']);

        $examQuery = Quiz::query()->whereIn('course_id', $manageableCourseIds);

        $draftExamCount = (clone $examQuery)->where('status', 'draft')->count();
        $publishedExamCount = (clone $examQuery)->where('status', 'published')->count();

        $heldResultsCount = Result::query()
            ->where('status', 'held')
            ->whereHas('quiz', fn ($q) => $q->whereIn('course_id', $manageableCourseIds))
            ->count();

        $pendingManualGradingCount = ExamSessionAnswer::query()
            ->where('evaluation_status', 'pending_manual')
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->whereHas('examSession.exam', fn ($q) => $q->whereIn('course_id', $manageableCourseIds))
            ->count();

        $flaggedSessions = ExamSession::query()
            ->where('status', 'flagged')
            ->whereHas('exam', fn ($q) => $q->whereIn('course_id', $manageableCourseIds))
            ->with([
                'exam:id,title,course_id',
                'exam.course:id,code,title',
                'student:id,name,index_number',
            ])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        return view('examiner.dashboard', [
            'assignedCourses' => $assignedCourses,
            'draftExamCount' => $draftExamCount,
            'publishedExamCount' => $publishedExamCount,
            'heldResultsCount' => $heldResultsCount,
            'pendingManualGradingCount' => $pendingManualGradingCount,
            'flaggedSessions' => $flaggedSessions,
            'manageableCourseCount' => count($manageableCourseIds),
        ]);
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
