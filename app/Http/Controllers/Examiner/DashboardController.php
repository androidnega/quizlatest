<?php

namespace App\Http\Controllers\Examiner;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassCourse;
use App\Models\ExaminerCourseAssignment;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ProctoringEvent;
use App\Models\Quiz;
use App\Models\Result;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
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
        $draftAssessmentsCount = (clone $examQuery)->where('status', 'draft')->count();
        $publishedAssessmentsCount = (clone $examQuery)->where('status', 'published')->count();

        $now = now();
        $activeAssessmentsCount = (clone $examQuery)->where('status', 'published')
            ->where(function ($q) use ($now): void {
                $q->where(function ($q2) use ($now): void {
                    $q2->whereNull('start_time')->orWhere('start_time', '<=', $now);
                })->where(function ($q2) use ($now): void {
                    $q2->whereNull('end_time')->orWhere('end_time', '>=', $now);
                });
            })
            ->count();

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

        $submittedSessionsCount = ExamSession::query()
            ->where('status', 'submitted')
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

        // Count of SUBMISSIONS (distinct exam sessions) waiting on essay grading,
        // not individual essay-answer rows. A single submission with multiple
        // essay sub-questions used to be counted N times here, which made the
        // pill on the dashboard appear inflated (e.g. "17" for one assignment
        // with several students each answering several essay parts).
        //
        // We also restrict to assessment_type=assignment so this matches the
        // grading queue (which is assignment-only — see ManualGradingController::pendingEssayQuery).
        $pendingManualGradingCount = ExamSessionAnswer::query()
            ->where('evaluation_status', 'pending_manual')
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->whereHas('examSession.exam', function ($q) use ($manageableCourseIds, $yearFilter, $user) {
                $q->where('assessment_type', 'assignment')
                    ->whereIn('course_id', $manageableCourseIds)
                    ->where('created_by', $user->id);
                if ($yearFilter > 0) {
                    $q->where(function ($q2) use ($yearFilter) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $yearFilter);
                    });
                }
            })
            ->distinct('exam_session_id')
            ->count('exam_session_id');

        $quizIds = Quiz::query()
            ->where('created_by', $user->id)
            ->whereIn('course_id', $manageableCourseIds)
            ->when($yearFilter > 0, function ($q) use ($yearFilter) {
                $q->where(function ($q2) use ($yearFilter) {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $yearFilter);
                });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $proctoringFlaggedSessionsCount = $quizIds === [] ? 0 : ExamSession::query()
            ->whereIn('exam_id', $quizIds)
            ->where(function ($q): void {
                $q->whereIn('risk_state', ['suspicious', 'critical', 'locked'])
                    ->orWhereExists(function (Builder $sub): void {
                        $sub->from('results')
                            ->whereColumn('results.user_id', 'exam_sessions.student_id')
                            ->whereColumn('results.quiz_id', 'exam_sessions.exam_id')
                            ->where('results.status', 'held')
                            ->selectRaw('1');
                    });
            })
            ->count();

        $autoSubmittedSessionsCount = $quizIds === [] ? 0 : ExamSession::query()
            ->whereIn('exam_id', $quizIds)
            ->whereNotNull('auto_submit_reason_code')
            ->count();

        $phoneDetectedEventsCount = $quizIds === [] ? 0 : ProctoringEvent::query()
            ->whereIn('quiz_id', $quizIds)
            ->where('event_type', 'phone_detected')
            ->count();

        $tabSwitchLimitSessionsCount = $quizIds === [] ? 0 : ExamSession::query()
            ->whereIn('exam_id', $quizIds)
            ->where('auto_submit_reason_code', 'tab_switch_limit')
            ->count();

        $assignmentsAwaitingGradingCount = $quizIds === [] ? 0 : Result::query()
            ->whereIn('quiz_id', $quizIds)
            ->whereHas('quiz', fn ($q) => $q->where('assessment_type', 'assignment'))
            ->whereIn('status', ['held', 'pending_manual'])
            ->count();

        $dashboardQueryBase = array_filter([
            'academic_year_id' => $yearFilter > 0 ? $yearFilter : null,
        ]);

        return view('examiner.dashboard', [
            'academicYears' => AcademicYear::query()
                ->where('university_id', $user->university_id)
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'is_active']),
            'selectedAcademicYearId' => $yearFilter > 0 ? $yearFilter : null,
            'quizTotalCount' => $quizTotalCount,
            'draftAssessmentsCount' => $draftAssessmentsCount,
            'publishedAssessmentsCount' => $publishedAssessmentsCount,
            'activeAssessmentsCount' => $activeAssessmentsCount,
            'sessionsCount' => $sessionsCount,
            'submittedSessionsCount' => $submittedSessionsCount,
            'resultsCount' => $resultsCount,
            'heldResultsCount' => $heldResultsCount,
            'pendingManualGradingCount' => $pendingManualGradingCount,
            'proctoringFlaggedSessionsCount' => $proctoringFlaggedSessionsCount,
            'autoSubmittedSessionsCount' => $autoSubmittedSessionsCount,
            'phoneDetectedEventsCount' => $phoneDetectedEventsCount,
            'tabSwitchLimitSessionsCount' => $tabSwitchLimitSessionsCount,
            'assignmentsAwaitingGradingCount' => $assignmentsAwaitingGradingCount,
            'dashboardProctoringQueryBase' => $dashboardQueryBase,
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
