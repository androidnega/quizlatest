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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class StudentLoginController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 8;

    /** @var string Session key: index number for returning student password step */
    private const SESSION_RETURNING_INDEX = 'student_login_returning_index';

    private const SESSION_OTP_PENDING = 'student_login_otp_pending';

    public function __construct(
        private readonly StudentFirstLoginOtpNotifier $firstLoginOtpNotifier,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->boolean('restart')) {
            $this->clearFirstLoginSession($request);
            $request->session()->forget(self::SESSION_RETURNING_INDEX);

            return redirect()->route('login');
        }

        return view('auth.login');
    }

    public function redirectLegacyFirstTime(): RedirectResponse
    {
        return redirect()->route('login');
    }

    /**
     * Step 1: index number only — routes to first-time OTP flow or password step.
     */
    public function submitIndex(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'index_number' => ['required', 'string', 'max:255'],
        ]);

        $indexNumber = trim($validated['index_number']);

        $user = User::query()
            ->where('index_number', $indexNumber)
            ->where('role', 'student')
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return back()
                ->withInput($request->only('index_number'))
                ->withErrors([
                    'index_number' => $this->genericIndexFailureMessage(),
                ]);
        }

        if ($user->student_onboarded_at === null) {
            return $this->beginFirstTimeFromVerifiedStudent($request, $user);
        }

        $this->clearFirstLoginSession($request);
        $request->session()->put(self::SESSION_RETURNING_INDEX, $indexNumber);

        return redirect()->route('login.password');
    }

    public function createPasswordStep(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_RETURNING_INDEX)) {
            return redirect()->route('login');
        }

        return view('auth.login-password', [
            'index_number' => (string) $request->session()->get(self::SESSION_RETURNING_INDEX),
        ]);
    }

    /**
     * Returning students: password using index number held in session.
     */
    public function completeReturningLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $indexNumber = $request->session()->get(self::SESSION_RETURNING_INDEX);
        if (! is_string($indexNumber) || $indexNumber === '') {
            return redirect()->route('login');
        }

        $user = User::query()
            ->where('index_number', $indexNumber)
            ->where('role', 'student')
            ->where('is_active', true)
            ->first();

        if ($user === null || $user->student_onboarded_at === null) {
            $request->session()->forget(self::SESSION_RETURNING_INDEX);

            return redirect()->route('login')
                ->withErrors(['index_number' => $this->genericIndexFailureMessage()]);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors([
                'password' => __('These credentials do not match our records.'),
            ]);
        }

        Auth::login($user, (bool) ($validated['remember'] ?? false));
        $request->session()->forget(self::SESSION_RETURNING_INDEX);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function createFirstTimePhone(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('student_first_login_needs_phone')) {
            return redirect()->route('login');
        }

        return view('auth.login-first-time-phone');
    }

    public function storeFirstTimePhone(Request $request): RedirectResponse
    {
        if (! $request->session()->get('student_first_login_needs_phone') || ! $request->session()->has('student_login_user_id')) {
            return redirect()->route('login')
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

            return redirect()->route('login')
                ->withErrors(['index_number' => __('Your session expired. Please start again.')]);
        }

        $request->session()->put('student_login_pending_phone_digits', $digits);

        return $this->reuseOrIssueOtpAndRedirect($request, $user, $digits);
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (
            ! $request->session()->has('student_login_user_id')
            || ! $request->session()->has('student_login_otp_hash')
            || ! $request->session()->get(self::SESSION_OTP_PENDING, false)
        ) {
            return redirect()->route('login');
        }

        return view('auth.login-otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        if (! $request->session()->has('student_login_user_id')) {
            return redirect()->route('login')->withErrors([
                'index_number' => __('Your session expired. Please start again.'),
            ]);
        }

        $expiresAt = $request->session()->get('student_login_otp_expires_at');
        if ($expiresAt === null || now()->timestamp > $expiresAt) {
            $this->clearOtpPayload($request);

            return redirect()->route('login')->withErrors([
                'index_number' => __('The code expired. Please try again.'),
            ]);
        }

        $attempts = (int) $request->session()->get('student_login_otp_attempts', 0);
        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $this->clearFirstLoginSession($request);

            return redirect()->route('login')->withErrors([
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
            return redirect()->route('login')->withErrors([
                'index_number' => __('Unable to complete verification.'),
            ]);
        }

        if ($user->student_onboarded_at !== null) {
            $this->clearFirstLoginSession($request);
            $request->session()->put(self::SESSION_RETURNING_INDEX, (string) $user->index_number);

            return redirect()->route('login.password')
                ->with('status', __('Your account is ready. Enter your password to continue.'));
        }

        $pending = $request->session()->pull('student_login_pending_phone_digits');
        if (is_string($pending) && $pending !== '' && StudentPhone::isGhanaMobile($pending)) {
            $user->forceFill(['phone' => $pending])->save();
        }

        $request->session()->forget('student_first_login_needs_phone');
        $request->session()->put('student_onboarding_user_id', $user->id);
        $request->session()->put('student_onboarding_verified_at', now()->timestamp);

        return redirect()->route('student.onboarding');
    }

    private function beginFirstTimeFromVerifiedStudent(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget(self::SESSION_RETURNING_INDEX);

        $request->session()->forget([
            'student_login_pending_phone_digits',
            'student_first_login_needs_phone',
        ]);

        $request->session()->put('student_login_user_id', $user->id);

        $existingPhone = StudentPhone::normalize($user->phone);
        if ($existingPhone !== null) {
            return $this->reuseOrIssueOtpAndRedirect($request, $user, $existingPhone);
        }

        $request->session()->put('student_first_login_needs_phone', true);

        return redirect()->route('login.first-time.phone');
    }

    private function reuseOrIssueOtpAndRedirect(Request $request, User $user, string $phoneDigits): RedirectResponse
    {
        return $this->issueOtpAndRedirect($request, $user, $phoneDigits);
    }

    private function issueOtpAndRedirect(Request $request, User $user, string $phoneDigits): RedirectResponse
    {
        $otp = app()->runningUnitTests()
            ? '123456'
            : str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        try {
            $this->firstLoginOtpNotifier->notify($user, $otp, $phoneDigits);
        } catch (StudentSmsVerificationUnavailable $e) {
            return back()
                ->withInput($request->only('index_number', 'phone'))
                ->withErrors(['index_number' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('student_first_login_otp_delivery_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('index_number', 'phone'))
                ->withErrors(['index_number' => __('We could not send a verification code. Contact your coordinator.')]);
        }

        $expiresAt = now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp;
        $hash = hash('sha256', $otp);
        $request->session()->put([
            'student_login_user_id' => $user->id,
            'student_login_otp_hash' => $hash,
            'student_login_otp_expires_at' => $expiresAt,
            'student_login_otp_attempts' => 0,
            self::SESSION_OTP_PENDING => true,
        ]);

        return redirect()->route('login.otp');
    }

    private function clearOtpPayload(Request $request): void
    {
        $request->session()->forget([
            'student_login_otp_hash',
            'student_login_otp_expires_at',
            'student_login_otp_attempts',
            self::SESSION_OTP_PENDING,
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
            'student_onboarding_verified_at',
        ]);
    }

    private function genericIndexFailureMessage(): string
    {
        return __('We could not continue with those details. Check your index number and try again, or contact your coordinator.');
    }
}
