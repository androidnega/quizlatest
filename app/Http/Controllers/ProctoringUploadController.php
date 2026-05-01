<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProctoringUploadController extends Controller
{
    public function createUploadPath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:100'],
            'event_type' => ['required', 'string', 'max:100'],
            'quiz_id' => ['required', 'integer', 'exists:quizzes,id'],
        ]);

        $sessionId = preg_replace('/[^A-Za-z0-9_-]/', '', $validated['session_id']) ?: 'default';
        $userId = (int) $request->user()->id;
        $session = ExamSession::query()
            ->where('session_id', $sessionId)
            ->where('student_id', $userId)
            ->where('exam_id', $validated['quiz_id'])
            ->whereIn('status', ['active', 'paused'])
            ->first();
        abort_unless($session, 422, 'Invalid exam session context.');

        $filename = now()->format('YmdHis').'_' . Str::random(10).'.jpg';
        $filePath = "proctoring/user_{$userId}/session_{$sessionId}/{$filename}";
        $token = Str::uuid()->toString();

        Cache::put($this->cacheKey($token), [
            'user_id' => $userId,
            'quiz_id' => $validated['quiz_id'],
            'session_id' => $sessionId,
            'event_type' => $validated['event_type'],
            'file_path' => $filePath,
            'exam_session_id' => $session->id,
            'uploaded' => false,
            'created_at' => now()->toISOString(),
        ], now()->addMinutes(15));

        return response()->json([
            'upload_token' => $token,
            'file_path' => $filePath,
            'upload_url' => route('proctoring.uploads.file', absolute: false),
            'metadata_url' => route('proctoring.uploads.metadata', absolute: false),
        ]);
    }

    public function uploadFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_token' => ['required', 'string'],
            'snapshot' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $payload = Cache::get($this->cacheKey($validated['upload_token']));
        abort_unless(is_array($payload), 422, 'Upload token expired.');
        abort_unless((int) $payload['user_id'] === (int) $request->user()->id, 403);

        Storage::disk('public')->put($payload['file_path'], file_get_contents($validated['snapshot']->getRealPath()));

        $payload['uploaded'] = true;
        $payload['uploaded_at'] = now()->toISOString();
        Cache::put($this->cacheKey($validated['upload_token']), $payload, now()->addMinutes(15));

        return response()->json([
            'status' => 'uploaded',
            'file_path' => $payload['file_path'],
        ]);
    }

    public function storeMetadata(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_token' => ['required', 'string'],
            'timestamp' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $payload = Cache::get($this->cacheKey($validated['upload_token']));
        abort_unless(is_array($payload), 422, 'Upload token expired.');
        abort_unless((int) $payload['user_id'] === (int) $request->user()->id, 403);
        abort_unless(($payload['uploaded'] ?? false) === true, 422, 'File must be uploaded before metadata.');

        ProctoringEvent::create([
            'user_id' => $request->user()->id,
            'quiz_id' => $payload['quiz_id'],
            'event_type' => $payload['event_type'],
            'severity' => 1,
            'flagged' => false,
            'action_taken' => null,
            'metadata' => [
                'file_path' => $payload['file_path'],
                'timestamp' => $validated['timestamp'] ?? now()->toISOString(),
                'event_type' => $payload['event_type'],
                'session_id' => $payload['session_id'],
                'student_id' => $request->user()->id,
                'exam_id' => $payload['quiz_id'],
                'extra' => $validated['metadata'] ?? [],
            ],
            'created_at' => now(),
        ]);

        Cache::forget($this->cacheKey($validated['upload_token']));

        return response()->json(['status' => 'metadata_saved']);
    }

    private function cacheKey(string $token): string
    {
        return 'proctoring_upload:'.$token;
    }
}
