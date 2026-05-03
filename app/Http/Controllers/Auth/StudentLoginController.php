<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class StudentLoginController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
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

        $otp = app()->runningUnitTests()
            ? '123456'
            : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put([
            'student_login_user_id' => $user->id,
            'student_login_otp_hash' => hash('sha256', $otp),
            'student_login_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
        ]);

        Log::info('[student login OTP — dev]', [
            'index_number' => $user->index_number,
            'otp' => $otp,
            'user_id' => $user->id,
        ]);

        return redirect()->route('login.otp');
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('student_login_user_id')) {
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
                'index_number' => __('Your session expired. Please sign in again.'),
            ]);
        }

        $expiresAt = $request->session()->get('student_login_otp_expires_at');
        if ($expiresAt === null || now()->timestamp > $expiresAt) {
            $this->clearOtpSession($request);

            return redirect()->route('login')->withErrors([
                'index_number' => __('The code expired. Please sign in again.'),
            ]);
        }

        $hash = $request->session()->get('student_login_otp_hash');
        $providedHash = hash('sha256', $request->input('otp'));

        if (! hash_equals((string) $hash, $providedHash)) {
            return back()->withErrors(['otp' => __('Invalid code.')]);
        }

        $userId = $request->session()->get('student_login_user_id');
        $user = User::query()->find($userId);

        $this->clearOtpSession($request);

        if ($user === null || $user->role !== 'student' || ! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'index_number' => __('Unable to complete sign in.'),
            ]);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function clearOtpSession(Request $request): void
    {
        $request->session()->forget([
            'student_login_user_id',
            'student_login_otp_hash',
            'student_login_otp_expires_at',
        ]);
    }
}
