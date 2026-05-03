<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Services\SensitiveStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SecureExamEvidenceController extends Controller
{
    public function verification(Request $request, ExamSession $examSession, SensitiveStorageService $storage): Response
    {
        $this->assertStaffEvidenceAccess($request, $examSession);

        $path = $examSession->verification_image_path;
        abort_if(! is_string($path) || $path === '', 404);
        abort_unless($storage->existsAnywhere($path), 404);

        return $storage->inlineImageResponse($path);
    }

    public function eventSnapshot(
        Request $request,
        ExamSession $examSession,
        ProctoringEvent $proctoringEvent,
        SensitiveStorageService $storage,
    ): Response {
        $this->assertStaffEvidenceAccess($request, $examSession);
        $this->assertEventBelongsToSession($examSession, $proctoringEvent);

        $meta = is_array($proctoringEvent->metadata) ? $proctoringEvent->metadata : [];
        $path = $meta['file_path'] ?? data_get($meta, 'payload.file_path');
        abort_if(! is_string($path) || $path === '', 404);
        abort_unless($storage->existsAnywhere($path), 404);

        return $storage->inlineImageResponse($path);
    }

    private function assertStaffEvidenceAccess(Request $request, ExamSession $examSession): void
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($user->role === 'coordinator', 403);
        Gate::authorize('view', $examSession);
    }

    private function assertEventBelongsToSession(ExamSession $examSession, ProctoringEvent $proctoringEvent): void
    {
        abort_unless((int) $proctoringEvent->user_id === (int) $examSession->student_id, 404);
        abort_unless((int) $proctoringEvent->quiz_id === (int) $examSession->exam_id, 404);

        $meta = is_array($proctoringEvent->metadata) ? $proctoringEvent->metadata : [];
        $sid = $meta['session_id'] ?? null;
        abort_unless(is_string($sid) && $sid === (string) $examSession->session_id, 404);
    }
}
