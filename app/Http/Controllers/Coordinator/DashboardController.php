<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AcademicResetSnapshot;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const TREND_DAYS = 14;

    public function index(): View
    {
        $coordinator = auth()->user();
        $universityId = (int) $coordinator->university_id;
        $activeYearId = AcademicYear::activeForUniversity($universityId)?->id;

        $departmentIds = $coordinator->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $studentCount = User::query()
            ->where('role', 'student')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->count();

        $studentsWithoutClass = User::query()
            ->where('role', 'student')
            ->whereNull('class_id')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->count();

        $activeProgramCount = Program::query()
            ->whereIn('department_id', $departmentIds)
            ->where('is_active', true)
            ->count();

        $programTotal = Program::query()
            ->whereIn('department_id', $departmentIds)
            ->count();

        $classCount = $this->scopedActiveClassesQuery($departmentIds, $activeYearId)->count();

        $activeCourseCount = Course::query()
            ->whereIn('department_id', $departmentIds)
            ->where('is_active', true)
            ->count();

        $courseTotal = Course::query()
            ->whereIn('department_id', $departmentIds)
            ->count();

        $assignedExaminersCount = (int) ExaminerCourseAssignment::query()
            ->where('is_active', true)
            ->whereHas('course', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->distinct()
            ->count('examiner_user_id');

        $coursesAssignedToClassesCount = DB::table('class_course')
            ->join('classes', 'classes.id', '=', 'class_course.class_id')
            ->join('programs', 'programs.id', '=', 'classes.program_id')
            ->whereIn('programs.department_id', $departmentIds)
            ->where('classes.is_active', true)
            ->when($activeYearId !== null, function ($q) use ($activeYearId): void {
                $q->where(function ($q2) use ($activeYearId): void {
                    $q2->whereNull('classes.academic_year_id')
                        ->orWhere('classes.academic_year_id', $activeYearId);
                });
            })
            ->count();

        $recentStudents = User::query()
            ->where('role', 'student')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'created_at']);

        $recentClasses = Classroom::query()
            ->whereHas('program', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'name', 'section', 'created_at', 'is_active']);

        $recentSnapshots = AcademicResetSnapshot::query()
            ->whereIn('department_id', $departmentIds)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'reset_type', 'created_at', 'applied_at']);

        $trendStart = $this->trendWindowStart();

        $studentAddsTrend = $this->countsPerDaySince(
            User::query()
                ->where('role', 'student')
                ->where('created_at', '>=', $trendStart)
                ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
                ->pluck('created_at'),
            self::TREND_DAYS,
        );

        $studentsWithoutClassAddsTrend = $this->countsPerDaySince(
            User::query()
                ->where('role', 'student')
                ->whereNull('class_id')
                ->where('created_at', '>=', $trendStart)
                ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
                ->pluck('created_at'),
            self::TREND_DAYS,
        );

        $classAddsTrend = $this->countsPerDaySince(
            Classroom::query()
                ->whereHas('program', fn ($q) => $q->whereIn('department_id', $departmentIds))
                ->where('created_at', '>=', $trendStart)
                ->pluck('created_at'),
            self::TREND_DAYS,
        );

        $programAddsTrend = $this->countsPerDaySince(
            Program::query()
                ->whereIn('department_id', $departmentIds)
                ->where('created_at', '>=', $trendStart)
                ->pluck('created_at'),
            self::TREND_DAYS,
        );

        $courseAddsTrend = $this->countsPerDaySince(
            Course::query()
                ->whereIn('department_id', $departmentIds)
                ->where('created_at', '>=', $trendStart)
                ->pluck('created_at'),
            self::TREND_DAYS,
        );

        $examinerAssignmentAddsTrend = $this->countsPerDaySince(
            ExaminerCourseAssignment::query()
                ->where('created_at', '>=', $trendStart)
                ->whereHas('course', fn ($q) => $q->whereIn('department_id', $departmentIds))
                ->pluck('created_at'),
            self::TREND_DAYS,
        );

        $classCourseAddsTrend = $this->countsPerDaySince(
            DB::table('class_course')
                ->join('classes', 'classes.id', '=', 'class_course.class_id')
                ->join('programs', 'programs.id', '=', 'classes.program_id')
                ->whereIn('programs.department_id', $departmentIds)
                ->where('class_course.created_at', '>=', $trendStart)
                ->pluck('class_course.created_at'),
            self::TREND_DAYS,
        );

        return view('coordinator.dashboard', [
            'studentCount' => $studentCount,
            'studentsWithoutClass' => $studentsWithoutClass,
            'activeProgramCount' => $activeProgramCount,
            'programTotal' => $programTotal,
            'classCount' => $classCount,
            'activeCourseCount' => $activeCourseCount,
            'courseTotal' => $courseTotal,
            'assignedExaminersCount' => $assignedExaminersCount,
            'coursesAssignedToClassesCount' => $coursesAssignedToClassesCount,
            'recentStudents' => $recentStudents,
            'recentClasses' => $recentClasses,
            'recentSnapshots' => $recentSnapshots,
            'trendDays' => self::TREND_DAYS,
            'metricTrends' => [
                'student-adds' => $studentAddsTrend,
                'students-without-class-adds' => $studentsWithoutClassAddsTrend,
                'class-adds' => $classAddsTrend,
                'program-adds' => $programAddsTrend,
                'course-adds' => $courseAddsTrend,
                'examiner-assignments' => $examinerAssignmentAddsTrend,
                'class-course-adds' => $classCourseAddsTrend,
            ],
        ]);
    }

    private function trendWindowStart(): Carbon
    {
        return now()
            ->timezone(config('app.timezone'))
            ->startOfDay()
            ->subDays(self::TREND_DAYS - 1);
    }

    /**
     * @param  iterable<\DateTimeInterface|Carbon|string|null>  $timestamps
     * @return list<int>
     */
    private function countsPerDaySince(iterable $timestamps, int $days): array
    {
        $tz = config('app.timezone');
        $start = now()->timezone($tz)->startOfDay()->subDays($days - 1);

        $indexByDate = [];
        for ($i = 0; $i < $days; $i++) {
            $indexByDate[$start->copy()->addDays($i)->format('Y-m-d')] = $i;
        }

        $counts = array_fill(0, $days, 0);

        foreach ($timestamps as $ts) {
            if ($ts === null) {
                continue;
            }
            $key = Carbon::parse($ts)->timezone($tz)->format('Y-m-d');
            if (isset($indexByDate[$key])) {
                $counts[$indexByDate[$key]]++;
            }
        }

        return $counts;
    }

    /**
     * @param  list<int>  $departmentIds
     */
    private function scopedActiveClassesQuery(array $departmentIds, ?int $activeYearId)
    {
        return Classroom::query()
            ->whereHas('program', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->where('is_active', true)
            ->when($activeYearId !== null, function ($q) use ($activeYearId): void {
                $q->where(function ($q2) use ($activeYearId): void {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $activeYearId);
                });
            });
    }
}
