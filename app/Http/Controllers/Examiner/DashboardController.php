<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassCourse;
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
        $assignedCoursesCount = count($manageableCourseIds);

        $classesInScopeCount = ClassCourse::query()
            ->whereIn('course_id', $manageableCourseIds)
            ->distinct('class_id')
            ->count('class_id');

        $yearFilter = (int) $request->integer('academic_year_id');
        if ($yearFilter <= 0) {
            $yearFilter = (int) (AcademicYear::activeForUniversity((int) $user->university_id)?->id ?? 0);
        }

        $examQuery = Quiz::query()
            ->where('created_by', $user->id)
            ->whereIn('course_id', $manageableCourseIds);
        if ($yearFilter > 0) {
            $examQuery->where(function ($q) use ($yearFilter) {
                $q->whereNull('academic_year_id')
                    ->orWhere('academic_year_id', $yearFilter);
            });
        }

        $quizTotalCount = (clone $examQuery)->count();

        $sessionsCount = ExamSession::query()
            ->whereHas('exam', function ($q) use ($manageableCourseIds, $yearFilter, $user) {
                $q->where('created_by', $user->id);
                $q->whereIn('course_id', $manageableCourseIds);
                if ($yearFilter > 0) {
                    $q->where(function ($q2) use ($yearFilter) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $yearFilter);
                    });
                }
            })
            ->count();

        $resultsCount = Result::query()
            ->whereHas('quiz', function ($q) use ($manageableCourseIds, $yearFilter, $user) {
                $q->where('created_by', $user->id);
                $q->whereIn('course_id', $manageableCourseIds);
                if ($yearFilter > 0) {
                    $q->where(function ($q2) use ($yearFilter) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $yearFilter);
                    });
                }
            })
            ->count();

        $heldResultsCount = Result::query()
            ->where('status', 'held')
            ->whereHas('quiz', function ($q) use ($manageableCourseIds, $yearFilter, $user) {
                $q->whereIn('course_id', $manageableCourseIds);
                $q->where('created_by', $user->id);
                if ($yearFilter > 0) {
                    $q->where(function ($q2) use ($yearFilter) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $yearFilter);
                    });
                }
            })
            ->count();

        $pendingManualGradingCount = ExamSessionAnswer::query()
            ->where('evaluation_status', 'pending_manual')
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->whereHas('examSession.exam', function ($q) use ($manageableCourseIds, $yearFilter, $user) {
                $q->whereIn('course_id', $manageableCourseIds);
                $q->where('created_by', $user->id);
                if ($yearFilter > 0) {
                    $q->where(function ($q2) use ($yearFilter) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $yearFilter);
                    });
                }
            })
            ->count();

        return view('examiner.dashboard', [
            'academicYears' => AcademicYear::query()
                ->where('university_id', $user->university_id)
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'is_active']),
            'selectedAcademicYearId' => $yearFilter > 0 ? $yearFilter : null,
            'quizTotalCount' => $quizTotalCount,
            'sessionsCount' => $sessionsCount,
            'resultsCount' => $resultsCount,
            'heldResultsCount' => $heldResultsCount,
            'pendingManualGradingCount' => $pendingManualGradingCount,
            'classesInScopeCount' => $classesInScopeCount,
            'assignedCoursesCount' => $assignedCoursesCount,
        ]);
    }

    /**
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
