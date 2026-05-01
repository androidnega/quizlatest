<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentExamController extends Controller
{
    public function take(Request $request, ExamSession $examSession): View
    {
        abort_unless($request->user()?->role === 'student', 403);
        abort_unless((int) $examSession->student_id === (int) $request->user()->id, 403);

        return view('student.exam.take', [
            'examSession' => $examSession,
        ]);
    }
}
