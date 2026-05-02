<?php

namespace App\Services;

/**
 * Institution-wide exam / OTP / proctoring policy from {@see SystemSettingsService}.
 * Admin settings are authoritative over per-exam quiz configuration.
 */
final class SystemExamPolicyService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {}

    public function isOtpEnabled(): bool
    {
        return $this->settings->getBool('enable_otp', true);
    }

    public function getOtpExpirySeconds(): int
    {
        $v = $this->settings->getInt('otp_expiry', 0);

        return $v > 0 ? $v : (int) config('exam_otp.ttl_seconds', 300);
    }

    public function getOtpAttemptLimit(): int
    {
        $v = $this->settings->getInt('otp_attempt_limit', 0);

        return $v > 0 ? $v : (int) config('exam_otp.max_verify_attempts', 3);
    }

    public function isSmsEnabled(): bool
    {
        return $this->settings->getBool('enable_sms', true);
    }

    public function isProctoringEnabled(): bool
    {
        return $this->settings->getBool('enable_proctoring', true);
    }

    public function isFaceVerificationRequired(): bool
    {
        return $this->settings->getBool('face_verification_required', true);
    }

    public function isPhoneDetectionEnabled(): bool
    {
        return $this->settings->getBool('phone_detection_enabled', true);
    }

    public function isFullscreenRequired(): bool
    {
        return $this->settings->getBool('fullscreen_required', true);
    }

    public function isAutoSubmitEnabled(): bool
    {
        return $this->settings->getBool('auto_submit_enabled', true);
    }

    /**
     * Apply admin caps to normalized proctoring settings (exam + defaults already merged).
     *
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function capNormalizedProctoringSettings(array $normalized): array
    {
        if (! $this->isProctoringEnabled()) {
            $normalized['phone_detection_enabled'] = false;
            $normalized['fullscreen_enforced'] = false;
            $normalized['auto_submit_enabled'] = false;

            return $normalized;
        }

        if (! $this->isPhoneDetectionEnabled()) {
            $normalized['phone_detection_enabled'] = false;
        }
        if (! $this->isFullscreenRequired()) {
            $normalized['fullscreen_enforced'] = false;
        }
        if (! $this->isAutoSubmitEnabled()) {
            $normalized['auto_submit_enabled'] = false;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $clientSlice
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $effective
     * @return array<string, mixed>
     */
    public function capEffectiveOrchestratorSettings(array $effective): array
    {
        if (! $this->isProctoringEnabled()) {
            $effective['phone_detection_enabled'] = false;
            $effective['fullscreen_enforced'] = false;
            $effective['auto_submit_enabled'] = false;

            return $effective;
        }

        if (! $this->isPhoneDetectionEnabled()) {
            $effective['phone_detection_enabled'] = false;
        }
        if (! $this->isFullscreenRequired()) {
            $effective['fullscreen_enforced'] = false;
        }
        if (! $this->isAutoSubmitEnabled()) {
            $effective['auto_submit_enabled'] = false;
        }

        return $effective;
    }

    public function capClientProctoringPayload(array $clientSlice): array
    {
        if (! $this->isProctoringEnabled()) {
            $clientSlice['phone_detection_enabled'] = false;
            $clientSlice['fullscreen_enforced'] = false;
            $clientSlice['auto_submit_enabled'] = false;

            return $clientSlice;
        }

        if (! $this->isPhoneDetectionEnabled()) {
            $clientSlice['phone_detection_enabled'] = false;
        }
        if (! $this->isFullscreenRequired()) {
            $clientSlice['fullscreen_enforced'] = false;
        }
        if (! $this->isAutoSubmitEnabled()) {
            $clientSlice['auto_submit_enabled'] = false;
        }

        return $clientSlice;
    }
}
