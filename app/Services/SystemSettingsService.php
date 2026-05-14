<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;

class SystemSettingsService
{
    public function get(string $key, ?string $default = null): ?string
    {
        $row = SystemSetting::query()->where('key', $key)->first();
        if ($row === null || $row->value === null || $row->value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($row->value);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $v = $this->get($key);
        if ($v === null || $v === '') {
            return $default;
        }

        return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default): int
    {
        $v = $this->get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        if (! is_numeric($v)) {
            return $default;
        }

        $i = (int) $v;

        return $i < 0 ? $default : $i;
    }

    /**
     * Masked placeholder for UI — never returns raw secrets.
     */
    public function getMasked(string $key): ?string
    {
        $row = SystemSetting::query()->where('key', $key)->first();
        if ($row === null || $row->value === null || $row->value === '') {
            return null;
        }

        return '********';
    }

    public function isLocked(string $key): bool
    {
        $row = SystemSetting::query()->where('key', $key)->first();

        return $row?->is_locked ?? false;
    }

    public function set(string $key, string $value, User $user): void
    {
        $existing = SystemSetting::query()->where('key', $key)->first();

        if ($existing && $existing->is_locked && $user->role !== 'admin') {
            throw new AuthorizationException('This setting is locked and cannot be modified.');
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => Crypt::encryptString($value),
                'is_locked' => $existing?->is_locked ?? false,
            ],
        );
    }

    public function lockSetting(string $key, User $user): void
    {
        abort_unless($user->role === 'admin', 403);

        $row = SystemSetting::query()->where('key', $key)->first();
        if ($row !== null) {
            $row->update(['is_locked' => true]);

            return;
        }

        $persist = $this->materializeValueForPersist($key);
        SystemSetting::query()->create([
            'key' => $key,
            'value' => Crypt::encryptString($persist),
            'is_locked' => true,
        ]);
    }

    /**
     * Value to persist when locking a key that has no row yet (matches UI defaults).
     */
    private function materializeValueForPersist(string $key): string
    {
        $existing = $this->get($key);
        if ($existing !== null && $existing !== '') {
            return $existing;
        }

        return match ($key) {
            'enable_otp', 'enable_sms', 'enable_proctoring', 'face_verification_required',
            'require_exam_start_snapshot', 'require_camera_monitoring',
            'phone_detection_enabled', 'fullscreen_required', 'auto_submit_enabled', 'enable_ai',
            'exam_clipboard_lock', 'exam_screenshot_mitigation', 'exam_screen_record_mitigation',
            'enable_redis_runtime', 'allow_redis_fallback', 'enable_live_sockets', 'allow_polling_fallback' => '1',
            'face_verification_threshold' => '60',
            'enable_student_practice_quizzes', 'enable_course_material_uploads', 'enable_ai_summary',
            'enable_ai_practice_quiz_generation', 'allow_examiner_practice_overview' => '0',
            'otp_expiry' => (string) config('exam_otp.ttl_seconds', 300),
            'otp_attempt_limit' => (string) config('exam_otp.max_verify_attempts', 3),
            'practice_quiz_daily_limit' => '5',
            'practice_quiz_monthly_limit' => '50',
            'practice_ai_token_limit_per_student' => '100000',
            'practice_ai_provider' => 'deepseek',
            'deepseek_model' => 'deepseek-chat',
            default => '',
        };
    }

    public function unlockSetting(string $key, User $user): void
    {
        abort_unless($user->role === 'admin', 403);

        SystemSetting::query()->where('key', $key)->update(['is_locked' => false]);
    }
}
