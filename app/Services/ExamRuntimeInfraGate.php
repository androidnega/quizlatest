<?php

namespace App\Services;

/**
 * Admin + environment gates for the exam runtime and live WebSockets.
 *
 * The runtime relies entirely on the Laravel cache store, RateLimiter,
 * and the database. This gate retains the live-sockets / Reverb fields
 * because those are still admin-toggleable.
 */
final class ExamRuntimeInfraGate
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {}

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
     * Exam OTP storage is always available — backed by the Laravel
     * cache store (file by default on shared hosting).
     */
    public function examOtpStorageOperational(): bool
    {
        return true;
    }
}
