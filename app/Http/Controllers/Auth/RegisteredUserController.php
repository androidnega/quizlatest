<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $faceEmbedding = json_decode((string) $request->input('face_embedding', ''), true);
        if (! is_array($faceEmbedding)) {
            $faceEmbedding = null;
        }

        $request->merge([
            'face_embedding' => $faceEmbedding,
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'face_embedding' => ['nullable', 'array'],
            'face_embedding.*' => ['numeric'],
            'face_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $faceImagePath = null;
        if ($request->hasFile('face_image')) {
            $faceImagePath = $request->file('face_image')->store('proctoring/face-templates', 'public');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'face_embedding' => $request->input('face_embedding'),
            'face_image_path' => $faceImagePath,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
