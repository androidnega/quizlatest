<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Quiz;
use App\Models\Result;
use App\Services\PracticeModuleSettings;
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

        $yearFilter = (int) $request->integer('academic_year_id');
        if ($yearFilter <= 0) {
            $yearFilter = (int) (AcademicYear::activeForUniversity((int) $user->university_id)?->id ?? 0);
        }

        $examQuery = Quiz::query()->whereIn('course_id', $manageableCourseIds);
        if ($yearFilter > 0) {
            $examQuery->where(function ($q) use ($yearFilter) {
                $q->whereNull('academic_year_id')
                    ->orWhere('academic_year_id', $yearFilter);
            });
        }

        $draftExamCount = (clone $examQuery)->where('status', 'draft')->count();
        $publishedExamCount = (clone $examQuery)->where('status', 'published')->count();

        $heldResultsCount = Result::query()
            ->where('status', 'held')
            ->whereHas('quiz', function ($q) use ($manageableCourseIds, $yearFilter) {
                $q->whereIn('course_id', $manageableCourseIds);
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
            ->whereHas('examSession.exam', function ($q) use ($manageableCourseIds, $yearFilter) {
                $q->whereIn('course_id', $manageableCourseIds);
                if ($yearFilter > 0) {
                    $q->where(function ($q2) use ($yearFilter) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $yearFilter);
                    });
                }
            })
            ->count();

        $flaggedSessions = ExamSession::query()
            ->where('status', 'flagged')
            ->whereHas('exam', function ($q) use ($manageableCourseIds, $yearFilter) {
                $q->whereIn('course_id', $manageableCourseIds);
                if ($yearFilter > 0) {
                    $q->where(function ($q2) use ($yearFilter) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $yearFilter);
                    });
                }
            })
            ->with([
                'exam:id,title,course_id',
                'exam.course:id,code,title',
                'student:id,name,index_number',
            ])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        $practice = app(PracticeModuleSettings::class);
        $firstManageableCourse = Course::query()
            ->whereIn('id', $manageableCourseIds)
            ->orderBy('code')
            ->first(['id', 'code', 'title']);

        return view('examiner.dashboard', [
            'assignedCourses' => $assignedCourses,
            'academicYears' => AcademicYear::query()
                ->where('university_id', $user->university_id)
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'is_active']),
            'selectedAcademicYearId' => $yearFilter > 0 ? $yearFilter : null,
            'draftExamCount' => $draftExamCount,
            'publishedExamCount' => $publishedExamCount,
            'heldResultsCount' => $heldResultsCount,
            'pendingManualGradingCount' => $pendingManualGradingCount,
            'flaggedSessions' => $flaggedSessions,
            'manageableCourseCount' => count($manageableCourseIds),
            'practiceOverviewEnabled' => $practice->examinerPracticeOverviewEnabled(),
            'materialUploadsEnabled' => $practice->courseMaterialUploadsEnabled(),
            'firstManageableCourse' => $firstManageableCourse,
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
