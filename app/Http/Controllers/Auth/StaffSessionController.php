<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.staff-login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if ($request->user()->role === 'student') {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('Students must sign in using their index number on the main student login page.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
