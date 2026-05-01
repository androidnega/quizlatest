<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ExaminerCourseAssignment;
use App\Models\Program;
use App\Models\Result;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $coordinator = auth()->user();
        $departmentIds = $coordinator->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id');

        $studentCount = Result::query()
            ->join('quizzes', 'results.quiz_id', '=', 'quizzes.id')
            ->join('courses', 'quizzes.course_id', '=', 'courses.id')
            ->join('users', 'results.user_id', '=', 'users.id')
            ->whereIn('courses.department_id', $departmentIds)
            ->where('users.role', 'student')
            ->distinct('results.user_id')
            ->count('results.user_id');

        $activeProgramCount = Program::query()
            ->whereIn('department_id', $departmentIds)
            ->where('is_active', true)
            ->count();

        $classCount = Classroom::query()
            ->join('programs', 'classes.program_id', '=', 'programs.id')
            ->whereIn('programs.department_id', $departmentIds)
            ->where('classes.is_active', true)
            ->count();

        $assignedCourseCount = ExaminerCourseAssignment::query()
            ->join('courses', 'examiner_course_assignments.course_id', '=', 'courses.id')
            ->where('examiner_course_assignments.examiner_user_id', $coordinator->id)
            ->where('examiner_course_assignments.is_active', true)
            ->whereIn('courses.department_id', $departmentIds)
            ->distinct('examiner_course_assignments.course_id')
            ->count('examiner_course_assignments.course_id');

        return view('coordinator.dashboard', [
            'studentCount' => $studentCount,
            'activeProgramCount' => $activeProgramCount,
            'classCount' => $classCount,
            'assignedCourseCount' => $assignedCourseCount,
        ]);
    }
}
