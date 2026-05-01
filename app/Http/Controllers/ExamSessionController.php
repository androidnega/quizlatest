<?php

namespace App\Http\Controllers;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
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
            'face_match_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $student = $request->user();
        abort_unless($student && $student->role === 'student', 403);
        abort_unless($student->class_id !== null, 422, 'Student must be assigned to class.');

        $exam = Quiz::query()->findOrFail($validated['exam_id']);
        $threshold = (float) ($exam->proctoring_settings['face_match_threshold'] ?? 55);
        abort_unless((float) $validated['face_match_score'] >= $threshold, 422, 'Face verification below required threshold.');

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
            ]);
        });

        return response()->json([
            'session_id' => $session->session_id,
            'status' => $session->status,
            'start_time' => $session->start_time?->toISOString(),
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

        $exam = $examSession->exam;

        ProctoringEvent::create([
            'user_id' => $examSession->student_id,
            'quiz_id' => $examSession->exam_id,
            'event_type' => $validated['event_type'],
            'severity' => $validated['severity'] ?? 1,
            'flagged' => (bool) ($validated['flagged'] ?? false),
            'action_taken' => null,
            'metadata' => [
                'session_id' => $examSession->session_id,
                'student_id' => $examSession->student_id,
                'exam_id' => $examSession->exam_id,
                'payload' => $validated['metadata'] ?? [],
            ],
            'created_at' => now(),
        ]);

        if (($validated['flagged'] ?? false) === true) {
            $examSession->increment('violation_count');
        }

        $autosubmitEnabled = (bool) ($exam->proctoring_settings['violation_actions']['autosubmit'] ?? false);
        $threshold = (int) ($exam->proctoring_settings['tab_switch_limit'] ?? 3);
        if ($autosubmitEnabled && $examSession->violation_count >= $threshold) {
            $this->submitSession($examSession);

            return response()->json(['status' => 'submitted', 'reason' => 'violation_threshold']);
        }

        return response()->json(['status' => 'logged', 'violation_count' => $examSession->violation_count]);
    }

    public function submit(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->authorizeStudentSession($request, $examSession);
        $this->submitSession($examSession);

        return response()->json(['status' => 'submitted']);
    }

    public function forceSubmit(Request $request, ExamSession $examSession): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'coordinator'], true), 403);
        $this->submitSession($examSession);

        return response()->json(['status' => 'submitted', 'reason' => 'force_submit']);
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
            $this->submitSession($examSession);

            return true;
        }

        return false;
    }

    private function submitSession(ExamSession $examSession): void
    {
        if ($examSession->status === 'submitted') {
            return;
        }

        $examSession->update([
            'status' => 'submitted',
            'end_time' => now(),
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
                'submitted_at' => now(),
            ],
        );
    }
}
