<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class BreakGlassEmergencyService
{
    public const SESSION_OTP_HASH = 'break_glass_otp_hash';

    public const SESSION_OTP_EXPIRES = 'break_glass_otp_expires_at';

    public const SESSION_INTENDED_ID = 'break_glass_intended_user_id';

    public const SESSION_OTP_ATTEMPTS = 'break_glass_otp_attempts';

    private const OTP_TTL_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 8;

    public function __construct(
        private readonly ArkeselSmsService $sms,
        private readonly SystemSettingsService $systemSettings,
    ) {}

    public function isEnabled(): bool
    {
        return config('breakglass.enabled')
            && trim((string) config('breakglass.secret_hash')) !== '';
    }

    public function throttleKeyForStep1(Request $request): string
    {
        return 'break-glass:1:'.sha1((string) $request->ip());
    }

    public function throttleKeyForVerify(Request $request): string
    {
        return 'break-glass:v:'.sha1((string) $request->ip());
    }

    public function tooManyAttempts(Request $request, string $key): bool
    {
        $max = max(1, (int) config('breakglass.attempts', 3));

        return RateLimiter::tooManyAttempts($key, $max);
    }

    public function hitThrottle(Request $request, string $key): void
    {
        $decay = max(1, (int) config('breakglass.decay_minutes', 60));
        RateLimiter::hit($key, $decay * 60);
    }

    public function clearThrottle(Request $request, string $key): void
    {
        RateLimiter::clear($key);
    }

    public function findTargetByCredential(string $username): ?User
    {
        $username = trim($username);
        if ($username === '') {
            return null;
        }

        return User::query()->where('email', $username)->first();
    }

    public function isStaffBreakGlassTarget(?User $user): bool
    {
        return $user !== null
            && $user->role !== 'student'
            && $user->is_active;
    }

    public function secretMatches(string $plain): bool
    {
        $hash = (string) config('breakglass.secret_hash');

        return $hash !== '' && Hash::check($plain, $hash);
    }

    public function resolveOwnerUser(): ?User
    {
        $u = trim((string) config('breakglass.owner_username'));
        if ($u === '') {
            return null;
        }

        return User::query()
            ->where('email', $u)
            ->where('role', 'admin')
            ->where('is_active', true)
            ->first();
    }

    public function logAttempt(string $eventType, array $eventData = []): void
    {
        ActivityLog::query()->create([
            'user_id' => null,
            'quiz_id' => null,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'created_at' => now(),
        ]);
    }

    public function issueChallenge(Request $request, User $intendedTarget): bool
    {
        $owner = $this->resolveOwnerUser();
        if ($owner === null) {
            $this->logAttempt('break_glass_owner_missing', []);

            return false;
        }

        $otp = app()->runningUnitTests()
            ? '123456'
            : str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $digits = $this->normalizePhone((string) config('breakglass.owner_phone'));
        if ($digits === null) {
            $this->logAttempt('break_glass_owner_phone_invalid', ['owner_id' => $owner->id]);

            return false;
        }

        if (! app()->runningUnitTests()) {
            try {
                if (! $this->arkeselReady()) {
                    $this->logAttempt('break_glass_sms_not_configured', []);

                    return false;
                }
                $this->sms->send([$digits], 'QUIZSNAP emergency access code: '.$otp);
            } catch (Throwable $e) {
                Log::warning('break_glass_sms_failed', [
                    'error' => $e->getMessage(),
                ]);
                $this->logAttempt('break_glass_sms_failed', []);

                return false;
            }
        }

        $request->session()->put([
            self::SESSION_OTP_HASH => hash('sha256', $otp),
            self::SESSION_OTP_EXPIRES => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
            self::SESSION_INTENDED_ID => $intendedTarget->id,
            self::SESSION_OTP_ATTEMPTS => 0,
        ]);

        $this->logAttempt('break_glass_challenge_issued', [
            'intended_user_id' => $intendedTarget->id,
            'owner_id' => $owner->id,
        ]);

        return true;
    }

    public function verifyOtp(Request $request, string $otp): bool
    {
        $hash = $request->session()->get(self::SESSION_OTP_HASH);
        $expires = $request->session()->get(self::SESSION_OTP_EXPIRES);
        if (! is_string($hash) || $hash === '' || $expires === null) {
            return false;
        }

        if (now()->timestamp > (int) $expires) {
            $this->clearChallengeSession($request);

            return false;
        }

        $attempts = (int) $request->session()->get(self::SESSION_OTP_ATTEMPTS, 0);
        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $this->clearChallengeSession($request);

            return false;
        }
        $request->session()->put(self::SESSION_OTP_ATTEMPTS, $attempts + 1);

        if (! hash_equals($hash, hash('sha256', $otp))) {
            $this->logAttempt('break_glass_otp_failed', []);

            return false;
        }

        $intendedId = $request->session()->get(self::SESSION_INTENDED_ID);
        if (! is_numeric($intendedId)) {
            $this->clearChallengeSession($request);

            return false;
        }

        $intended = User::query()->find((int) $intendedId);
        if ($intended === null || $intended->role === 'student' || ! $intended->is_active) {
            $this->clearChallengeSession($request);
            $this->logAttempt('break_glass_intended_invalid_after_otp', []);

            return false;
        }

        $owner = $this->resolveOwnerUser();
        if ($owner === null) {
            $this->clearChallengeSession($request);

            return false;
        }

        $this->clearChallengeSession($request);
        $request->session()->put('break_glass_intended_target_user_id', (int) $intended->id);

        $this->logAttempt('break_glass_success', [
            'owner_id' => $owner->id,
            'intended_user_id' => (int) $intended->id,
        ]);

        return true;
    }

    public function clearChallengeSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_OTP_HASH,
            self::SESSION_OTP_EXPIRES,
            self::SESSION_INTENDED_ID,
            self::SESSION_OTP_ATTEMPTS,
        ]);
    }

    public function hasPendingChallenge(Request $request): bool
    {
        return $request->session()->has(self::SESSION_OTP_HASH);
    }

    private function arkeselReady(): bool
    {
        $key = $this->systemSettings->get('arkesel_api_key');
        $sender = $this->systemSettings->get('arkesel_sender_id');

        return $key !== null && $key !== '' && $sender !== null && trim($sender) !== '';
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return $digits === '' ? null : $digits;
    }
}
