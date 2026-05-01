<?php

use App\Models\ExamSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('exam-session.{sessionId}', function ($user, string $sessionId) {
    $session = ExamSession::query()->where('session_id', $sessionId)->first();
    if (! $session) {
        return false;
    }

    if ($user->role === 'student' && (int) $user->id === (int) $session->student_id) {
        return ['id' => $user->id];
    }

    if (in_array($user->role, ['admin', 'coordinator'], true)) {
        return ['id' => $user->id];
    }

    return false;
});
