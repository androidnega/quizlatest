<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Support\FaceEmbeddingComparator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamSessionController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:quizzes,id'],
            'face_embedding' => ['required', 'array', 'min:3'],
            'face_embedding.*' => ['numeric'],
            'face_retry_attempt' => ['nullable', 'integer', 'min:0', 'max:1'],
        ]);

        $student = $request->user();
        abort_unless($student && $student->role === 'student', 403);
        abort_unless($student->class_id !== null, 422, 'Student must be assigned to class.');

        $exam = Quiz::query()->findOrFail($validated['exam_id']);
        $threshold = 60.0;
        $template = is_array($student->face_embedding) ? $student->face_embedding : [];
        $similarity = FaceEmbeddingComparator::similarityPercent($template, $validated['face_embedding']);
        $retryAttempt = (int) ($validated['face_retry_attempt'] ?? 0);
        abort_unless(! empty($template), 422, 'Face template not enrolled.');
        abort_unless(
            $similarity >= $threshold || $retryAttempt === 0,
            422,
            'Face verification failed. Retry once.',
        );
        abort_unless($similarity >= $threshold, 422, 'Face verification failed. Exam start blocked.');

        $classHasExamCourse = DB::table('class_course')
            ->where('class_id', $student->class_id)
            ->where('course_id', $exam->course_id)
            ->exists();
        abort_unless($classHasExamCourse, 422, 'Exam is not assigned to your class.');

        $session = DB::transaction(function () use ($student, $exam) {
            $existingSubmitted = ExamSession::query()
                ->where('student_id', $student->id)
                ->where('exam_id', $exam->id)
                ->where('status', 'submitted')
                ->lockForUpdate()
                ->exists();
            abort_unless(! $existingSubmitted, 422, 'Re-entry is not allowed after submission.');

            $activeSessionExists = ExamSession::query()
                ->where('student_id', $student->id)
                ->whereIn('status', ['active', 'paused'])
                ->lockForUpdate()
                ->exists();
            abort_unless(! $activeSessionExists, 422, 'Another active session already exists.');

            return ExamSession::create([
                'student_id' => $student->id,
                'class_id' => $student->class_id,
                'exam_id' => $exam->id,
                'session_id' => (string) Str::uuid(),
                'status' => 'active',
                'start_time' => now(),
                'end_time' => null,
                'violation_count' => 0,
                'violation_score' => 0,
                'violation_events' => [],
                'last_event_time' => null,
                'risk_state' => 'normal',
                'exam_status' => 'active',
            ]);
        });

        return response()->json([
            'session_id' => $session->session_id,
            'status' => $session->status,
            'start_time' => $session->start_time?->toISOString(),
            'face_similarity' => $similarity,
        ]);
    }

    public function saveAnswer(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);
        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'answer_text' => ['nullable', 'string'],
            'answer_payload' => ['nullable', 'array'],
        ]);

        $questionBelongsToExam = Question::query()
            ->where('id', $validated['question_id'])
            ->where('quiz_id', $examSession->exam_id)
            ->exists();
        abort_unless($questionBelongsToExam, 422, 'Question does not belong to this exam.');

        ExamSessionAnswer::query()->updateOrCreate(
            [
                'exam_session_id' => $examSession->id,
                'question_id' => $validated['question_id'],
            ],
            [
                'answer_text' => $validated['answer_text'] ?? null,
                'answer_payload' => $validated['answer_payload'] ?? null,
                'saved_at' => now(),
            ],
        );

        return response()->json(['status' => 'saved']);
    }

    public function heartbeat(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);

        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        return response()->json(['status' => $examSession->status]);
    }

    public function logProctoringEvent(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);
        if ($this->autoExpireIfTimedOut($examSession)) {
            return response()->json(['status' => 'submitted', 'reason' => 'timeout']);
        }

        $validated = $request->validate([
            'event_type' => ['required', 'string', 'max:100'],
            'severity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'flagged' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);
        $scoreMap = [
            'tab_switch' => 5,
            'face_missing' => 10,
            'multiple_faces' => 25,
            'phone_detected' => 20,
            'fullscreen_exit' => 10,
        ];
        $eventType = $validated['event_type'];
        $scoreIncrease = $scoreMap[$eventType] ?? 0;

        $events = is_array($examSession->violation_events) ? $examSession->violation_events : [];
        $now = now();
        $lastSame = collect($events)->reverse()->first(fn ($event) => ($event['event_type'] ?? '') === $eventType);
        $cooldownSeconds = 45;
        $inCooldown = false;
        if (is_array($lastSame) && ! empty($lastSame['timestamp'])) {
            $inCooldown = $now->diffInSeconds($lastSame['timestamp']) < $cooldownSeconds;
        }
        if ($inCooldown) {
            $scoreIncrease = 0;
        }

        $newScore = ((int) $examSession->violation_score) + $scoreIncrease;
        $riskState = $this->resolveRiskState($newScore);
        $events[] = [
            'event_type' => $eventType,
            'score' => $scoreIncrease,
            'timestamp' => $now->toISOString(),
            'cooldown_applied' => $inCooldown,
        ];

        ProctoringEvent::create([
            'user_id' => $examSession->student_id,
            'quiz_id' => $examSession->exam_id,
            'event_type' => $eventType,
            'severity' => $validated['severity'] ?? 1,
            'flagged' => (bool) ($validated['flagged'] ?? false),
            'action_taken' => null,
            'metadata' => [
                'session_id' => $examSession->session_id,
                'student_id' => $examSession->student_id,
                'exam_id' => $examSession->exam_id,
                'risk_state' => $riskState,
                'payload' => $validated['metadata'] ?? [],
            ],
            'created_at' => $now,
        ]);

        $examSession->update([
            'violation_count' => (int) $examSession->violation_count + (($validated['flagged'] ?? false) ? 1 : 0),
            'violation_score' => $newScore,
            'violation_events' => $events,
            'last_event_time' => $now,
            'risk_state' => $riskState,
            'exam_status' => $newScore >= 50 ? 'flagged_for_review' : $examSession->exam_status,
        ]);

        if ($newScore >= 90) {
            $this->submitSession($examSession->fresh(), 'submitted_held', 'violation_threshold');

            return response()->json([
                'status' => 'submitted_held',
                'reason' => 'violation_threshold',
                'message' => 'Your exam has been submitted due to violation detection. Your result is under review. Please contact your lecturer.',
            ]);
        }

        return response()->json([
            'status' => 'logged',
            'violation_score' => $newScore,
            'risk_state' => $riskState,
        ]);
    }

    public function submit(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);
        $this->submitSession($examSession, 'submitted', 'manual_submit');

        return response()->json(['status' => 'submitted']);
    }

    public function forceSubmit(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $this->submitSession($examSession, 'submitted_held', 'force_submit');

        return response()->json(['status' => 'submitted_held', 'reason' => 'force_submit']);
    }

    public function reviewTimeline(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);

        $events = ProctoringEvent::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->where('metadata->session_id', $examSession->session_id)
            ->orderBy('created_at')
            ->get(['event_type', 'severity', 'flagged', 'metadata', 'created_at']);

        $capturedImages = $events->filter(function ($event) {
            return ! empty($event->metadata['payload']['file_path']);
        })->values();

        return response()->json([
            'session_id' => $examSession->session_id,
            'exam_status' => $examSession->exam_status,
            'risk_state' => $examSession->risk_state,
            'violation_score' => $examSession->violation_score,
            'events' => $events,
            'captured_images' => $capturedImages,
            'result' => Result::query()
                ->where('user_id', $examSession->student_id)
                ->where('quiz_id', $examSession->exam_id)
                ->first(['score', 'status', 'exam_status']),
        ]);
    }

    public function releaseHeldResult(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $this->applyReviewDecision($examSession, 'released', 'Result released after review.');

        return response()->json(['status' => 'released']);
    }

    public function confirmFail(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $this->applyReviewDecision($examSession, 'confirmed_fail', 'Result marked failed due to violations.');

        return response()->json(['status' => 'confirmed_fail']);
    }

    public function overrideDecision(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->applyReviewDecision($examSession, 'overridden', $validated['note'] ?? 'Decision overridden by reviewer.');

        return response()->json(['status' => 'overridden']);
    }

    private function authorizeStudentSession(Request $request, ExamSession $examSession): void
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'student', 403);
        abort_unless((int) $examSession->student_id === (int) $user->id, 403);
        abort_unless(in_array($examSession->status, ['active', 'paused'], true), 422, 'Session is not active.');
    }

    private function autoExpireIfTimedOut(ExamSession $examSession): bool
    {
        $durationMinutes = (int) ($examSession->exam?->duration_minutes ?? 0);
        if ($durationMinutes <= 0) {
            return false;
        }

        $expiresAt = $examSession->start_time?->copy()->addMinutes($durationMinutes);
        if ($expiresAt && now()->greaterThanOrEqualTo($expiresAt) && $examSession->status !== 'submitted') {
            $this->submitSession($examSession, 'submitted', 'timeout');

            return true;
        }

        return false;
    }

    private function submitSession(ExamSession $examSession, string $examStatus, string $reason): void
    {
        if ($examSession->status === 'submitted') {
            return;
        }

        $examSession->update([
            'status' => 'submitted',
            'end_time' => now(),
            'exam_status' => $examStatus,
            'risk_state' => $examStatus === 'submitted_held' ? 'locked' : $examSession->risk_state,
        ]);

        $timeTaken = max(0, $examSession->start_time?->diffInSeconds($examSession->end_time ?? now()) ?? 0);

        Result::query()->updateOrCreate(
            [
                'user_id' => $examSession->student_id,
                'quiz_id' => $examSession->exam_id,
            ],
            [
                'score' => 0,
                'time_taken' => $timeTaken,
                'status' => 'submitted',
                'exam_status' => $examStatus,
                'review_note' => $reason,
                'submitted_at' => now(),
            ],
        );
    }

    private function resolveRiskState(int $score): string
    {
        return match (true) {
            $score >= 90 => 'locked',
            $score >= 70 => 'critical',
            $score >= 50 => 'suspicious',
            $score >= 30 => 'warning',
            default => 'normal',
        };
    }

    private function applyReviewDecision(ExamSession $examSession, string $decision, string $note): void
    {
        $examSession->update([
            'exam_status' => 'reviewed',
            'risk_state' => 'suspicious',
        ]);

        Result::query()
            ->where('user_id', $examSession->student_id)
            ->where('quiz_id', $examSession->exam_id)
            ->update([
                'exam_status' => 'reviewed',
                'review_decision' => $decision,
                'review_note' => $note,
            ]);
    }
}
