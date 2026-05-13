<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffLoginRequest extends LoginRequest
{
    /**
     * Attempt staff authentication (username in `email` column, case-insensitive match).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim($this->string('email')->toString());
        $password = (string) $this->input('password');

        if ($login === '' || $password === '') {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = User::query()->where('email', $login)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [Str::lower($login)])->first();

        if ($user === null) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('No staff account uses that username. Use the staff page at :url (not the student exam login). If you just created the database, run: php artisan migrate --seed', [
                    'url' => url('/admin_login'),
                ]),
            ]);
        }

        if ($user->role === 'student') {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('Students must sign in with their index number on the student login page, not here.'),
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('This account is inactive. Contact an administrator.'),
            ]);
        }

        $hashed = $user->getAuthPassword();
        if ($hashed === null || $hashed === '') {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('This account has no password set. Contact an administrator.'),
            ]);
        }

        if (! Hash::check($password, $hashed)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('Wrong password for that username. If you forgot it, ask a super admin to reset it from Manage users.'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }
}
