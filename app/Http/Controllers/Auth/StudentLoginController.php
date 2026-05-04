<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentFirstLoginOtpNotifier;
use App\Support\StudentPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class StudentLoginController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 8;

    public function __construct(
        private readonly StudentFirstLoginOtpNotifier $firstLoginOtpNotifier,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Returning students: index number or phone + password.
     */
    public function loginWithPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $identifier = trim($validated['identifier']);
        $phoneNorm = StudentPhone::normalize($identifier);

        $user = User::query()
            ->where('role', 'student')
            ->where(function ($q) use ($identifier, $phoneNorm) {
                $q->where('index_number', $identifier);
                if ($phoneNorm !== null) {
                    $q->orWhere('phone', $identifier)->orWhere('phone', $phoneNorm);
                }
            })
            ->first();

        if ($user === null || ! $user->is_active) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => __('No active student account matches these credentials.')]);
        }

        if ($user->student_onboarded_at === null) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors([
                    'identifier' => __('This account has not finished first-time setup. Use “First-time sign-in” below.'),
                ]);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => __('No active student account matches these credentials.')]);
        }

        Auth::login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function createFirstTime(): View
    {
        return view('auth.login-first-time');
    }

    public function createFirstTimePhone(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('student_first_login_needs_phone')) {
            return redirect()->route('login.first-time');
        }

        return view('auth.login-first-time-phone');
    }

    /**
     * First-time flow: index number → OTP to existing phone, or collect phone then OTP.
     */
    public function storeFirstTime(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'index_number' => ['required', 'string', 'max:255'],
        ]);

        $indexNumber = trim($validated['index_number']);

        $user = User::query()
            ->where('index_number', $indexNumber)
            ->where('role', 'student')
            ->first();

        if ($user === null || ! $user->is_active) {
            return back()
                ->withInput($request->only('index_number'))
                ->withErrors(['index_number' => __('No active student account matches this index number.')]);
        }

        if ($user->student_onboarded_at !== null) {
            return back()
                ->withInput($request->only('index_number'))
                ->withErrors([
                    'index_number' => __('You have already completed setup. Sign in with your index number or phone and password.'),
                ]);
        }

        $request->session()->forget([
            'student_login_pending_phone_digits',
            'student_first_login_needs_phone',
        ]);

        $request->session()->put('student_login_user_id', $user->id);

        $existingPhone = StudentPhone::normalize($user->phone);
        if ($existingPhone !== null) {
            return $this->issueOtpAndRedirect($request, $user, $existingPhone);
        }

        $request->session()->put('student_first_login_needs_phone', true);

        return redirect()->route('login.first-time.phone');
    }

    public function storeFirstTimePhone(Request $request): RedirectResponse
    {
        if (! $request->session()->get('student_first_login_needs_phone') || ! $request->session()->has('student_login_user_id')) {
            return redirect()->route('login.first-time')
                ->withErrors(['index_number' => __('Your session expired. Please start again.')]);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $digits = StudentPhone::normalize($validated['phone']);
        if ($digits === null || ! StudentPhone::isGhanaMobile($digits)) {
            return back()->withErrors(['phone' => __('Enter a valid Ghana mobile number (e.g. 024… or +233…).')]);
        }

        $user = User::query()->find((int) $request->session()->get('student_login_user_id'));
        if ($user === null || $user->role !== 'student' || ! $user->is_active || $user->student_onboarded_at !== null) {
            $this->clearFirstLoginSession($request);

            return redirect()->route('login.first-time')
                ->withErrors(['index_number' => __('Your session expired. Please start again.')]);
        }

        $request->session()->put('student_login_pending_phone_digits', $digits);

        return $this->issueOtpAndRedirect($request, $user, $digits);
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('student_login_user_id') || ! $request->session()->has('student_login_otp_hash')) {
            return redirect()->route('login.first-time');
        }

        return view('auth.login-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        if (! $request->session()->has('student_login_user_id')) {
            return redirect()->route('login.first-time')->withErrors([
                'index_number' => __('Your session expired. Please start again.'),
            ]);
        }

        $expiresAt = $request->session()->get('student_login_otp_expires_at');
        if ($expiresAt === null || now()->timestamp > $expiresAt) {
            $this->clearOtpPayload($request);

            return redirect()->route('login.first-time')->withErrors([
                'index_number' => __('The code expired. Please try again.'),
            ]);
        }

        $attempts = (int) $request->session()->get('student_login_otp_attempts', 0);
        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $this->clearFirstLoginSession($request);

            return redirect()->route('login.first-time')->withErrors([
                'index_number' => __('Too many incorrect attempts. Please start again.'),
            ]);
        }
        $request->session()->put('student_login_otp_attempts', $attempts + 1);

        $hash = $request->session()->get('student_login_otp_hash');
        $providedHash = hash('sha256', $request->input('otp'));

        if (! hash_equals((string) $hash, $providedHash)) {
            return back()->withErrors(['otp' => __('Invalid code.')]);
        }

        $userId = $request->session()->get('student_login_user_id');
        $user = User::query()->find($userId);

        $this->clearOtpPayload($request);

        if ($user === null || $user->role !== 'student' || ! $user->is_active) {
            return redirect()->route('login.first-time')->withErrors([
                'index_number' => __('Unable to complete verification.'),
            ]);
        }

        if ($user->student_onboarded_at !== null) {
            return redirect()->route('login')
                ->withErrors(['identifier' => __('Use your password to sign in.')]);
        }

        $pending = $request->session()->pull('student_login_pending_phone_digits');
        if (is_string($pending) && $pending !== '' && StudentPhone::isGhanaMobile($pending)) {
            $user->forceFill(['phone' => $pending])->save();
        }

        $request->session()->forget('student_first_login_needs_phone');
        $request->session()->put('student_onboarding_user_id', $user->id);

        return redirect()->route('student.onboarding');
    }

    private function issueOtpAndRedirect(Request $request, User $user, string $phoneDigits): RedirectResponse
    {
        $otp = app()->runningUnitTests()
            ? '123456'
            : str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        try {
            $this->firstLoginOtpNotifier->notify($user, $otp, $phoneDigits);
        } catch (\Throwable $e) {
            Log::error('student_first_login_otp_delivery_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('index_number', 'phone'))
                ->withErrors(['index_number' => __('We could not send a verification code. Contact your coordinator.')]);
        }

        $request->session()->put([
            'student_login_user_id' => $user->id,
            'student_login_otp_hash' => hash('sha256', $otp),
            'student_login_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
            'student_login_otp_attempts' => 0,
        ]);

        return redirect()->route('login.otp');
    }

    private function clearOtpPayload(Request $request): void
    {
        $request->session()->forget([
            'student_login_otp_hash',
            'student_login_otp_expires_at',
            'student_login_otp_attempts',
        ]);
    }

    private function clearFirstLoginSession(Request $request): void
    {
        $request->session()->forget([
            'student_login_user_id',
            'student_login_otp_hash',
            'student_login_otp_expires_at',
            'student_login_otp_attempts',
            'student_login_pending_phone_digits',
            'student_first_login_needs_phone',
        ]);
    }
}
