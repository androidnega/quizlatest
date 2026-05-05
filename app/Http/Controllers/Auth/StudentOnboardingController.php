<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StudentOnboardingController extends Controller
{
    private const ONBOARDING_SESSION_TTL_MINUTES = 30;

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->resolveOnboardingUser($request);
        if ($user === null) {
            return redirect()->route('login')
                ->withErrors(['index_number' => __('Your setup session expired. Start again with your index number.')]);
        }

        return view('student.onboarding', [
            'user' => $user,
            'draft' => $this->onboardingDraft($request),
        ]);
    }

    public function saveDraft(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->resolveOnboardingUser($request);
        if ($user === null) {
            return response()->json(['message' => __('Session expired.')], 401);
        }

        $validated = $request->validate([
            'step' => ['nullable', 'integer', 'min:1', 'max:2'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $draft = $this->onboardingDraft($request);
        if (isset($validated['step'])) {
            $draft['step'] = $validated['step'];
        }
        if (array_key_exists('name', $validated)) {
            $draft['name'] = trim((string) ($validated['name'] ?? ''));
        }

        $request->session()->put('student_onboarding_draft', $draft);

        return response()->json(['saved' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->resolveOnboardingUser($request);
        if ($user === null) {
            return redirect()->route('login')
                ->withErrors(['index_number' => __('Your setup session expired. Start again with your index number.')]);
        }

        $nameRules = trim((string) $user->name) === ''
            ? ['required', 'string', 'max:255']
            : ['sometimes', 'string', 'max:255'];

        $request->validate([
            'name' => $nameRules,
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $newName = isset($request->name) ? trim((string) $request->name) : trim((string) $user->name);
        if ($newName === '') {
            return back()->withErrors(['name' => __('Please enter your full name.')]);
        }

        $user->forceFill([
            'name' => $newName,
            'password' => $request->input('password'),
            'student_onboarded_at' => now(),
        ])->save();

        $request->session()->forget('student_onboarding_user_id');
        $request->session()->forget('student_onboarding_draft');
        $this->clearLoginOtpSession($request);

        Auth::login($user->fresh(), remember: false);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function resolveOnboardingUser(Request $request): ?User
    {
        $verifiedAt = $request->session()->get('student_onboarding_verified_at');
        if (! is_int($verifiedAt) && ! is_numeric($verifiedAt)) {
            return null;
        }
        if (now()->timestamp > ((int) $verifiedAt + (self::ONBOARDING_SESSION_TTL_MINUTES * 60))) {
            return null;
        }

        $id = $request->session()->get('student_onboarding_user_id');
        if (! is_int($id) && ! is_numeric($id)) {
            return null;
        }

        $user = User::query()->find((int) $id);
        if ($user === null || $user->role !== 'student' || ! $user->is_active) {
            return null;
        }

        if ($user->student_onboarded_at !== null) {
            return null;
        }

        return $user;
    }

    /**
     * @return array{step?:int,name?:string}
     */
    private function onboardingDraft(Request $request): array
    {
        $draft = $request->session()->get('student_onboarding_draft');
        if (! is_array($draft)) {
            return [];
        }

        return $draft;
    }

    private function clearLoginOtpSession(Request $request): void
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
}
