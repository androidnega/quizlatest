<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AcademicResetSnapshot;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExaminerCourseAssignment;
use App\Models\Level;
use App\Models\Program;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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

        $levelTotal = Level::query()
            ->where('university_id', $universityId)
            ->count();

        $levelActiveCount = Level::query()
            ->where('university_id', $universityId)
            ->where('is_active', true)
            ->count();

        $levelsReady = $levelTotal > 0 && $levelActiveCount === $levelTotal;

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

        $checklist = [
            [
                'label' => __('Students imported'),
                'ready' => $studentCount > 0,
            ],
            [
                'label' => __('Programs ready'),
                'ready' => $activeProgramCount > 0,
            ],
            [
                'label' => __('Levels ready'),
                'ready' => $levelsReady,
            ],
            [
                'label' => __('Classes ready'),
                'ready' => $classCount > 0,
            ],
            [
                'label' => __('Courses ready'),
                'ready' => $activeCourseCount > 0,
            ],
            [
                'label' => __('Courses assigned to classes'),
                'ready' => $coursesAssignedToClassesCount > 0,
            ],
            [
                'label' => __('Examiners assigned'),
                'ready' => $assignedExaminersCount > 0,
            ],
        ];

        $alerts = $this->buildAlerts(
            $studentsWithoutClass,
            $classCount,
            $activeCourseCount,
            $assignedExaminersCount,
            $coursesAssignedToClassesCount,
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
            'checklist' => $checklist,
            'alerts' => $alerts,
            'activeAcademicYear' => $activeYearId !== null,
        ]);
    }

    /**
     * @return list<array{message: string}>
     */
    private function buildAlerts(
        int $studentsWithoutClass,
        int $classCount,
        int $activeCourseCount,
        int $assignedExaminersCount,
        int $coursesAssignedToClassesCount,
    ): array {
        $alerts = [];

        if ($studentsWithoutClass > 0) {
            $alerts[] = ['message' => __('Some students are not yet in a class group.')];
        }

        if ($classCount === 0) {
            $alerts[] = ['message' => __('No active classes have been created yet.')];
        }

        if ($activeCourseCount === 0) {
            $alerts[] = ['message' => __('No courses are available yet.')];
        }

        if ($assignedExaminersCount === 0) {
            $alerts[] = ['message' => __('No examiners have been assigned yet.')];
        }

        if ($coursesAssignedToClassesCount === 0) {
            $alerts[] = ['message' => __('No courses have been linked to class groups yet.')];
        }

        return $alerts;
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
