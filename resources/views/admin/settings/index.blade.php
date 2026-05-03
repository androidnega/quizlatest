<x-layouts.admin>
    <x-slot name="title">System settings</x-slot>
    <x-slot name="subtitle">Institution policy and integrations. Secrets are encrypted; API keys are never shown in full.</x-slot>

    @php
        $lockable = [
            'enable_otp' => 'Enable OTP',
            'otp_expiry' => 'OTP expiry (seconds)',
            'otp_attempt_limit' => 'OTP attempt limit',
            'enable_sms' => 'Enable SMS',
            'arkesel_api_key' => 'Arkesel API key',
            'arkesel_sender_id' => 'Arkesel sender ID',
            'enable_proctoring' => 'Enable proctoring',
            'face_verification_required' => 'Face verification required',
            'phone_detection_enabled' => 'Phone detection enabled',
            'fullscreen_required' => 'Fullscreen required',
            'auto_submit_enabled' => 'Auto-submit enabled',
            'default_proctoring_settings' => 'Default proctoring JSON',
            'enable_ai' => 'Enable AI',
            'ai_api_key' => 'AI API key',
            'ai_model_name' => 'AI model name',
            'enable_student_practice_quizzes' => 'Enable student practice module',
            'enable_course_material_uploads' => 'Enable course material uploads',
            'enable_ai_summary' => 'Enable AI study summaries (practice)',
            'enable_ai_practice_quiz_generation' => 'Enable AI practice quiz generation',
            'practice_quiz_daily_limit' => 'Practice AI quiz daily limit (per student)',
            'practice_quiz_monthly_limit' => 'Practice AI quiz monthly limit (per student)',
            'practice_ai_token_limit_per_student' => 'Practice AI tokens/month per student',
            'practice_ai_provider' => 'Practice AI provider slug',
            'deepseek_api_key' => 'DeepSeek API key',
            'deepseek_model' => 'DeepSeek model',
            'allow_examiner_practice_overview' => 'Allow examiner practice analytics',
        ];
    @endphp

    <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-8">
        @csrf
        @method('PUT')

        <section class="qs-surface rounded-xl p-6 space-y-4">
            <h3 class="text-base font-semibold text-qs-text">OTP</h3>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_otp" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_otp', $enable_otp)) @disabled($lock_enable_otp) />
                Enable OTP for exam start
            </label>
            @if ($lock_enable_otp)
                <p class="text-xs text-qs-muted">Locked.</p>
            @endif
            <div>
                <label class="mb-1 block text-sm text-qs-muted">OTP expiry (seconds)</label>
                <input type="number" name="otp_expiry" min="60" max="7200" value="{{ old('otp_expiry', $otp_expiry) }}"
                    class="qs-input max-w-xs" @disabled($lock_otp_expiry) />
            </div>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">OTP verify attempt limit</label>
                <input type="number" name="otp_attempt_limit" min="1" max="20" value="{{ old('otp_attempt_limit', $otp_attempt_limit) }}"
                    class="qs-input max-w-xs" @disabled($lock_otp_attempt_limit) />
            </div>
        </section>

        <section class="qs-surface rounded-xl p-6 space-y-4">
            <h3 class="text-base font-semibold text-qs-text">SMS (Arkesel)</h3>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_sms" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_sms', $enable_sms)) @disabled($lock_enable_sms) />
                Enable SMS delivery
            </label>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">API key</label>
                <input type="password" name="arkesel_api_key" autocomplete="off" class="qs-input max-w-xl"
                    placeholder="{{ $arkesel_api_key_masked ? '•••••••• (enter new to replace)' : 'Not set' }}"
                    @disabled($arkesel_key_locked) />
            </div>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">Sender ID</label>
                <input type="text" name="arkesel_sender_id" value="{{ old('arkesel_sender_id', $arkesel_sender_id) }}"
                    class="qs-input max-w-xl" @disabled($arkesel_sender_locked) />
            </div>
        </section>

        <section class="qs-surface rounded-xl p-6 space-y-4">
            <h3 class="text-base font-semibold text-qs-text">Proctoring policy</h3>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_proctoring" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_proctoring', $enable_proctoring)) @disabled($lock_enable_proctoring) />
                Enable proctoring
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="face_verification_required" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('face_verification_required', $face_verification_required)) @disabled($lock_face_verification_required) />
                Face verification required
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="phone_detection_enabled" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('phone_detection_enabled', $phone_detection_enabled)) @disabled($lock_phone_detection_enabled) />
                Phone detection enabled
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="fullscreen_required" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('fullscreen_required', $fullscreen_required)) @disabled($lock_fullscreen_required) />
                Fullscreen required
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="auto_submit_enabled" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('auto_submit_enabled', $auto_submit_enabled)) @disabled($lock_auto_submit_enabled) />
                Auto-submit enabled
            </label>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">Default exam proctoring (JSON)</label>
                <textarea name="default_proctoring_settings" rows="8" class="qs-input font-mono text-xs"
                    @disabled($proctoring_locked)>{{ old('default_proctoring_settings', $proctoring_json) }}</textarea>
            </div>
        </section>

        <section class="qs-surface rounded-xl p-6 space-y-4">
            <h3 class="text-base font-semibold text-qs-text">AI</h3>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_ai" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_ai', $enable_ai)) @disabled($lock_enable_ai) />
                Enable AI integrations
            </label>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">API key</label>
                <input type="password" name="ai_api_key" autocomplete="off" class="qs-input max-w-xl"
                    placeholder="{{ $ai_api_key_masked ? '•••••••• (enter new to replace)' : 'Not set' }}"
                    @disabled($ai_key_locked) />
            </div>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">Model name</label>
                <input type="text" name="ai_model_name" value="{{ old('ai_model_name', $ai_model_name) }}"
                    class="qs-input max-w-xl" @disabled($ai_model_locked) />
            </div>
        </section>

        <section class="qs-surface rounded-xl p-6 space-y-4">
            <h3 class="text-base font-semibold text-qs-text">Practice &amp; course materials (unofficial)</h3>
            <p class="text-xs text-qs-muted">Student practice is separate from official exams and proctoring. DeepSeek key is stored encrypted and never shown in full.</p>

            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_student_practice_quizzes" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_student_practice_quizzes', $enable_student_practice_quizzes)) @disabled($lock_enable_student_practice_quizzes) />
                Enable student practice area (master switch)
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_course_material_uploads" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_course_material_uploads', $enable_course_material_uploads)) @disabled($lock_enable_course_material_uploads) />
                Examiners can upload course materials
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_ai_summary" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_ai_summary', $enable_ai_summary)) @disabled($lock_enable_ai_summary) />
                Students can request AI study summaries
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="enable_ai_practice_quiz_generation" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('enable_ai_practice_quiz_generation', $enable_ai_practice_quiz_generation)) @disabled($lock_enable_ai_practice_quiz_generation) />
                Students can generate AI practice quizzes
            </label>
            <label class="flex items-center gap-3 text-sm text-qs-text">
                <input type="checkbox" name="allow_examiner_practice_overview" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                    @checked(old('allow_examiner_practice_overview', $allow_examiner_practice_overview)) @disabled($lock_allow_examiner_practice_overview) />
                Examiners can view practice analytics (aggregates only)
            </label>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm text-qs-muted">Daily AI quiz generations / student</label>
                    <input type="number" name="practice_quiz_daily_limit" min="0" max="500" value="{{ old('practice_quiz_daily_limit', $practice_quiz_daily_limit) }}" class="qs-input max-w-xs" @disabled($lock_practice_quiz_daily_limit) />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-qs-muted">Monthly AI quiz generations / student</label>
                    <input type="number" name="practice_quiz_monthly_limit" min="0" max="5000" value="{{ old('practice_quiz_monthly_limit', $practice_quiz_monthly_limit) }}" class="qs-input max-w-xs" @disabled($lock_practice_quiz_monthly_limit) />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-qs-muted">Practice AI tokens / student / month</label>
                    <input type="number" name="practice_ai_token_limit_per_student" min="0" value="{{ old('practice_ai_token_limit_per_student', $practice_ai_token_limit_per_student) }}" class="qs-input max-w-xs" @disabled($lock_practice_ai_token_limit_per_student) />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">Provider identifier</label>
                <input type="text" name="practice_ai_provider" value="{{ old('practice_ai_provider', $practice_ai_provider) }}" class="qs-input max-w-md" @disabled($lock_practice_ai_provider) />
            </div>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">DeepSeek API key</label>
                <input type="password" name="deepseek_api_key" autocomplete="off" class="qs-input max-w-xl"
                    placeholder="{{ $deepseek_api_key_masked ? '•••••••• (enter new to replace)' : 'Not set' }}"
                    @disabled($lock_deepseek_api_key) />
            </div>
            <div>
                <label class="mb-1 block text-sm text-qs-muted">DeepSeek model</label>
                <input type="text" name="deepseek_model" value="{{ old('deepseek_model', $deepseek_model) }}" class="qs-input max-w-xl" @disabled($lock_deepseek_model) />
            </div>
        </section>

        <div class="flex gap-3">
            <button type="submit" class="qs-btn-primary">Save changes</button>
        </div>
    </form>

    <section class="qs-surface mt-10 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-qs-text">Lock / unlock</h3>
        <p class="mb-4 mt-1 text-xs text-qs-muted">When locked, only an admin can change the value.</p>
        <ul class="space-y-2 text-sm">
            @foreach ($lockable as $k => $label)
                <li class="flex flex-wrap items-center gap-2 border-t border-qs-soft/50 pt-2 first:border-0 first:pt-0">
                    <span class="min-w-[10rem] text-qs-text">{{ $label }}</span>
                    <form method="post" action="{{ route('admin.settings.lock') }}" class="inline">@csrf
                        <input type="hidden" name="key" value="{{ $k }}" />
                        <button type="submit" class="rounded border border-qs-soft bg-qs-card px-2 py-1 text-xs text-qs-text hover:bg-qs-bg">Lock</button>
                    </form>
                    <form method="post" action="{{ route('admin.settings.unlock') }}" class="inline">@csrf
                        <input type="hidden" name="key" value="{{ $k }}" />
                        <button type="submit" class="rounded border border-qs-soft bg-qs-bg px-2 py-1 text-xs text-qs-text hover:bg-qs-card">Unlock</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>
</x-layouts.admin>
