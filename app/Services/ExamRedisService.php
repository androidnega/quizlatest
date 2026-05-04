<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Quiz;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exam runtime primitives: Redis when enabled, otherwise Laravel cache / RateLimiter fallbacks (cPanel-friendly).
 */
final class ExamRedisService
{
    public function __construct(
        private readonly ExamRuntimeInfraGate $gate,
    ) {}

    public function acquireSessionStartLock(int $studentId, int $examId): bool
    {
        if (! $this->validId($studentId) || ! $this->validId($examId)) {
            return false;
        }

        $key = $this->sessionLockKey($studentId, $examId);
        $ttl = max(1, (int) config('exam_redis.session_lock_ttl_seconds', 60));

        if ($this->gate->useRedisForExamRuntime()) {
            try {
                $r = Redis::set($key, '1', 'EX', $ttl, 'NX');

                return $r === true || $r === 'OK' || $r === 1;
            } catch (\Throwable $e) {
                Log::warning('exam_redis.session_lock_failed', ['error' => $e->getMessage()]);
                if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
                    return Cache::add($key, '1', $ttl);
                }

                return true;
            }
        }

        if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
            return Cache::add($key, '1', $ttl);
        }

        Log::warning('exam_runtime.session_lock_skipped', ['reason' => 'no_redis_no_fallback']);

        return true;
    }

    public function releaseSessionStartLock(int $studentId, int $examId): void
    {
        if (! $this->validId($studentId) || ! $this->validId($examId)) {
            return;
        }

        $key = $this->sessionLockKey($studentId, $examId);

        if ($this->gate->useRedisForExamRuntime()) {
            try {
                Redis::del($key);
            } catch (\Throwable $e) {
                Log::warning('exam_redis.session_lock_release_failed', ['error' => $e->getMessage()]);
            }
        }

        try {
            Cache::forget($key);
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

        $window = max(1, (int) config('exam_redis.exam_start_window_seconds', 60));
        $max = max(1, (int) config('exam_redis.exam_start_max_attempts', 30));
        $rlKey = 'exam_start_attempts:'.$studentId;

        if ($this->gate->useRedisForExamRuntime()) {
            try {
                $n = (int) Redis::incr($rlKey);
                if ($n === 1) {
                    Redis::expire($rlKey, $window);
                }
                if ($n > $max) {
                    abort(429, 'Too many exam start attempts. Try again shortly.');
                }

                return;
            } catch (HttpException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('exam_redis.start_rate_failed', ['error' => $e->getMessage()]);
                if (! $this->gate->useCacheBackedExamRuntimeFallbacks()) {
                    return;
                }
            }
        }

        if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
            if (! RateLimiter::attempt($rlKey, $max, fn () => true, $window)) {
                abort(429, 'Too many exam start attempts. Try again shortly.');
            }

            return;
        }

        Log::warning('exam_runtime.start_rate_skipped', ['reason' => 'no_redis_no_fallback']);
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

        if ($this->gate->useRedisForExamRuntime()) {
            try {
                $n = (int) Redis::incr($rlKey);
                if ($n === 1) {
                    Redis::expire($rlKey, $window);
                }
                if ($n > $max) {
                    abort(429, 'Too many verification codes requested. Try again later.');
                }

                return;
            } catch (HttpException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('exam_redis.otp_send_rate_failed', ['error' => $e->getMessage()]);
                if (! $this->gate->useCacheBackedExamRuntimeFallbacks()) {
                    return;
                }
            }
        }

        if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
            if (! RateLimiter::attempt($rlKey, $max, fn () => true, $window)) {
                abort(429, 'Too many verification codes requested. Try again later.');
            }

            return;
        }

        Log::warning('exam_runtime.otp_send_rate_skipped', ['reason' => 'no_redis_no_fallback']);
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
        $window = max(1, (int) config('exam_redis.proctoring_events_window_seconds', 60));
        $max = max(1, (int) config('exam_redis.proctoring_events_max_per_window', 200));
        $key = 'proctoring_event_flood:'.$sessionId;

        if ($this->gate->useRedisForExamRuntime()) {
            try {
                $n = 0;
                for ($i = 0; $i < $eventCount; $i++) {
                    $n = (int) Redis::incr($key);
                    if ($n === 1) {
                        Redis::expire($key, $window);
                    }
                }
                if ($n > $max) {
                    abort(429, 'Too many proctoring events. Slow down and try again.');
                }

                return;
            } catch (HttpException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('exam_redis.proctoring_flood_failed', ['error' => $e->getMessage()]);
                if (! $this->gate->useCacheBackedExamRuntimeFallbacks()) {
                    return;
                }
            }
        }

        if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
            for ($i = 0; $i < $eventCount; $i++) {
                if (! RateLimiter::attempt($key, $max, fn () => true, $window)) {
                    abort(429, 'Too many proctoring events. Slow down and try again.');
                }
            }

            return;
        }

        Log::warning('exam_runtime.proctoring_flood_skipped', ['reason' => 'no_redis_no_fallback']);
    }

    public function rememberQuiz(int $examId, \Closure $loader): Quiz
    {
        if (! $this->validId($examId)) {
            return $loader();
        }

        $key = 'exam_config:'.$examId;
        $cacheKey = 'qs_exam_config:'.$examId;
        $ttl = max(60, (int) config('exam_redis.exam_config_ttl_seconds', 480));

        if ($this->gate->useRedisForExamRuntime()) {
            try {
                $raw = Redis::get($key);
                if (is_string($raw) && $raw !== '') {
                    /** @var array<string, mixed>|null $row */
                    $row = json_decode($raw, true);
                    if (is_array($row) && isset($row['id']) && (int) $row['id'] === $examId) {
                        return $this->quizFromCacheRow($row);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('exam_redis.exam_config_read_failed', ['error' => $e->getMessage()]);
            }

            /** @var Quiz $quiz */
            $quiz = $loader();

            try {
                Redis::setex($key, $ttl, json_encode($this->quizToRow($quiz), JSON_THROW_ON_ERROR));
            } catch (\Throwable $e) {
                Log::warning('exam_redis.exam_config_write_failed', ['error' => $e->getMessage()]);
                if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
                    Cache::put($cacheKey, $quiz, $ttl);
                }
            }

            return $quiz;
        }

        if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
            /** @var Quiz $cached */
            $cached = Cache::remember($cacheKey, $ttl, $loader);

            return $cached;
        }

        return $loader();
    }

    public function forgetExamConfig(int $examId): void
    {
        if (! $this->validId($examId)) {
            return;
        }

        if ($this->gate->useRedisForExamRuntime()) {
            try {
                Redis::del('exam_config:'.$examId);
            } catch (\Throwable $e) {
                Log::warning('exam_redis.exam_config_forget_failed', ['error' => $e->getMessage()]);
            }
        }

        try {
            Cache::forget('qs_exam_config:'.$examId);
        } catch (\Throwable) {
            //
        }
    }

    public function incrementActiveSessions(int $examId): void
    {
        if (! $this->validId($examId)) {
            return;
        }

        if (! $this->gate->useRedisForExamRuntime()) {
            return;
        }

        try {
            Redis::incr('qs:exam_active_sessions:global');
            Redis::incr('qs:exam_active_sessions:exam:'.$examId);
        } catch (\Throwable $e) {
            Log::warning('exam_redis.active_incr_failed', ['error' => $e->getMessage()]);
        }
    }

    public function decrementActiveSessions(int $examId): void
    {
        if (! $this->validId($examId)) {
            return;
        }

        if (! $this->gate->useRedisForExamRuntime()) {
            return;
        }

        try {
            $this->decrNonNegative('qs:exam_active_sessions:global');
            $this->decrNonNegative('qs:exam_active_sessions:exam:'.$examId);
        } catch (\Throwable $e) {
            Log::warning('exam_redis.active_decr_failed', ['error' => $e->getMessage()]);
        }
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
        if ($this->gate->useRedisForExamRuntime()) {
            try {
                $v = Redis::get('qs:exam_active_sessions:global');

                return [
                    'value' => is_numeric($v) ? max(0, (int) $v) : 0,
                    'source' => 'redis',
                ];
            } catch (\Throwable $e) {
                Log::warning('exam_redis.active_count_read_failed', ['error' => $e->getMessage()]);
            }
        }

        if ($this->gate->useCacheBackedExamRuntimeFallbacks()) {
            return [
                'value' => ExamSession::query()->whereIn('status', ['active', 'paused'])->count(),
                'source' => 'database_estimate',
            ];
        }

        return [
            'value' => null,
            'source' => 'unavailable',
        ];
    }

    private function decrNonNegative(string $key): void
    {
        $v = Redis::get($key);
        $n = is_numeric($v) ? (int) $v : 0;
        if ($n > 0) {
            Redis::decr($key);
        }
    }

    private function sessionLockKey(int $studentId, int $examId): string
    {
        return 'exam_session_lock:'.$studentId.':'.$examId;
    }

    private function validId(int $id): bool
    {
        return $id > 0 && $id < 2_147_483_647;
    }

    private function quizToRow(Quiz $quiz): array
    {
        return [
            'id' => (int) $quiz->id,
            'university_id' => $quiz->university_id !== null ? (int) $quiz->university_id : null,
            'course_id' => $quiz->course_id !== null ? (int) $quiz->course_id : null,
            'created_by' => $quiz->created_by !== null ? (int) $quiz->created_by : null,
            'title' => (string) $quiz->title,
            'description' => $quiz->description,
            'assessment_type' => $quiz->assessment_type,
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
            'university_id' => $row['university_id'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'created_by' => $row['created_by'] ?? null,
            'title' => (string) ($row['title'] ?? ''),
            'description' => $row['description'] ?? null,
            'assessment_type' => $row['assessment_type'] ?? 'quiz',
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
        ]);
        $quiz->exists = true;
        $quiz->syncOriginal();

        return $quiz;
    }
}
