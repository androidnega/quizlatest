<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $coordinator = auth()->user();
        $activeYearId = AcademicYear::activeForUniversity((int) $coordinator->university_id)?->id;

        $departmentIds = $coordinator->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id');

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

        $classCount = Classroom::query()
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->where('is_active', true)
            ->when($activeYearId !== null, function ($q) use ($activeYearId) {
                $q->where(function ($q2) use ($activeYearId) {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $activeYearId);
                });
            })
            ->count();

        $courseCount = Course::query()
            ->whereIn('department_id', $departmentIds)
            ->count();

        $recentStudents = User::query()
            ->where('role', 'student')
            ->whereHas('program', fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'created_at']);

        return view('coordinator.dashboard', [
            'studentCount' => $studentCount,
            'studentsWithoutClass' => $studentsWithoutClass,
            'activeProgramCount' => $activeProgramCount,
            'programTotal' => $programTotal,
            'classCount' => $classCount,
            'courseCount' => $courseCount,
            'recentStudents' => $recentStudents,
        ]);
    }
}
