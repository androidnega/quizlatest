<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\StudentSmsVerificationUnavailable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentFirstLoginOtpNotifier;
use App\Support\StudentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StudentPasswordResetController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 8;

    private const RESET_COOLDOWN_DAYS = 90;

    public function __construct(
        private readonly StudentFirstLoginOtpNotifier $otpNotifier,
    ) {}

    public function create(): View
    {
        return view('auth.student-forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($validated['identifier']);
        $phoneNorm = StudentPhone::normalize($identifier);

        $user = User::query()
            ->where('role', 'student')
            ->where('is_active', true)
            ->where(function ($q) use ($identifier, $phoneNorm) {
                $q->where('index_number', $identifier);
                if ($phoneNorm !== null) {
                    $q->orWhere('phone', $identifier)->orWhere('phone', $phoneNorm);
                }
            })
            ->first();

        if ($user === null) {
            return back()
                ->with('status', __('If an account matches, we sent a verification code to the phone on file.'));
        }

        if ($user->student_onboarded_at === null) {
            return back()
                ->with('status', __('If an account matches, we sent a verification code to the phone on file.'));
        }

        $phone = StudentPhone::normalize($user->phone);
        if ($phone === null || trim((string) $user->phone) === '') {
            return back()->withErrors([
                'identifier' => __('Please contact your coordinator to add a phone number before password reset.'),
            ]);
        }

        if ($user->last_student_password_reset_at !== null) {
            $next = $user->last_student_password_reset_at->copy()->addDays(self::RESET_COOLDOWN_DAYS);
            if (now()->lt($next)) {
                return back()->withErrors([
                    'identifier' => __('Self-service password reset is available once every 3 months for students. Try again after :date.', ['date' => $next->toFormattedDateString()]),
                ]);
            }
        }

        $otp = app()->runningUnitTests()
            ? '654321'
            : str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        try {
            $this->otpNotifier->notify($user, $otp, $phone);
        } catch (StudentSmsVerificationUnavailable $e) {
            return back()->withErrors([
                'identifier' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('student_password_reset_otp_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'identifier' => __('We could not send a verification code right now. Try again later or contact your coordinator.'),
            ]);
        }

        $request->session()->put([
            'student_pw_reset_user_id' => $user->id,
            'student_pw_reset_otp_hash' => hash('sha256', $otp),
            'student_pw_reset_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
            'student_pw_reset_otp_attempts' => 0,
            'student_pw_reset_verified' => false,
        ]);

        return redirect()->route('student.password-reset.otp');
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('student_pw_reset_user_id')) {
            return redirect()->route('student.password-reset.request');
        }

        return view('auth.student-forgot-password-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        if (! $request->session()->has('student_pw_reset_user_id')) {
            return redirect()->route('student.password-reset.request');
        }

        $expiresAt = $request->session()->get('student_pw_reset_otp_expires_at');
        if ($expiresAt === null || now()->timestamp > $expiresAt) {
            $this->clearOtpSession($request);

            return redirect()->route('student.password-reset.request')
                ->withErrors(['identifier' => __('The code expired. Please start again.')]);
        }

        $attempts = (int) $request->session()->get('student_pw_reset_otp_attempts', 0);
        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $this->clearOtpSession($request);

            return redirect()->route('student.password-reset.request')
                ->withErrors(['identifier' => __('Too many incorrect attempts. Please start again.')]);
        }
        $request->session()->put('student_pw_reset_otp_attempts', $attempts + 1);

        $hash = $request->session()->get('student_pw_reset_otp_hash');
        if (! hash_equals((string) $hash, hash('sha256', $request->input('otp')))) {
            return back()->withErrors(['otp' => __('Invalid code.')]);
        }

        $this->clearOtpSession($request);
        $request->session()->put('student_pw_reset_verified', true);

        return redirect()->route('student.password-reset.form');
    }

    public function editPassword(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('student_pw_reset_verified')) {
            return redirect()->route('student.password-reset.request');
        }

        return view('auth.student-reset-password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        if (! $request->session()->get('student_pw_reset_verified') || ! $request->session()->has('student_pw_reset_user_id')) {
            return redirect()->route('student.password-reset.request');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->find((int) $request->session()->get('student_pw_reset_user_id'));
        if ($user === null || $user->role !== 'student' || ! $user->is_active) {
            $request->session()->forget(['student_pw_reset_verified', 'student_pw_reset_user_id']);

            return redirect()->route('student.password-reset.request');
        }

        $user->forceFill([
            'password' => $validated['password'],
            'last_student_password_reset_at' => now(),
        ])->save();

        $request->session()->forget(['student_pw_reset_verified', 'student_pw_reset_user_id']);

        Auth::login($user->fresh(), remember: false);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('status', __('Your password has been updated.'));
    }

    private function clearOtpSession(Request $request): void
    {
        $request->session()->forget([
            'student_pw_reset_otp_hash',
            'student_pw_reset_otp_expires_at',
            'student_pw_reset_otp_attempts',
        ]);
    }
}
