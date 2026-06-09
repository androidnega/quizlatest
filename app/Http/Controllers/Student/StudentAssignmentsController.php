<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentAssignmentCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class StudentAssignmentsController extends Controller
{
    public function index(StudentAssignmentCatalogService $catalog): View
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'student', 403);

        $data = $catalog->catalogFor($user);

        $hasLinkedCourses = $user->class_id !== null
            && DB::table('class_course')->where('class_id', $user->class_id)->exists();

        return view('student.assignments.index', [
            'user' => $user,
            'assignments' => collect($data['courses']),
            'activeSession' => $data['activeSession'],
            'hasLinkedCourses' => $hasLinkedCourses,
            'summaryCourses' => collect($data['courses'])->count(),
            'summaryOpen' => $data['summaryOpen'],
            'summaryInProgress' => $data['summaryInProgress'],
            'summaryUpcoming' => $data['summaryUpcoming'],
            'summarySubmitted' => $data['summarySubmitted'],
            'summaryMissed' => $data['summaryMissed'],
        ]);
    }
}
