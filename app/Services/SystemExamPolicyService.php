<?php

namespace App\Services;

use App\Models\Quiz;

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

    /**
     * Presentation mode for the student exam runtime.
     * - 'classic' = the existing list-of-questions layout (default; unchanged behaviour).
     * - 'arena'   = the gamified Kahoot-style flow (single colored card, step rail, feedback sweep).
     *
     * Assignments always render in classic regardless of this setting — coursework
     * needs the essay editor and file upload slot that don't fit the arena card.
     */
    public function getStudentExamPlayMode(): string
    {
        $raw = strtolower(trim((string) ($this->settings->get('student_exam_play_mode') ?? '')));
        if ($raw === 'arena') {
            return 'arena';
        }

        return 'classic';
    }

    public function getStudentExamPlayModeForQuiz(?Quiz $quiz): string
    {
        if ($quiz !== null && $quiz->isAssignment()) {
            return 'classic';
        }

        return $this->getStudentExamPlayMode();
    }

    /**
     * When proctoring is enabled, require one exam-start verification photo (not identity matching).
     */
    public function isExamStartSnapshotRequired(): bool
    {
        if (! $this->isProctoringEnabled()) {
            return false;
        }

        $explicit = $this->settings->get('require_exam_start_snapshot');
        if ($explicit !== null && $explicit !== '') {
            return $this->settings->getBool('require_exam_start_snapshot', true);
        }

        return $this->settings->getBool('face_verification_required', true);
    }

    /**
     * When proctoring is enabled, keep camera/video monitoring active during the exam (runtime engine).
     */
    public function isCameraMonitoringRequired(): bool
    {
        if (! $this->isProctoringEnabled()) {
            return false;
        }

        $explicit = $this->settings->get('require_camera_monitoring');
        if ($explicit !== null && $explicit !== '') {
            return $this->settings->getBool('require_camera_monitoring', true);
        }

        return true;
    }

    /**
     * Coursework assignments skip live camera monitoring unless explicitly enabled on the quiz
     * (reserved for a future admin-controlled exception).
     */
    public function isCameraMonitoringRequiredForQuiz(?Quiz $quiz): bool
    {
        if ($quiz !== null && $quiz->isAssignment()) {
            $live = filter_var(
                data_get($quiz->proctoring_settings, 'allow_live_proctoring_for_assignment', false),
                FILTER_VALIDATE_BOOLEAN,
            );

            return $live && $this->isCameraMonitoringRequired();
        }

        return $this->isCameraMonitoringRequired();
    }

    public function isExamStartSnapshotRequiredForQuiz(?Quiz $quiz): bool
    {
        if ($quiz !== null && $quiz->isAssignment()) {
            return false;
        }

        return $this->isExamStartSnapshotRequired();
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
     * Block copy/cut/paste inside the invigilated exam UI (best-effort; not assignment mode).
     */
    public function isExamClipboardLockEnabled(): bool
    {
        return $this->settings->getBool('exam_clipboard_lock', true);
    }

    /**
     * Mitigate common browser screenshot shortcuts and context menu (fullscreen + policy).
     */
    public function isExamScreenshotMitigationEnabled(): bool
    {
        return $this->settings->getBool('exam_screenshot_mitigation', true);
    }

    /**
     * Best-effort: try Keyboard Lock for PrintScreen when supported (cannot stop OS recorders).
     */
    public function isExamScreenRecordMitigationEnabled(): bool
    {
        return $this->settings->getBool('exam_screen_record_mitigation', true);
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

    /**
     * @param  array<string, mixed>  $clientSlice
     * @return array<string, mixed>
     */
    public function capClientProctoringPayload(array $clientSlice): array
    {
        if (! $this->isProctoringEnabled()) {
            $clientSlice['phone_detection_enabled'] = false;
            $clientSlice['fullscreen_enforced'] = false;
            $clientSlice['auto_submit_enabled'] = false;
            $clientSlice['require_camera_monitoring'] = false;

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
        if (! $this->isCameraMonitoringRequired()) {
            $clientSlice['require_camera_monitoring'] = false;
        }

        return $clientSlice;
    }
}
