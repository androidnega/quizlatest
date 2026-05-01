<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exam start OTP — Redis only; OTP stored as bcrypt hash (never plaintext, never logged).
 */
class ExamOtpService
{
    public function __construct(
        private readonly ArkeselSmsService $sms,
    ) {}

    public function otpKey(int $studentId, int $examId): string
    {
        return "otp:{$studentId}:{$examId}";
    }

    /**
     * True when Redis record exists, is within TTL, and verified === true.
     */
    public function isOtpVerified(int $studentId, int $examId): bool
    {
        $raw = Redis::get($this->otpKey($studentId, $examId));
        if ($raw === null || $raw === '') {
            return false;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || ! ($data['verified'] ?? false)) {
            return false;
        }

        return ($data['expires_at'] ?? 0) > now()->timestamp;
    }

    /**
     * Entry pipeline: exam access already validated.
     *
     * @return string 'continue' | 'otp_required' | 'otp_pending'
     */
    public function evaluateStartGate(User $student, int $examId): string
    {
        $key = $this->otpKey((int) $student->id, $examId);
        $data = $this->getPayload($key);

        if ($this->isVerifiedPayloadAlive($data)) {
            return 'continue';
        }

        if (is_array($data) && ($data['verified'] ?? false) === true) {
            Redis::del($key);
            $data = null;
        }

        if ($this->isPendingPayloadAlive($data)) {
            return 'otp_pending';
        }

        if (is_array($data)) {
            Redis::del($key);
        }

        return $this->atomicCreateAndSendOtp($student, $examId);
    }

    /**
     * @throws HttpException
     */
    public function verifySubmittedOtp(User $student, int $examId, string $otpCode): void
    {
        abort_unless($student->role === 'student', 403);

        $key = $this->otpKey((int) $student->id, $examId);
        $raw = Redis::get($key);

        abort_if($raw === null || $raw === '', 422, 'No verification code is active for this exam. Start the exam again.');

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        abort_if(! is_array($data), 422, 'Verification could not be completed.');

        if (($data['verified'] ?? false) === true) {
            abort(422, 'This exam is already verified for the current code window.');
        }

        $expiresAt = (int) ($data['expires_at'] ?? 0);
        if ($expiresAt <= now()->timestamp) {
            Redis::del($key);
            abort(422, 'This code has expired. Start the exam again to receive a new code.');
        }

        $attempts = (int) ($data['attempt_count'] ?? 0);
        $maxAttempts = (int) config('exam_otp.max_verify_attempts', 3);
        abort_if($attempts >= $maxAttempts, 422, 'Too many failed attempts. Start the exam again.');

        $hash = $data['otp_hash'] ?? null;
        abort_if(! is_string($hash) || $hash === '', 422, 'Verification could not be completed.');

        $normalized = $this->normalizeOtpDigits($otpCode);
        abort_if(strlen($normalized) !== 6, 422, 'Invalid verification code format.');

        if (! password_verify($normalized, $hash)) {
            $data['attempt_count'] = $attempts + 1;
            $remainingTtl = max(1, $expiresAt - now()->timestamp);
            if ($data['attempt_count'] >= $maxAttempts) {
                Redis::del($key);
                abort(422, 'Too many failed attempts. Start the exam again.');
            }
            Redis::setex($key, $remainingTtl, json_encode($data));

            abort(422, 'Invalid verification code.');
        }

        $verifiedTtl = (int) config('exam_otp.verified_ttl_seconds', 900);
        $verifiedUntil = now()->addSeconds($verifiedTtl)->timestamp;

        $verifiedPayload = [
            'otp_hash' => null,
            'expires_at' => $verifiedUntil,
            'attempt_count' => (int) ($data['attempt_count'] ?? 0),
            'verified' => true,
            'last_sent_at' => (int) ($data['last_sent_at'] ?? now()->timestamp),
        ];

        Redis::setex($key, $verifiedTtl, json_encode($verifiedPayload));
    }

    /**
     * After session created — one-time use; removes OTP state.
     */
    public function forgetVerifiedFlag(int $studentId, int $examId): void
    {
        Redis::del($this->otpKey($studentId, $examId));
    }

    /**
     * @return 'otp_required'|'otp_pending'|'continue'
     */
    private function atomicCreateAndSendOtp(User $student, int $examId): string
    {
        $phone = $this->normalizedPhone($student->phone ?? null);
        abort_if($phone === null, 422, 'Add a phone number on your profile before starting this exam.');

        $this->enforceSendRateLimit((int) $student->id);

        $plain = $this->generateSixDigitCode();
        $hash = password_hash($plain, PASSWORD_BCRYPT);

        $ttl = (int) config('exam_otp.ttl_seconds', 300);
        $expiresAt = now()->addSeconds($ttl)->timestamp;
        $now = now()->timestamp;

        $payload = [
            'otp_hash' => $hash,
            'expires_at' => $expiresAt,
            'attempt_count' => 0,
            'verified' => false,
            'last_sent_at' => $now,
        ];

        $key = $this->otpKey((int) $student->id, $examId);
        $encoded = json_encode($payload);

        $created = $this->redisSetNxEx($key, $encoded, $ttl);

        if (! $created) {
            $again = $this->getPayload($key);
            if ($this->isVerifiedPayloadAlive($again)) {
                return 'continue';
            }

            return 'otp_pending';
        }

        $message = 'Your QUIZSNAP verification code is: '.$plain.'. It expires in 5 minutes.';

        try {
            $result = $this->sms->send([$phone], $message);
            if (! ($result['success'] ?? false)) {
                Redis::del($key);
                throw new RuntimeException('SMS gateway rejected the message.');
            }
        } catch (\Throwable $e) {
            Redis::del($key);
            throw $e;
        }

        RateLimiter::hit(
            $this->sendRateLimiterKey((int) $student->id),
            (int) config('exam_otp.send_window_seconds', 600),
        );

        return 'otp_required';
    }

    /**
     * Atomic SET with TTL only when key does not exist (race-safe creation).
     */
    private function redisSetNxEx(string $key, string $value, int $ttlSeconds): bool
    {
        $result = Redis::set($key, $value, 'EX', $ttlSeconds, 'NX');

        return $result === true || $result === 'OK' || $result === 1;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function isVerifiedPayloadAlive(?array $data): bool
    {
        if (! is_array($data) || ($data['verified'] ?? false) !== true) {
            return false;
        }

        return ($data['expires_at'] ?? 0) > now()->timestamp;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function isPendingPayloadAlive(?array $data): bool
    {
        if (! is_array($data) || ($data['verified'] ?? false) === true) {
            return false;
        }

        return ($data['expires_at'] ?? 0) > now()->timestamp;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPayload(string $key): ?array
    {
        $raw = Redis::get($key);
        if ($raw === null || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    private function generateSixDigitCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    private function normalizeOtpDigits(string $otp): string
    {
        $digits = preg_replace('/\D/', '', $otp);

        return $digits ?? '';
    }

    private function normalizedPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        return $digits;
    }

    private function sendRateLimiterKey(int $studentId): string
    {
        return 'exam-otp-send:'.$studentId;
    }

    private function enforceSendRateLimit(int $studentId): void
    {
        $key = $this->sendRateLimiterKey($studentId);
        $max = (int) config('exam_otp.max_send_per_window', 5);
        $decay = (int) config('exam_otp.send_window_seconds', 600);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);
            abort(429, 'Too many verification codes requested. Try again in '.$seconds.' seconds.');
        }
    }
}
