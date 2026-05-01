<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $systemSettings,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'arkesel_api_key_masked' => $this->systemSettings->getMasked('arkesel_api_key'),
            'arkesel_sender_id' => $this->systemSettings->get('arkesel_sender_id') ?? '',
            'arkesel_sender_locked' => $this->systemSettings->isLocked('arkesel_sender_id'),
            'ai_api_key_masked' => $this->systemSettings->getMasked('ai_api_key'),
            'ai_model_name' => $this->systemSettings->get('ai_model_name') ?? '',
            'ai_model_locked' => $this->systemSettings->isLocked('ai_model_name'),
            'proctoring_json' => $this->systemSettings->get('default_proctoring_settings') ?? '',
            'proctoring_locked' => $this->systemSettings->isLocked('default_proctoring_settings'),
            'arkesel_key_locked' => $this->systemSettings->isLocked('arkesel_api_key'),
            'ai_key_locked' => $this->systemSettings->isLocked('ai_api_key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'arkesel_api_key' => ['nullable', 'string', 'max:2000'],
            'arkesel_sender_id' => ['nullable', 'string', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:2000'],
            'ai_model_name' => ['nullable', 'string', 'max:255'],
            'default_proctoring_settings' => ['nullable', 'string', 'max:10000'],
        ]);

        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403);

        if (! empty($validated['arkesel_api_key']) && $validated['arkesel_api_key'] !== '********') {
            $this->systemSettings->set('arkesel_api_key', $validated['arkesel_api_key'], $user);
        }
        if (array_key_exists('arkesel_sender_id', $validated) && $validated['arkesel_sender_id'] !== null) {
            $this->systemSettings->set('arkesel_sender_id', (string) $validated['arkesel_sender_id'], $user);
        }
        if (! empty($validated['ai_api_key']) && $validated['ai_api_key'] !== '********') {
            $this->systemSettings->set('ai_api_key', $validated['ai_api_key'], $user);
        }
        if (array_key_exists('ai_model_name', $validated) && $validated['ai_model_name'] !== null) {
            $this->systemSettings->set('ai_model_name', (string) $validated['ai_model_name'], $user);
        }
        if (array_key_exists('default_proctoring_settings', $validated) && $validated['default_proctoring_settings'] !== null) {
            $this->systemSettings->set('default_proctoring_settings', (string) $validated['default_proctoring_settings'], $user);
        }

        return redirect()->route('admin.settings.index')->with('status', 'Settings updated.');
    }

    public function lock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
        ]);
        $this->systemSettings->lockSetting($validated['key'], $request->user());

        return back()->with('status', 'Setting locked.');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
        ]);
        $this->systemSettings->unlockSetting($validated['key'], $request->user());

        return back()->with('status', 'Setting unlocked.');
    }
}
