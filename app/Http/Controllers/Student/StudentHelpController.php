<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class StudentHelpController extends Controller
{
    public function show(): View
    {
        abort_unless(auth()->user()?->role === 'student', 403);

        return view('student.help');
    }
}
