<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BreakGlassEmergencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BreakGlassEmergencyController extends Controller
{
    public function __construct(
        private readonly BreakGlassEmergencyService $breakGlass,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        abort_unless($this->breakGlass->isEnabled(), 404);

        if ($this->breakGlass->hasPendingChallenge($request)) {
            return redirect()->route('breakglass.emergency.verify.form');
        }

        return view('auth.break-glass-emergency');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->breakGlass->isEnabled(), 404);

        $key = $this->breakGlass->throttleKeyForStep1($request);
        if ($this->breakGlass->tooManyAttempts($request, $key)) {
            return back()->withErrors([
                'privileged_username' => __('Too many attempts. Try again later.'),
            ]);
        }

        $validated = $request->validate([
            'privileged_username' => ['required', 'string', 'max:255'],
            'emergency_secret' => ['required', 'string', 'max:2048'],
        ]);

        $generic = back()
            ->withInput(['privileged_username' => $validated['privileged_username']])
            ->withErrors(['privileged_username' => __('Unable to complete this request.')]);

        $target = $this->breakGlass->findTargetByCredential($validated['privileged_username']);
        $secretOk = $this->breakGlass->secretMatches($validated['emergency_secret']);

        if (! $secretOk || $target === null) {
            $this->breakGlass->hitThrottle($request, $key);
            $this->breakGlass->logAttempt('break_glass_step1_failed', ['reason' => 'credential']);

            return $generic;
        }

        if ($target->role === 'student') {
            $this->breakGlass->hitThrottle($request, $key);
            $this->breakGlass->logAttempt('break_glass_step1_rejected', ['reason' => 'student']);

            return $generic;
        }

        if (! $target->is_active) {
            $this->breakGlass->hitThrottle($request, $key);
            $this->breakGlass->logAttempt('break_glass_step1_rejected', ['reason' => 'inactive']);

            return $generic;
        }

        if (! $this->breakGlass->issueChallenge($request, $target)) {
            $this->breakGlass->hitThrottle($request, $key);
            $this->breakGlass->logAttempt('break_glass_challenge_issue_failed', []);

            return $generic;
        }

        $this->breakGlass->clearThrottle($request, $key);

        return redirect()->route('breakglass.emergency.verify.form');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        abort_unless($this->breakGlass->isEnabled(), 404);

        if (! $this->breakGlass->hasPendingChallenge($request)) {
            return redirect()->route('breakglass.emergency');
        }

        return view('auth.break-glass-emergency-verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_unless($this->breakGlass->isEnabled(), 404);

        $key = $this->breakGlass->throttleKeyForVerify($request);
        if ($this->breakGlass->tooManyAttempts($request, $key)) {
            return back()->withErrors([
                'otp' => __('Too many attempts. Try again later.'),
            ]);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        if (! $this->breakGlass->verifyOtp($request, $validated['otp'])) {
            $this->breakGlass->hitThrottle($request, $key);

            return back()->withErrors([
                'otp' => __('Unable to complete this request.'),
            ]);
        }

        $owner = $this->breakGlass->resolveOwnerUser();
        if ($owner === null) {
            return redirect()->route('breakglass.emergency')
                ->withErrors(['privileged_username' => __('Unable to complete this request.')]);
        }

        Auth::guard('web')->login($owner, false);
        $request->session()->regenerate();

        $this->breakGlass->clearThrottle($request, $key);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
