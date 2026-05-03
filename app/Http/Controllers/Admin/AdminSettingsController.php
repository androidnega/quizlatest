<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        $this->authorize('manageSystemSettings');

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

            'enable_otp' => $this->systemSettings->getBool('enable_otp', true),
            'otp_expiry' => (string) ($this->systemSettings->getInt('otp_expiry', 0) ?: config('exam_otp.ttl_seconds', 300)),
            'otp_attempt_limit' => (string) ($this->systemSettings->getInt('otp_attempt_limit', 0) ?: config('exam_otp.max_verify_attempts', 3)),
            'enable_sms' => $this->systemSettings->getBool('enable_sms', true),
            'enable_proctoring' => $this->systemSettings->getBool('enable_proctoring', true),
            'face_verification_required' => $this->systemSettings->getBool('face_verification_required', true),
            'phone_detection_enabled' => $this->systemSettings->getBool('phone_detection_enabled', true),
            'fullscreen_required' => $this->systemSettings->getBool('fullscreen_required', true),
            'auto_submit_enabled' => $this->systemSettings->getBool('auto_submit_enabled', true),
            'enable_ai' => $this->systemSettings->getBool('enable_ai', true),

            'lock_enable_otp' => $this->systemSettings->isLocked('enable_otp'),
            'lock_otp_expiry' => $this->systemSettings->isLocked('otp_expiry'),
            'lock_otp_attempt_limit' => $this->systemSettings->isLocked('otp_attempt_limit'),
            'lock_enable_sms' => $this->systemSettings->isLocked('enable_sms'),
            'lock_enable_proctoring' => $this->systemSettings->isLocked('enable_proctoring'),
            'lock_face_verification_required' => $this->systemSettings->isLocked('face_verification_required'),
            'lock_phone_detection_enabled' => $this->systemSettings->isLocked('phone_detection_enabled'),
            'lock_fullscreen_required' => $this->systemSettings->isLocked('fullscreen_required'),
            'lock_auto_submit_enabled' => $this->systemSettings->isLocked('auto_submit_enabled'),
            'lock_enable_ai' => $this->systemSettings->isLocked('enable_ai'),

            'enable_student_practice_quizzes' => $this->systemSettings->getBool('enable_student_practice_quizzes', false),
            'enable_course_material_uploads' => $this->systemSettings->getBool('enable_course_material_uploads', false),
            'enable_ai_summary' => $this->systemSettings->getBool('enable_ai_summary', false),
            'enable_ai_practice_quiz_generation' => $this->systemSettings->getBool('enable_ai_practice_quiz_generation', false),
            'practice_quiz_daily_limit' => (string) $this->systemSettings->getInt('practice_quiz_daily_limit', 5),
            'practice_quiz_monthly_limit' => (string) $this->systemSettings->getInt('practice_quiz_monthly_limit', 50),
            'practice_ai_token_limit_per_student' => (string) $this->systemSettings->getInt('practice_ai_token_limit_per_student', 100_000),
            'practice_ai_provider' => $this->systemSettings->get('practice_ai_provider') ?? 'deepseek',
            'deepseek_api_key_masked' => $this->systemSettings->getMasked('deepseek_api_key'),
            'deepseek_model' => $this->systemSettings->get('deepseek_model') ?? 'deepseek-chat',
            'allow_examiner_practice_overview' => $this->systemSettings->getBool('allow_examiner_practice_overview', false),

            'lock_enable_student_practice_quizzes' => $this->systemSettings->isLocked('enable_student_practice_quizzes'),
            'lock_enable_course_material_uploads' => $this->systemSettings->isLocked('enable_course_material_uploads'),
            'lock_enable_ai_summary' => $this->systemSettings->isLocked('enable_ai_summary'),
            'lock_enable_ai_practice_quiz_generation' => $this->systemSettings->isLocked('enable_ai_practice_quiz_generation'),
            'lock_practice_quiz_daily_limit' => $this->systemSettings->isLocked('practice_quiz_daily_limit'),
            'lock_practice_quiz_monthly_limit' => $this->systemSettings->isLocked('practice_quiz_monthly_limit'),
            'lock_practice_ai_token_limit_per_student' => $this->systemSettings->isLocked('practice_ai_token_limit_per_student'),
            'lock_practice_ai_provider' => $this->systemSettings->isLocked('practice_ai_provider'),
            'lock_deepseek_api_key' => $this->systemSettings->isLocked('deepseek_api_key'),
            'lock_deepseek_model' => $this->systemSettings->isLocked('deepseek_model'),
            'lock_allow_examiner_practice_overview' => $this->systemSettings->isLocked('allow_examiner_practice_overview'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manageSystemSettings');

        $validated = $request->validate([
            'arkesel_api_key' => ['nullable', 'string', 'max:2000'],
            'arkesel_sender_id' => ['nullable', 'string', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:2000'],
            'ai_model_name' => ['nullable', 'string', 'max:255'],
            'default_proctoring_settings' => ['nullable', 'string', 'max:10000'],
            'otp_expiry' => ['nullable', 'integer', 'min:60', 'max:7200'],
            'otp_attempt_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'practice_quiz_daily_limit' => ['nullable', 'integer', 'min:0', 'max:500'],
            'practice_quiz_monthly_limit' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'practice_ai_token_limit_per_student' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'practice_ai_provider' => ['nullable', 'string', 'max:64'],
            'deepseek_api_key' => ['nullable', 'string', 'max:2000'],
            'deepseek_model' => ['nullable', 'string', 'max:128'],
        ]);

        $user = $request->user();

        if (! empty($validated['arkesel_api_key']) && $validated['arkesel_api_key'] !== '********' && ! $this->systemSettings->isLocked('arkesel_api_key')) {
            $this->systemSettings->set('arkesel_api_key', $validated['arkesel_api_key'], $user);
        }
        if (array_key_exists('arkesel_sender_id', $validated) && $validated['arkesel_sender_id'] !== null && ! $this->systemSettings->isLocked('arkesel_sender_id')) {
            $this->systemSettings->set('arkesel_sender_id', (string) $validated['arkesel_sender_id'], $user);
        }
        if (! empty($validated['ai_api_key']) && $validated['ai_api_key'] !== '********' && ! $this->systemSettings->isLocked('ai_api_key')) {
            $this->systemSettings->set('ai_api_key', $validated['ai_api_key'], $user);
        }
        if (array_key_exists('ai_model_name', $validated) && $validated['ai_model_name'] !== null && ! $this->systemSettings->isLocked('ai_model_name')) {
            $this->systemSettings->set('ai_model_name', (string) $validated['ai_model_name'], $user);
        }
        if (array_key_exists('default_proctoring_settings', $validated) && $validated['default_proctoring_settings'] !== null && ! $this->systemSettings->isLocked('default_proctoring_settings')) {
            $this->systemSettings->set('default_proctoring_settings', (string) $validated['default_proctoring_settings'], $user);
        }

        $this->setBoolIfUnlocked('enable_otp', $request->boolean('enable_otp'), $user);
        $this->setBoolIfUnlocked('enable_sms', $request->boolean('enable_sms'), $user);
        $this->setBoolIfUnlocked('enable_proctoring', $request->boolean('enable_proctoring'), $user);
        $this->setBoolIfUnlocked('face_verification_required', $request->boolean('face_verification_required'), $user);
        $this->setBoolIfUnlocked('phone_detection_enabled', $request->boolean('phone_detection_enabled'), $user);
        $this->setBoolIfUnlocked('fullscreen_required', $request->boolean('fullscreen_required'), $user);
        $this->setBoolIfUnlocked('auto_submit_enabled', $request->boolean('auto_submit_enabled'), $user);
        $this->setBoolIfUnlocked('enable_ai', $request->boolean('enable_ai'), $user);

        if (array_key_exists('otp_expiry', $validated) && $validated['otp_expiry'] !== null && ! $this->systemSettings->isLocked('otp_expiry')) {
            $this->systemSettings->set('otp_expiry', (string) (int) $validated['otp_expiry'], $user);
        }
        if (array_key_exists('otp_attempt_limit', $validated) && $validated['otp_attempt_limit'] !== null && ! $this->systemSettings->isLocked('otp_attempt_limit')) {
            $this->systemSettings->set('otp_attempt_limit', (string) (int) $validated['otp_attempt_limit'], $user);
        }

        $this->setBoolIfUnlocked('enable_student_practice_quizzes', $request->boolean('enable_student_practice_quizzes'), $user);
        $this->setBoolIfUnlocked('enable_course_material_uploads', $request->boolean('enable_course_material_uploads'), $user);
        $this->setBoolIfUnlocked('enable_ai_summary', $request->boolean('enable_ai_summary'), $user);
        $this->setBoolIfUnlocked('enable_ai_practice_quiz_generation', $request->boolean('enable_ai_practice_quiz_generation'), $user);
        $this->setBoolIfUnlocked('allow_examiner_practice_overview', $request->boolean('allow_examiner_practice_overview'), $user);

        if (array_key_exists('practice_quiz_daily_limit', $validated) && $validated['practice_quiz_daily_limit'] !== null && ! $this->systemSettings->isLocked('practice_quiz_daily_limit')) {
            $this->systemSettings->set('practice_quiz_daily_limit', (string) (int) $validated['practice_quiz_daily_limit'], $user);
        }
        if (array_key_exists('practice_quiz_monthly_limit', $validated) && $validated['practice_quiz_monthly_limit'] !== null && ! $this->systemSettings->isLocked('practice_quiz_monthly_limit')) {
            $this->systemSettings->set('practice_quiz_monthly_limit', (string) (int) $validated['practice_quiz_monthly_limit'], $user);
        }
        if (array_key_exists('practice_ai_token_limit_per_student', $validated) && $validated['practice_ai_token_limit_per_student'] !== null && ! $this->systemSettings->isLocked('practice_ai_token_limit_per_student')) {
            $this->systemSettings->set('practice_ai_token_limit_per_student', (string) (int) $validated['practice_ai_token_limit_per_student'], $user);
        }
        if (array_key_exists('practice_ai_provider', $validated) && $validated['practice_ai_provider'] !== null && ! $this->systemSettings->isLocked('practice_ai_provider')) {
            $this->systemSettings->set('practice_ai_provider', trim((string) $validated['practice_ai_provider']), $user);
        }
        if (! empty($validated['deepseek_api_key']) && $validated['deepseek_api_key'] !== '********' && ! $this->systemSettings->isLocked('deepseek_api_key')) {
            $this->systemSettings->set('deepseek_api_key', $validated['deepseek_api_key'], $user);
        }
        if (array_key_exists('deepseek_model', $validated) && $validated['deepseek_model'] !== null && ! $this->systemSettings->isLocked('deepseek_model')) {
            $this->systemSettings->set('deepseek_model', trim((string) $validated['deepseek_model']), $user);
        }

        return redirect()->route('admin.settings.index')->with('status', 'Settings updated.');
    }

    public function lock(Request $request): RedirectResponse
    {
        $this->authorize('manageSystemSettings');

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
        ]);
        $this->systemSettings->lockSetting($validated['key'], $request->user());

        return back()->with('status', 'Setting locked.');
    }

    public function unlock(Request $request): RedirectResponse
    {
        $this->authorize('manageSystemSettings');

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
        ]);
        $this->systemSettings->unlockSetting($validated['key'], $request->user());

        return back()->with('status', 'Setting unlocked.');
    }

    private function setBoolIfUnlocked(string $key, bool $value, User $user): void
    {
        if ($this->systemSettings->isLocked($key)) {
            return;
        }
        $this->systemSettings->set($key, $value ? '1' : '0', $user);
    }
}
