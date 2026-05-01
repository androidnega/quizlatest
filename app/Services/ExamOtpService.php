<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exam start OTP — stored only in Redis (never DB, never logged).
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

    public function verifiedKey(int $studentId, int $examId): string
    {
        return "otp_verified:{$studentId}:{$examId}";
    }

    public function isOtpVerified(int $studentId, int $examId): bool
    {
        return (bool) Redis::get($this->verifiedKey($studentId, $examId));
    }

    /**
     * Ensure a pending OTP exists for SMS flow; send only when creating or refreshing after expiry.
     * Does not log OTP values.
     *
     * @throws HttpException
     */
    public function ensurePendingOtpIssued(User $student, int $examId): void
    {
        $key = $this->otpKey((int) $student->id, $examId);

        $existing = Redis::get($key);
        if ($existing !== null && $existing !== '') {
            $payload = json_decode($existing, true);
            if (is_array($payload) && isset($payload['expires_at'])
                && (int) $payload['expires_at'] > now()->timestamp) {
                return;
            }
            Redis::del($key);
        }

        $phone = $this->normalizedPhone($student->phone ?? null);
        abort_if($phone === null, 422, 'Add a phone number on your profile before starting this exam.');

        $this->enforceSendRateLimit((int) $student->id);

        $code = $this->generateSixDigitCode();
        $ttl = config('exam_otp.ttl_seconds', 300);
        $expiresAt = now()->addSeconds($ttl)->timestamp;

        $payload = [
            'otp_code' => $code,
            'expires_at' => $expiresAt,
            'attempt_count' => 0,
        ];

        Redis::setex($key, $ttl, json_encode($payload));

        $message = 'Your QUIZSNAP verification code is: '.$code.'. It expires in 5 minutes.';

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

        RateLimiter::hit($this->sendRateLimiterKey((int) $student->id), config('exam_otp.send_window_seconds', 600));
    }

    /**
     * Validate OTP and set short-lived verified flag in Redis; removes pending OTP (one-time code).
     *
     * @throws HttpException
     */
    public function verifySubmittedOtp(User $student, int $examId, string $otpCode): void
    {
        abort_unless($student->role === 'student', 403);

        $key = $this->otpKey((int) $student->id, $examId);
        $raw = Redis::get($key);

        abort_if($raw === null || $raw === '', 422, 'No verification code is active for this exam. Request a new code.');

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        abort_if(! is_array($data), 422, 'Verification could not be completed.');

        $expiresAt = (int) ($data['expires_at'] ?? 0);
        abort_if($expiresAt <= now()->timestamp, 422, 'This code has expired. Start the exam again to receive a new code.');

        $attempts = (int) ($data['attempt_count'] ?? 0);
        $maxAttempts = config('exam_otp.max_verify_attempts', 3);
        abort_if($attempts >= $maxAttempts, 422, 'Too many failed attempts. Request a new code.');

        $expected = (string) ($data['otp_code'] ?? '');
        $given = preg_replace('/\D/', '', $otpCode) ?? '';

        if (! hash_equals($expected, $given)) {
            $data['attempt_count'] = $attempts + 1;
            $remainingTtl = max(1, $expiresAt - now()->timestamp);
            if ($data['attempt_count'] >= $maxAttempts) {
                Redis::del($key);
                abort(422, 'Too many failed attempts. Request a new code.');
            }
            Redis::setex($key, $remainingTtl, json_encode($data));
            abort(422, 'Invalid verification code.');
        }

        Redis::del($key);

        $verifiedTtl = (int) config('exam_otp.verified_ttl_seconds', 900);
        Redis::setex($this->verifiedKey((int) $student->id, $examId), $verifiedTtl, '1');
    }

    /**
     * After session is created — verified flag is single-use for this attempt chain.
     */
    public function forgetVerifiedFlag(int $studentId, int $examId): void
    {
        Redis::del($this->verifiedKey($studentId, $examId));
    }

    private function generateSixDigitCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
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
        $max = (int) config('exam_otp.max_send_per_window', 3);
        $decay = (int) config('exam_otp.send_window_seconds', 600);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);
            abort(429, 'Too many verification codes requested. Try again in '.$seconds.' seconds.');
        }
    }
}
