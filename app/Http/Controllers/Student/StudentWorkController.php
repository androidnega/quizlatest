<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StudentWorkController extends Controller
{
    public function index(Request $request, DashboardController $dashboard): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'student', 403);

        return view('student.work.index', $dashboard->studentWorkData($user));
    }
}
