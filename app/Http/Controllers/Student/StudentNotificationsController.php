<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentNoticeDigestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StudentNotificationsController extends Controller
{
    public function index(Request $request, StudentNoticeDigestService $notices): View
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'student', 403);

        $previousSeenAt = (string) $request->session()->get('student_notifications_seen_at', '');
        $previousSeenAt = $previousSeenAt !== '' ? $previousSeenAt : null;

        $rows = $notices->noticesFor($user, 40, $previousSeenAt);

        $request->session()->put(
            'student_notifications_seen_at',
            Carbon::now()->toIso8601String(),
        );

        $unreadCount = count(array_filter($rows, static fn (array $n): bool => (bool) ($n['is_unread'] ?? false)));

        return view('student.notifications.index', [
            'notices' => $rows,
            'unreadCount' => $unreadCount,
        ]);
    }
}
