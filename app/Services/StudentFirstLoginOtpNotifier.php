<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Delivers six-digit student auth OTP via SMS only (no email path).
 */
final class StudentFirstLoginOtpNotifier
{
    public function __construct(
        private readonly ArkeselSmsService $sms,
        private readonly SystemSettingsService $systemSettings,
    ) {}

    /**
     * Send OTP to the student's saved phone, or to an explicit E.164-style digit string (pending verification).
     */
    public function notify(User $student, string $otp, ?string $phoneDigitsOverride = null): void
    {
        if (app()->runningUnitTests()) {
            Log::info('student_first_login_otp_dispatched_tests', [
                'user_id' => $student->id,
            ]);

            return;
        }

        $digits = $phoneDigitsOverride ?? $this->normalizedPhone($student->phone);
        if ($digits === null || ! $this->smsConfigured()) {
            throw new RuntimeException('SMS is not configured or no valid phone number is available.');
        }

        try {
            $this->sms->send([$digits], 'QUIZSNAP verification code: '.$otp);
        } catch (Throwable $e) {
            Log::warning('student_first_login_sms_failed', [
                'user_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function smsConfigured(): bool
    {
        $key = $this->systemSettings->get('arkesel_api_key');
        $sender = $this->systemSettings->get('arkesel_sender_id');

        return $key !== null && $key !== '' && $sender !== null && $sender !== '';
    }

    private function normalizedPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        return $digits === '' ? null : $digits;
    }
}
