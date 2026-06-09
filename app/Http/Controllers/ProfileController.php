<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\OptimizedImageService;
use App\Services\SensitiveStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        if ($user !== null && $user->role === 'student') {
            $user->load([
                'university',
                'program.department.faculty',
                'level',
                'classroom.academicYearStruct',
            ]);
        }

        return view('profile.edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $user->fill($request->only(['phone']));
            $this->syncStudentProfilePhoto(
                $request,
                $user,
                app(SensitiveStorageService::class),
                app(OptimizedImageService::class),
            );
        } else {
            $user->fill($request->validated());
        }

        if ($user->role !== 'student' && $user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function syncStudentProfilePhoto(
        Request $request,
        User $user,
        SensitiveStorageService $storage,
        OptimizedImageService $images,
    ): void {
        if ($request->boolean('remove_profile_photo') && filled($user->face_image_path)) {
            $storage->deleteFromAnywhere((string) $user->face_image_path);
            $user->face_image_path = null;

            return;
        }

        if (! $request->hasFile('profile_photo')) {
            return;
        }

        $file = $request->file('profile_photo');
        if ($file === null) {
            return;
        }

        $relativePath = 'proctoring/face-templates/'.$user->id.'/profile.jpg';

        if (filled($user->face_image_path) && $user->face_image_path !== $relativePath) {
            $storage->deleteFromAnywhere((string) $user->face_image_path);
        }

        try {
            $binary = $images->encodeSquarePortraitJpeg($file, 512, 250 * 1024);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'profile_photo' => $exception->getMessage(),
            ]);
        }

        Storage::disk('local')->put($relativePath, $binary);
        $user->face_image_path = $relativePath;
    }
}
