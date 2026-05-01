<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ExaminerCourseAssignment;
use App\Models\Program;
use App\Models\University;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $stats = [];

        if ($user->role === 'admin') {
            $stats = [
                'universities' => University::query()->count(),
                'coordinators' => User::query()->where('role', 'coordinator')->count(),
                'students' => User::query()->where('role', 'student')->count(),
            ];
        }

        if ($user->role === 'coordinator') {
            $departmentIds = $user->coordinatorAssignments()
                ->where('is_active', true)
                ->pluck('department_id');

            $stats = [
                'students' => User::query()
                    ->join('programs', 'users.program_id', '=', 'programs.id')
                    ->whereIn('programs.department_id', $departmentIds)
                    ->where('users.role', 'student')
                    ->distinct('users.id')
                    ->count('users.id'),
                'programs' => Program::query()
                    ->whereIn('department_id', $departmentIds)
                    ->where('is_active', true)
                    ->count(),
                'classes' => Classroom::query()
                    ->join('programs', 'classes.program_id', '=', 'programs.id')
                    ->whereIn('programs.department_id', $departmentIds)
                    ->where('classes.is_active', true)
                    ->count(),
                'assigned_courses' => ExaminerCourseAssignment::query()
                    ->join('courses', 'examiner_course_assignments.course_id', '=', 'courses.id')
                    ->where('examiner_course_assignments.examiner_user_id', $user->id)
                    ->where('examiner_course_assignments.is_active', true)
                    ->whereIn('courses.department_id', $departmentIds)
                    ->distinct('examiner_course_assignments.course_id')
                    ->count('examiner_course_assignments.course_id'),
            ];
        }

        if ($user->role === 'student') {
            $stats = [
                'program' => $user->program?->name,
                'level' => $user->level?->name,
                'results_published' => $user->results()->where('status', 'published')->count(),
            ];
        }

        return view('dashboard', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }
}
