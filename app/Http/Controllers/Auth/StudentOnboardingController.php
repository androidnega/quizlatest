<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StudentOnboardingController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->resolveOnboardingUser($request);
        if ($user === null) {
            return redirect()->route('login.first-time')
                ->withErrors(['index_number' => __('Your setup session expired. Start again with your index number.')]);
        }

        return view('student.onboarding', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->resolveOnboardingUser($request);
        if ($user === null) {
            return redirect()->route('login.first-time')
                ->withErrors(['index_number' => __('Your setup session expired. Start again with your index number.')]);
        }

        $nameRules = trim((string) $user->name) === ''
            ? ['required', 'string', 'max:255']
            : ['sometimes', 'string', 'max:255'];

        $validated = $request->validate([
            'name' => $nameRules,
            'password' => ['required', 'confirmed', Password::defaults()],
            'face_embedding_json' => ['required', 'string', 'max:8000'],
            'face_liveness_embedding_json' => ['required', 'string', 'max:8000'],
            'face_snapshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $embedding = $this->decodeEmbeddingJson($validated['face_embedding_json']);
        $liveness = $this->decodeEmbeddingJson($validated['face_liveness_embedding_json']);
        abort_if($embedding === null || $liveness === null, 422, __('Face data was invalid. Try capturing again.'));

        $similarity = $this->embeddingSimilarityPercent($embedding, $liveness);
        if ($similarity < 78.0 || $similarity > 99.95) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'face_embedding_json', 'face_liveness_embedding_json']))
                ->withErrors(['face' => __('Face check did not pass. Capture two live samples a few seconds apart, with your face clearly visible.')]);
        }

        $path = $user->face_image_path;
        if ($request->hasFile('face_snapshot')) {
            $ext = strtolower($request->file('face_snapshot')->getClientOriginalExtension() ?: 'jpg');
            if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $ext = 'jpg';
            }
            $dir = sprintf('profiles/user_%d', (int) $user->id);
            $path = $dir.'/portrait.'.$ext;
            Storage::disk('local')->put($path, file_get_contents($request->file('face_snapshot')->getRealPath()));
        }

        $newName = isset($validated['name']) ? trim((string) $validated['name']) : trim((string) $user->name);
        if ($newName === '') {
            return back()->withErrors(['name' => __('Please enter your full name.')]);
        }

        $user->forceFill([
            'name' => $newName,
            'password' => $validated['password'],
            'face_embedding' => $embedding,
            'face_image_path' => $path,
            'student_onboarded_at' => now(),
        ])->save();

        $request->session()->forget('student_onboarding_user_id');
        $this->clearLoginOtpSession($request);

        Auth::login($user->fresh(), remember: false);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function resolveOnboardingUser(Request $request): ?User
    {
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
     * @return list<float>|null
     */
    private function decodeEmbeddingJson(string $json): ?array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || count($data) < 18) {
            return null;
        }

        $out = [];
        foreach (array_slice($data, 0, 18) as $v) {
            if (! is_numeric($v)) {
                return null;
            }
            $out[] = (float) $v;
        }

        return $out;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function embeddingSimilarityPercent(array $a, array $b): float
    {
        if (count($a) !== count($b) || $a === []) {
            return 0.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        foreach ($a as $i => $va) {
            $vb = $b[$i];
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        $cos = $dot / (sqrt($na) * sqrt($nb));
        $cos = max(-1.0, min(1.0, $cos));

        return (float) round((($cos + 1.0) / 2.0) * 100.0, 2);
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
        ]);
    }
}
