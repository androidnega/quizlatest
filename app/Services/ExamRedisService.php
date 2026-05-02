<?php

namespace App\Services;

use App\Models\Quiz;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Redis-backed exam runtime primitives (locks, rate limits, cache, counters).
 * All operations are best-effort when Redis is unavailable except documented callers.
 */
final class ExamRedisService
{
    public function __construct(
        private readonly RedisHealthService $redisHealth,
    ) {}

    public function acquireSessionStartLock(int $studentId, int $examId): bool
    {
        if (! $this->redisHealth->isAvailable()) {
            return true;
        }
        if (! $this->validId($studentId) || ! $this->validId($examId)) {
            return false;
        }

        $key = $this->sessionLockKey($studentId, $examId);
        $ttl = max(1, (int) config('exam_redis.session_lock_ttl_seconds', 60));

        try {
            $r = Redis::set($key, '1', 'EX', $ttl, 'NX');

            return $r === true || $r === 'OK' || $r === 1;
        } catch (\Throwable $e) {
            Log::warning('exam_redis.session_lock_failed', ['error' => $e->getMessage()]);

            return true;
        }
    }

    public function releaseSessionStartLock(int $studentId, int $examId): void
    {
        if (! $this->redisHealth->isAvailable() || ! $this->validId($studentId) || ! $this->validId($examId)) {
            return;
        }

        try {
            Redis::del($this->sessionLockKey($studentId, $examId));
        } catch (\Throwable $e) {
            Log::warning('exam_redis.session_lock_release_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @throws HttpException
     */
    public function enforceExamStartRateLimit(int $studentId): void
    {
        if (! $this->redisHealth->isAvailable() || ! $this->validId($studentId)) {
            return;
        }

        $window = max(1, (int) config('exam_redis.exam_start_window_seconds', 60));
        $max = max(1, (int) config('exam_redis.exam_start_max_attempts', 30));
        $key = 'exam_start_attempts:'.$studentId;

        try {
            $n = (int) Redis::incr($key);
            if ($n === 1) {
                Redis::expire($key, $window);
            }
            if ($n > $max) {
                abort(429, 'Too many exam start attempts. Try again shortly.');
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('exam_redis.start_rate_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @throws HttpException
     */
    public function enforceOtpSendRateLimit(int $studentId): void
    {
        if (! $this->redisHealth->isAvailable() || ! $this->validId($studentId)) {
            return;
        }

        $window = max(1, (int) config('exam_otp.send_window_seconds', 600));
        $max = max(1, (int) config('exam_otp.max_send_per_window', 5));
        $key = 'exam_otp_send_rate:'.$studentId;

        try {
            $n = (int) Redis::incr($key);
            if ($n === 1) {
                Redis::expire($key, $window);
            }
            if ($n > $max) {
                abort(429, 'Too many verification codes requested. Try again later.');
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('exam_redis.otp_send_rate_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @throws HttpException
     */
    public function enforceProctoringEventBudget(string $sessionId, int $eventCount = 1): void
    {
        if (! $this->redisHealth->isAvailable()) {
            return;
        }

        $sessionId = trim($sessionId);
        if ($sessionId === '' || ! Str::isUuid($sessionId)) {
            abort(422, 'Invalid session identifier.');
        }

        $eventCount = max(1, min(100, $eventCount));
        $window = max(1, (int) config('exam_redis.proctoring_events_window_seconds', 60));
        $max = max(1, (int) config('exam_redis.proctoring_events_max_per_window', 200));
        $key = 'proctoring_event_flood:'.$sessionId;

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
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('exam_redis.proctoring_flood_failed', ['error' => $e->getMessage()]);
        }
    }

    public function rememberQuiz(int $examId, \Closure $loader): Quiz
    {
        if (! $this->validId($examId) || ! $this->redisHealth->isAvailable()) {
            return $loader();
        }

        $key = 'exam_config:'.$examId;
        $ttl = max(60, (int) config('exam_redis.exam_config_ttl_seconds', 480));

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
            $payload = [
                'id' => (int) $quiz->id,
                'university_id' => $quiz->university_id !== null ? (int) $quiz->university_id : null,
                'course_id' => $quiz->course_id !== null ? (int) $quiz->course_id : null,
                'created_by' => $quiz->created_by !== null ? (int) $quiz->created_by : null,
                'title' => (string) $quiz->title,
                'description' => $quiz->description,
                'assessment_type' => $quiz->assessment_type,
                'status' => $quiz->status,
                'duration_minutes' => (int) ($quiz->duration_minutes ?? 0),
                'total_marks' => $quiz->total_marks,
                'proctoring_settings' => is_array($quiz->proctoring_settings) ? $quiz->proctoring_settings : [],
                'available_from' => $quiz->available_from?->toAtomString(),
                'available_to' => $quiz->available_to?->toAtomString(),
            ];
            Redis::setex($key, $ttl, json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::warning('exam_redis.exam_config_write_failed', ['error' => $e->getMessage()]);
        }

        return $quiz;
    }

    public function incrementActiveSessions(int $examId): void
    {
        if (! $this->redisHealth->isAvailable() || ! $this->validId($examId)) {
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
        if (! $this->redisHealth->isAvailable() || ! $this->validId($examId)) {
            return;
        }

        try {
            $this->decrNonNegative('qs:exam_active_sessions:global');
            $this->decrNonNegative('qs:exam_active_sessions:exam:'.$examId);
        } catch (\Throwable $e) {
            Log::warning('exam_redis.active_decr_failed', ['error' => $e->getMessage()]);
        }
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
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'total_marks' => $row['total_marks'] ?? 0,
            'proctoring_settings' => is_array($row['proctoring_settings'] ?? null) ? $row['proctoring_settings'] : [],
            'available_from' => isset($row['available_from']) ? $row['available_from'] : null,
            'available_to' => isset($row['available_to']) ? $row['available_to'] : null,
        ]);
        $quiz->exists = true;
        $quiz->syncOriginal();

        return $quiz;
    }
}
