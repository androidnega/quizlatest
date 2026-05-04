<?php

namespace App\Services;

/**
 * Admin + environment gates for Redis-backed exam runtime and live WebSockets.
 */
final class ExamRuntimeInfraGate
{
    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly RedisHealthService $redisHealth,
    ) {}

    public function redisRuntimeEnabledByAdmin(): bool
    {
        return $this->settings->getBool('enable_redis_runtime', true);
    }

    /**
     * True when Redis should be used for exam runtime primitives (locks, counters, OTP store, etc.).
     */
    public function useRedisForExamRuntime(): bool
    {
        return $this->redisRuntimeEnabledByAdmin() && $this->redisHealth->isAvailable();
    }

    public function allowRedisFallback(): bool
    {
        return $this->settings->getBool('allow_redis_fallback', true);
    }

    /**
     * True when neither Redis nor admin wants Redis, but Laravel cache/rate-limiter fallbacks are allowed.
     */
    public function useCacheBackedExamRuntimeFallbacks(): bool
    {
        if ($this->useRedisForExamRuntime()) {
            return false;
        }

        return $this->allowRedisFallback();
    }

    /**
     * No Redis and fallbacks disabled — distributed primitives are skipped (logged).
     */
    public function examRuntimeFullyDegraded(): bool
    {
        return ! $this->useRedisForExamRuntime() && ! $this->allowRedisFallback();
    }

    public function enableLiveSockets(): bool
    {
        return $this->settings->getBool('enable_live_sockets', true);
    }

    public function allowPollingFallback(): bool
    {
        return $this->settings->getBool('allow_polling_fallback', true);
    }

    /**
     * Whether Reverb appears configured for the app (not a live socket probe).
     */
    public function reverbEnvConfigured(): bool
    {
        if (config('broadcasting.default') !== 'reverb') {
            return false;
        }

        /** @var mixed $apps */
        $apps = config('reverb.apps.apps');
        if (! is_array($apps) || $apps === [] || ! is_array($apps[0] ?? null)) {
            return false;
        }

        $key = $apps[0]['key'] ?? null;

        return is_string($key) && $key !== '';
    }

    /**
     * Exam OTP storage can proceed (Redis or cache fallback path).
     */
    public function examOtpStorageOperational(): bool
    {
        if ($this->useRedisForExamRuntime()) {
            return true;
        }

        return $this->allowRedisFallback() || (bool) config('exam_otp.fallback_enabled', false);
    }
}
