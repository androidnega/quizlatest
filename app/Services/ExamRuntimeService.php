<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Quiz;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exam runtime primitives backed entirely by the Laravel cache store,
 * the RateLimiter, and the database. Used for session-start locks,
 * rate limiting, exam config caching, and live session counters.
 */
final class ExamRuntimeService
{
    public function acquireSessionStartLock(int $studentId, int $examId): bool
    {
        if (! $this->validId($studentId) || ! $this->validId($examId)) {
            return false;
        }

        $key = $this->sessionLockKey($studentId, $examId);
        $ttl = max(1, (int) config('exam_runtime.session_lock_ttl_seconds', 60));

        return Cache::add($key, '1', $ttl);
    }

    public function releaseSessionStartLock(int $studentId, int $examId): void
    {
        if (! $this->validId($studentId) || ! $this->validId($examId)) {
            return;
        }

        try {
            Cache::forget($this->sessionLockKey($studentId, $examId));
        } catch (\Throwable) {
            //
        }
    }

    /**
     * @throws HttpException
     */
    public function enforceExamStartRateLimit(int $studentId): void
    {
        if (! $this->validId($studentId)) {
            return;
        }

        $window = max(1, (int) config('exam_runtime.exam_start_window_seconds', 60));
        $max = max(1, (int) config('exam_runtime.exam_start_max_attempts', 30));
        $rlKey = 'exam_start_attempts:'.$studentId;

        if (! RateLimiter::attempt($rlKey, $max, fn () => true, $window)) {
            abort(429, 'Too many exam start attempts. Try again shortly.');
        }
    }

    /**
     * @throws HttpException
     */
    public function enforceOtpSendRateLimit(int $studentId): void
    {
        if (! $this->validId($studentId)) {
            return;
        }

        $window = max(1, (int) config('exam_otp.send_window_seconds', 600));
        $max = max(1, (int) config('exam_otp.max_send_per_window', 5));
        $rlKey = 'exam_otp_send_rate:'.$studentId;

        if (! RateLimiter::attempt($rlKey, $max, fn () => true, $window)) {
            abort(429, 'Too many verification codes requested. Try again later.');
        }
    }

    /**
     * @throws HttpException
     */
    public function enforceProctoringEventBudget(string $sessionId, int $eventCount = 1): void
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || ! Str::isUuid($sessionId)) {
            abort(422, 'Invalid session identifier.');
        }

        $eventCount = max(1, min(100, $eventCount));
        $window = max(1, (int) config('exam_runtime.proctoring_events_window_seconds', 60));
        $max = max(1, (int) config('exam_runtime.proctoring_events_max_per_window', 200));
        $key = 'proctoring_event_flood:'.$sessionId;

        for ($i = 0; $i < $eventCount; $i++) {
            if (! RateLimiter::attempt($key, $max, fn () => true, $window)) {
                abort(429, 'Too many proctoring events. Slow down and try again.');
            }
        }
    }

    public function rememberQuiz(int $examId, \Closure $loader): Quiz
    {
        if (! $this->validId($examId)) {
            return $loader();
        }

        $cacheKey = 'qs_exam_config:'.$examId;
        $ttl = max(60, (int) config('exam_runtime.exam_config_ttl_seconds', 480));

        $raw = Cache::get($cacheKey);
        if (is_array($raw) && isset($raw['id']) && (int) $raw['id'] === $examId) {
            return $this->quizFromCacheRow($raw);
        }
        if ($raw instanceof Quiz) {
            return $raw;
        }
        if ($raw !== null) {
            Log::warning('exam_cache.quiz_config_unusable', [
                'exam_id' => $examId,
                'type' => get_debug_type($raw),
            ]);
            Cache::forget($cacheKey);
        }

        $quiz = $loader();
        Cache::put($cacheKey, $this->quizToRow($quiz), $ttl);

        return $quiz;
    }

    public function forgetExamConfig(int $examId): void
    {
        if (! $this->validId($examId)) {
            return;
        }

        try {
            Cache::forget('qs_exam_config:'.$examId);
        } catch (\Throwable) {
            //
        }
    }

    public function incrementActiveSessions(int $examId): void
    {
        // No-op: with the database snapshot we don't maintain a separate
        // counter — activeSessionCountSnapshot() reads exam_sessions
        // directly. Kept on the API for backward compatibility.
        unset($examId);
    }

    public function decrementActiveSessions(int $examId): void
    {
        // See incrementActiveSessions.
        unset($examId);
    }

    /**
     * @deprecated Use activeSessionCountSnapshot for admin health.
     */
    public function getGlobalActiveSessionCount(): int
    {
        $snap = $this->activeSessionCountSnapshot();

        return is_int($snap['value']) ? $snap['value'] : 0;
    }

    /**
     * @return array{value: ?int, source: string}
     */
    public function activeSessionCountSnapshot(): array
    {
        return [
            'value' => ExamSession::query()->whereIn('status', ['active', 'paused'])->count(),
            'source' => 'database',
        ];
    }

    private function sessionLockKey(int $studentId, int $examId): string
    {
        return 'exam_session_lock:'.$studentId.':'.$examId;
    }

    private function validId(int $id): bool
    {
        return $id > 0 && $id < 2_147_483_647;
    }

    /**
     * @return array<string, mixed>
     */
    private function quizToRow(Quiz $quiz): array
    {
        return [
            'id' => (int) $quiz->id,
            'share_token' => filled($quiz->share_token) ? (string) $quiz->share_token : null,
            'university_id' => $quiz->university_id !== null ? (int) $quiz->university_id : null,
            'academic_year_id' => $quiz->academic_year_id !== null ? (int) $quiz->academic_year_id : null,
            'term_id' => $quiz->term_id !== null ? (int) $quiz->term_id : null,
            'course_id' => $quiz->course_id !== null ? (int) $quiz->course_id : null,
            'created_by' => $quiz->created_by !== null ? (int) $quiz->created_by : null,
            'title' => (string) $quiz->title,
            'description' => $quiz->description,
            'assessment_type' => $quiz->assessment_type,
            'selected_question_types' => is_array($quiz->selected_question_types) ? $quiz->selected_question_types : null,
            'status' => $quiz->status,
            'published_at' => $quiz->published_at?->toAtomString(),
            'duration_minutes' => (int) ($quiz->duration_minutes ?? 0),
            'total_marks' => $quiz->total_marks,
            'questions_per_student' => $quiz->questions_per_student,
            'randomize_questions' => (bool) ($quiz->randomize_questions ?? false),
            'randomize_options' => (bool) ($quiz->randomize_options ?? false),
            'proctoring_settings' => is_array($quiz->proctoring_settings) ? $quiz->proctoring_settings : [],
            'start_time' => $quiz->start_time?->toAtomString(),
            'end_time' => $quiz->end_time?->toAtomString(),
            'due_at' => $quiz->due_at?->toAtomString(),
            'grades_released_at' => $quiz->grades_released_at?->toAtomString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function quizFromCacheRow(array $row): Quiz
    {
        $quiz = new Quiz;
        $quiz->forceFill([
            'id' => (int) $row['id'],
            'share_token' => $row['share_token'] ?? null,
            'university_id' => $row['university_id'] ?? null,
            'academic_year_id' => $row['academic_year_id'] ?? null,
            'term_id' => $row['term_id'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'title' => (string) ($row['title'] ?? ''),
            'description' => $row['description'] ?? null,
            'assessment_type' => $row['assessment_type'] ?? 'quiz',
            'selected_question_types' => $row['selected_question_types'] ?? null,
            'status' => $row['status'] ?? 'draft',
            'published_at' => $row['published_at'] ?? null,
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'total_marks' => $row['total_marks'] ?? 0,
            'questions_per_student' => $row['questions_per_student'] ?? null,
            'randomize_questions' => (bool) ($row['randomize_questions'] ?? false),
            'randomize_options' => (bool) ($row['randomize_options'] ?? false),
            'proctoring_settings' => is_array($row['proctoring_settings'] ?? null) ? $row['proctoring_settings'] : [],
            'start_time' => $row['start_time'] ?? $row['available_from'] ?? null,
            'end_time' => $row['end_time'] ?? $row['available_to'] ?? null,
            'due_at' => $row['due_at'] ?? null,
            'grades_released_at' => $row['grades_released_at'] ?? null,
        ]);
        $quiz->exists = true;
        $quiz->syncOriginal();

        return $quiz;
    }
}
