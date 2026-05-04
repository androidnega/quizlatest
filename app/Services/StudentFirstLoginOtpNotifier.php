<?php

namespace App\Services;

use App\Exceptions\StudentSmsVerificationUnavailable;
use App\Models\User;
use Illuminate\Support\Facades\Log;
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
     *
     * @throws StudentSmsVerificationUnavailable When SMS is disabled or Arkesel is not fully configured.
     */
    public function notify(User $student, string $otp, ?string $phoneDigitsOverride = null): void
    {
        if (! $this->systemSettings->getBool('enable_sms', true)) {
            throw StudentSmsVerificationUnavailable::smsDisabled();
        }

        if (! $this->arkeselCredentialsPresent()) {
            throw StudentSmsVerificationUnavailable::smsIncompleteConfiguration();
        }

        $digits = $phoneDigitsOverride ?? $this->normalizedPhone($student->phone);
        if ($digits === null) {
            throw StudentSmsVerificationUnavailable::smsIncompleteConfiguration();
        }

        if (app()->runningUnitTests()) {
            Log::info('student_first_login_otp_dispatched_tests', [
                'user_id' => $student->id,
            ]);

            return;
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

    private function arkeselCredentialsPresent(): bool
    {
        $key = $this->systemSettings->get('arkesel_api_key');
        $sender = $this->systemSettings->get('arkesel_sender_id');

        return $key !== null && $key !== '' && $sender !== null && trim($sender) !== '';
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
