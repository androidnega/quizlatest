<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentNoticeDigestService;
use Illuminate\Contracts\View\View;

class StudentNotificationsController extends Controller
{
    public function index(StudentNoticeDigestService $notices): View
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->role === 'student', 403);

        return view('student.notifications.index', [
            'notices' => $notices->noticesFor($user, 40),
        ]);
    }
}
