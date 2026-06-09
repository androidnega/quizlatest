<x-layouts.admin :white-workspace="true">
    <x-slot name="title">System settings</x-slot>
    <x-slot name="subtitle">Institution policy and integrations. Secrets are encrypted; API keys are never shown in full.</x-slot>

    @php
        $setPanel = 'rounded-xl border border-slate-200 bg-white p-6 space-y-4 shadow-sm';
        $setInput = 'mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/25 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';
        $setCheck = 'h-4 w-4 shrink-0 rounded border-slate-300 bg-white text-emerald-600 focus:ring-emerald-500/35';
    @endphp

    <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-8">
        @csrf
        @method('PUT')

        <section class="{{ $setPanel }}">
            <h3 class="text-base font-semibold text-slate-900">{{ __('Infrastructure (cPanel / shared hosting)') }}</h3>
            <p class="text-sm text-slate-600">
                {{ __('Exam runtime primitives (locks, counters, OTP store) run on the application cache and database. If live sockets are disabled or unavailable, the system will use polling.') }}
            </p>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_live_sockets" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_live_sockets', $enable_live_sockets)) @disabled($lock_enable_live_sockets) />
                {{ __('Enable WebSockets (Reverb) for live exam session updates') }}
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="allow_polling_fallback" value="1" class="{{ $setCheck }}"
                    @checked(old('allow_polling_fallback', $allow_polling_fallback)) @disabled($lock_allow_polling_fallback) />
                {{ __('Allow silent HTTP polling when WebSockets fail or are disabled') }}
            </label>
        </section>

        <section class="{{ $setPanel }}">
            <h3 class="text-base font-semibold text-slate-900">OTP</h3>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_otp" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_otp', $enable_otp)) @disabled($lock_enable_otp) />
                Enable OTP for exam start
            </label>
            <div>
                <label class="mb-1 block text-sm text-slate-600">OTP expiry (seconds)</label>
                <input type="number" name="otp_expiry" min="60" max="7200" value="{{ old('otp_expiry', $otp_expiry) }}"
                    class="{{ $setInput }} max-w-xs" @disabled($lock_otp_expiry) />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-600">OTP verify attempt limit</label>
                <input type="number" name="otp_attempt_limit" min="1" max="20" value="{{ old('otp_attempt_limit', $otp_attempt_limit) }}"
                    class="{{ $setInput }} max-w-xs" @disabled($lock_otp_attempt_limit) />
            </div>
        </section>

        <section class="{{ $setPanel }}">
            <h3 class="text-base font-semibold text-slate-900">SMS (Arkesel)</h3>
            @php
                $smsStatusLine = match ($sms_derived_status) {
                    'ready' => __('SMS Ready: enabled and credentials configured'),
                    'disabled' => __('SMS Disabled: enable_sms is off'),
                    default => __('SMS Incomplete: enabled but API key or sender ID missing'),
                };
                $smsStatusClass = match ($sms_derived_status) {
                    'ready' => 'border border-emerald-200 bg-emerald-50 text-slate-800',
                    'disabled' => 'border border-slate-200 bg-slate-50 text-slate-600',
                    default => 'border border-amber-200 bg-amber-50 text-slate-800',
                };
            @endphp
            <p class="rounded-lg px-4 py-3 text-sm font-medium {{ $smsStatusClass }}">
                {{ $smsStatusLine }}
            </p>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_sms" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_sms', $enable_sms)) @disabled($lock_enable_sms) />
                Enable SMS delivery
            </label>
            <div>
                <label class="mb-1 block text-sm text-slate-600">API key</label>
                <input type="password" name="arkesel_api_key" autocomplete="off" class="{{ $setInput }} max-w-xl"
                    placeholder="{{ $arkesel_api_key_masked ? '•••••••• (enter new to replace)' : 'Not set' }}"
                    @disabled($arkesel_key_locked) />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-600">Sender ID</label>
                <input type="text" name="arkesel_sender_id" value="{{ old('arkesel_sender_id', $arkesel_sender_id) }}"
                    class="{{ $setInput }} max-w-xl" @disabled($arkesel_sender_locked) />
            </div>
        </section>

        <section class="{{ $setPanel }}">
            <h3 class="text-base font-semibold text-slate-900">Proctoring policy</h3>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_proctoring" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_proctoring', $enable_proctoring)) @disabled($lock_enable_proctoring) />
                Enable proctoring
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="require_exam_start_snapshot" value="1" class="{{ $setCheck }}"
                    @checked(old('require_exam_start_snapshot', $require_exam_start_snapshot)) @disabled($lock_require_exam_start_snapshot) />
                Require exam start verification photo (when proctoring is enabled)
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="require_camera_monitoring" value="1" class="{{ $setCheck }}"
                    @checked(old('require_camera_monitoring', $require_camera_monitoring)) @disabled($lock_require_camera_monitoring) />
                Require proctoring camera during the exam (when proctoring is enabled)
            </label>
            <p class="text-xs text-slate-500">Legacy face-match settings in stored JSON are ignored for exam entry; per-exam JSON keys such as <code class="rounded bg-slate-100 px-1">face_match_threshold</code> no longer block students.</p>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="phone_detection_enabled" value="1" class="{{ $setCheck }}"
                    @checked(old('phone_detection_enabled', $phone_detection_enabled)) @disabled($lock_phone_detection_enabled) />
                Phone detection enabled
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="fullscreen_required" value="1" class="{{ $setCheck }}"
                    @checked(old('fullscreen_required', $fullscreen_required)) @disabled($lock_fullscreen_required) />
                Fullscreen required
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="auto_submit_enabled" value="1" class="{{ $setCheck }}"
                    @checked(old('auto_submit_enabled', $auto_submit_enabled)) @disabled($lock_auto_submit_enabled) />
                Auto-submit enabled
            </label>
            @if (auth()->user()?->isSuperAdmin())
                <div class="rounded-lg border border-amber-100 bg-amber-50/90 px-3 py-2 text-xs leading-relaxed text-amber-950">
                    {{ __('Exam surface integrity (invigilated exams only). OS-level screenshots and screen recording cannot be fully blocked in a browser; these options deter common shortcuts and log signals for review.') }}
                </div>
                <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                    <input type="checkbox" name="exam_clipboard_lock" value="1" class="{{ $setCheck }}"
                        @checked(old('exam_clipboard_lock', $exam_clipboard_lock)) @disabled($lock_exam_clipboard_lock) />
                    {{ __('Block copy, cut, and paste in the exam answer area') }}
                </label>
                <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                    <input type="checkbox" name="exam_screenshot_mitigation" value="1" class="{{ $setCheck }}"
                        @checked(old('exam_screenshot_mitigation', $exam_screenshot_mitigation)) @disabled($lock_exam_screenshot_mitigation) />
                    {{ __('Mitigate common screenshot shortcuts and right-click (when in fullscreen)') }}
                </label>
                <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                    <input type="checkbox" name="exam_screen_record_mitigation" value="1" class="{{ $setCheck }}"
                        @checked(old('exam_screen_record_mitigation', $exam_screen_record_mitigation)) @disabled($lock_exam_screen_record_mitigation) />
                    {{ __('Try to capture the PrintScreen key via Keyboard Lock when the browser supports it (fullscreen)') }}
                </label>
            @endif
            <div>
                <label class="mb-1 block text-sm text-slate-600">Default exam proctoring (JSON, optional)</label>
                <textarea name="default_proctoring_settings" rows="8" class="{{ $setInput }} font-mono text-xs"
                    @disabled($proctoring_locked)>{{ old('default_proctoring_settings', $proctoring_json) }}</textarea>
                @error('default_proctoring_settings')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-slate-500">Leave empty to use built-in defaults.</p>
            </div>
        </section>

        <section class="{{ $setPanel }}">
            <h3 class="text-base font-semibold text-slate-900">{{ __('AI integration (system-wide)') }}</h3>
            <p class="text-xs text-slate-600">
                {{ __('Single source of truth for every AI feature in QuizSnap — examiner question generation, lecturer essay grading assistant, student practice quizzes, and study summaries all use the credentials below. Configure once here; there is no separate per-feature key.') }}
            </p>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_ai" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_ai', $enable_ai)) @disabled($lock_enable_ai) />
                {{ __('Enable AI integrations (master switch)') }}
            </label>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-1">
                    <label class="mb-1 block text-sm text-slate-600">{{ __('Provider') }}</label>
                    @php
                        $aiProviderCurrent = old('ai_provider', $ai_provider);
                    @endphp
                    <select name="ai_provider" class="{{ $setInput }}" @disabled($lock_ai_provider)>
                        <option value="deepseek" @selected($aiProviderCurrent === 'deepseek')>DeepSeek</option>
                        <option value="openai" @selected($aiProviderCurrent === 'openai')>OpenAI</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm text-slate-600">{{ __('API key') }}</label>
                    <input type="password" name="ai_api_key" autocomplete="off" class="{{ $setInput }} w-full"
                        placeholder="{{ $ai_api_key_masked ? '•••••••• (enter new to replace)' : 'Not set' }}"
                        @disabled($ai_key_locked) />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-600">{{ __('Model name') }}</label>
                <input type="text" name="ai_model_name" value="{{ old('ai_model_name', $ai_model_name) }}"
                    placeholder="{{ \App\Services\AiIntegrationSettings::DEFAULT_MODEL }}"
                    class="{{ $setInput }} max-w-xl" @disabled($ai_model_locked) />
                <p class="mt-1 text-xs text-slate-500">
                    {{ __('Examples: deepseek-chat, deepseek-coder, gpt-4o-mini. Defaults to deepseek-chat when blank.') }}
                </p>
            </div>
            @if (! empty($ai_legacy_deepseek_present) && empty(trim((string) ($ai_api_key_masked ?? ''))))
                <div class="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-xs text-amber-900">
                    {{ __('A legacy DeepSeek key was found from a previous version and is being used as a fallback. Enter a new API key above to migrate it into the unified integration.') }}
                </div>
            @endif
        </section>

        @if (auth()->user()?->isSuperAdmin())
            <section class="{{ $setPanel }}">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Student dashboard') }}</h3>
                <p class="text-sm text-slate-600">
                    {{ __('Opt the entire student body into the wallet-style mobile experience (hero with a live countdown, action ring, recent activity card, floating bottom bar). The chosen color theme is also applied to the mobile header and floating navigation on every student page so the feel is consistent. Phones only — tablets and desktop are unchanged.') }}
                </p>
                <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                    <input
                        type="checkbox"
                        name="student_dashboard_mobile_wallet"
                        value="1"
                        class="{{ $setCheck }}"
                        @checked(old('student_dashboard_mobile_wallet', $student_dashboard_mobile_wallet))
                        @disabled($lock_student_dashboard_mobile_wallet)
                    />
                    {{ __('Use wallet-style mobile dashboard for students') }}
                </label>
                <p class="text-xs text-slate-500">
                    {{ __('After saving, students will see the new layout on phones the next time their dashboard loads. Sign in as a student on a phone (or use a narrow-window mobile emulator) to preview it.') }}
                </p>

                @php
                    $walletThemeCurrent = old('student_dashboard_mobile_wallet_theme', $student_dashboard_mobile_wallet_theme);
                    $walletThemeSwatches = [
                        'teal' => ['#56aebb', '#15343a', '#e46f2e'],
                        'forest' => ['#1b6b4e', '#14523c', '#c9f656'],
                        'indigo' => ['#1e1b4b', '#312e81', '#7dd3fc'],
                        'coral' => ['#c2410c', '#9a3412', '#fde68a'],
                        'noir' => ['#050507', '#16161e', '#2dd4bf'],
                    ];
                @endphp
                <div class="mt-3 rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 sm:p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Color theme') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Picks the color the wallet hero, mobile header, and floating navigation use across all student pages. Default is the main QuizSnap teal so the mobile shell matches the rest of the product.') }}</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($student_dashboard_mobile_wallet_theme_options as $themeOption)
                            @php
                                $slug = $themeOption['slug'];
                                $swatches = $walletThemeSwatches[$slug] ?? ['#94a3b8', '#475569', '#f1f5f9'];
                            @endphp
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border px-3 py-2.5 text-sm text-slate-800 transition-colors {{ $walletThemeCurrent === $slug ? 'border-emerald-400 bg-white' : 'border-slate-200 bg-white/80 hover:bg-white' }}">
                                <input
                                    type="radio"
                                    name="student_dashboard_mobile_wallet_theme"
                                    value="{{ $slug }}"
                                    class="mt-1 h-4 w-4 text-emerald-600 focus:ring-emerald-500/35"
                                    @checked($walletThemeCurrent === $slug)
                                    @disabled($lock_student_dashboard_mobile_wallet_theme)
                                />
                                <span class="min-w-0 flex-1 space-y-0.5">
                                    <span class="flex items-center gap-2">
                                        <span class="block font-semibold text-slate-900">{{ __($themeOption['label']) }}</span>
                                        <span class="inline-flex items-center gap-0.5" aria-hidden="true">
                                            @foreach ($swatches as $swatch)
                                                <span class="inline-block h-3.5 w-3.5 rounded-full ring-1 ring-slate-200" style="background: {{ $swatch }};"></span>
                                            @endforeach
                                        </span>
                                    </span>
                                    <span class="block text-xs text-slate-600">{{ __($themeOption['description']) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="{{ $setPanel }}">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Student exam: presentation mode') }}</h3>
                <p class="text-sm text-slate-600">
                    {{ __('Picks which student-facing layout the exam runtime shows. The proctoring stack (camera, fullscreen, tab-switch warnings, auto-submit, answer save) is identical in both modes — only the presentation changes.') }}
                </p>
                @php
                    $playModeCurrent = old('student_exam_play_mode', $student_exam_play_mode);
                @endphp
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 text-sm text-slate-800 transition-colors {{ $playModeCurrent === 'classic' ? 'border-emerald-400 bg-emerald-50/70' : 'border-slate-200 bg-white' }}">
                        <input
                            type="radio"
                            name="student_exam_play_mode"
                            value="classic"
                            class="mt-0.5 h-4 w-4 text-emerald-600 focus:ring-emerald-500/35"
                            @checked($playModeCurrent === 'classic')
                            @disabled($lock_student_exam_play_mode)
                        />
                        <span class="space-y-0.5">
                            <span class="block font-semibold text-slate-900">{{ __('Classic') }}</span>
                            <span class="block text-xs text-slate-600">{{ __('List of A/B/C/D rows with left nav rail. The default — works well for long forms and reading-heavy exams.') }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 text-sm text-slate-800 transition-colors {{ $playModeCurrent === 'arena' ? 'border-emerald-400 bg-emerald-50/70' : 'border-slate-200 bg-white' }}">
                        <input
                            type="radio"
                            name="student_exam_play_mode"
                            value="arena"
                            class="mt-0.5 h-4 w-4 text-emerald-600 focus:ring-emerald-500/35"
                            @checked($playModeCurrent === 'arena')
                            @disabled($lock_student_exam_play_mode)
                        />
                        <span class="space-y-0.5">
                            <span class="block font-semibold text-slate-900">{{ __('Arena (gamified)') }}</span>
                            <span class="block text-xs text-slate-600">{{ __('Kahoot-style single card with colored answer tiles, 3-stage progress rail, “locked-in” feedback sweep, floating camera PiP, and a step-by-step finish screen before submit.') }}</span>
                        </span>
                    </label>
                </div>
                <p class="text-xs text-slate-500">
                    {{ __('Assignments always use the classic essay editor regardless of this setting (the arena card layout cannot host long-form text + file uploads).') }}
                </p>
            </section>
        @endif

        <section class="{{ $setPanel }}">
            <h3 class="text-base font-semibold text-slate-900">Practice &amp; course materials (unofficial)</h3>
            <p class="text-xs text-slate-600">{{ __('Student practice is separate from official exams and proctoring. AI features below use the unified AI integration configured above — there is no separate API key here.') }}</p>

            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_student_practice_quizzes" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_student_practice_quizzes', $enable_student_practice_quizzes)) @disabled($lock_enable_student_practice_quizzes) />
                Enable student practice area (master switch)
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_course_material_uploads" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_course_material_uploads', $enable_course_material_uploads)) @disabled($lock_enable_course_material_uploads) />
                Examiners can upload course materials
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_ai_summary" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_ai_summary', $enable_ai_summary)) @disabled($lock_enable_ai_summary) />
                Students can request AI study summaries
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="enable_ai_practice_quiz_generation" value="1" class="{{ $setCheck }}"
                    @checked(old('enable_ai_practice_quiz_generation', $enable_ai_practice_quiz_generation)) @disabled($lock_enable_ai_practice_quiz_generation) />
                Students can generate AI practice quizzes
            </label>
            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-800">
                <input type="checkbox" name="allow_examiner_practice_overview" value="1" class="{{ $setCheck }}"
                    @checked(old('allow_examiner_practice_overview', $allow_examiner_practice_overview)) @disabled($lock_allow_examiner_practice_overview) />
                Examiners can view practice analytics (aggregates only)
            </label>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm text-slate-600">Daily AI quiz generations / student</label>
                    <input type="number" name="practice_quiz_daily_limit" min="0" max="500" value="{{ old('practice_quiz_daily_limit', $practice_quiz_daily_limit) }}" class="{{ $setInput }} max-w-xs" @disabled($lock_practice_quiz_daily_limit) />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-600">Monthly AI quiz generations / student</label>
                    <input type="number" name="practice_quiz_monthly_limit" min="0" max="5000" value="{{ old('practice_quiz_monthly_limit', $practice_quiz_monthly_limit) }}" class="{{ $setInput }} max-w-xs" @disabled($lock_practice_quiz_monthly_limit) />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-600">Practice AI tokens / student / month</label>
                    <input type="number" name="practice_ai_token_limit_per_student" min="0" value="{{ old('practice_ai_token_limit_per_student', $practice_ai_token_limit_per_student) }}" class="{{ $setInput }} max-w-xs" @disabled($lock_practice_ai_token_limit_per_student) />
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 px-3 py-2.5 text-xs text-slate-700">
                <p class="font-semibold text-slate-800">
                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                    {{ __('AI provider:') }}
                    <span class="font-normal">{{ __('Active provider is') }} <code class="rounded bg-white px-1 py-0.5 ring-1 ring-inset ring-slate-200">{{ $ai_provider_active }}</code> {{ __('using') }} <code class="rounded bg-white px-1 py-0.5 ring-1 ring-inset ring-slate-200">{{ $ai_model_active }}</code>.</span>
                </p>
                <p class="mt-1 text-slate-600">{{ __('Update credentials in the “AI integration (system-wide)” section above. All practice features inherit the same configuration automatically.') }}</p>
            </div>
        </section>

        <div class="flex gap-3">
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/45 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                Save changes
            </button>
        </div>
    </form>

    <section id="admin-settings-lock-unlock" class="mt-8 scroll-mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Lock / unlock') }}</h3>
        <p class="mb-3 mt-1 text-[11px] leading-relaxed text-slate-600">{{ __('Flip a switch to lock a setting. Locked values can only be changed by an admin; a light green tint shows which rows are locked.') }}</p>
        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($lockable as $k => $label)
                @php
                    $locked = $lockStatesByKey[$k] ?? false;
                @endphp
                <li
                    id="setting-lock-{{ $k }}"
                    x-data="{ locked: @js($locked), flip() { (this.locked ? this.$refs.unlockForm : this.$refs.lockForm).requestSubmit(); } }"
                    class="scroll-mt-24 flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2 transition-colors"
                    :class="locked ? 'border-emerald-200/80 bg-emerald-50/50' : 'border-slate-200/90 bg-white'"
                >
                    <span class="min-w-0 flex-1 text-[11px] font-medium leading-snug text-slate-800">{{ $label }}</span>
                    <form x-ref="lockForm" method="post" action="{{ route('admin.settings.lock') }}" class="sr-only" aria-hidden="true">@csrf
                        <input type="hidden" name="key" value="{{ $k }}" />
                        <button type="submit" tabindex="-1">{{ __('Lock') }}</button>
                    </form>
                    <form x-ref="unlockForm" method="post" action="{{ route('admin.settings.unlock') }}" class="sr-only" aria-hidden="true">@csrf
                        <input type="hidden" name="key" value="{{ $k }}" />
                        <button type="submit" tabindex="-1">{{ __('Unlock') }}</button>
                    </form>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="locked"
                        :aria-label="locked ? @js(__('Locked — click to unlock')) : @js(__('Unlocked — click to lock'))"
                        @click="flip()"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border border-slate-200/80 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 focus-visible:ring-offset-1"
                        :class="locked ? 'bg-emerald-600' : 'bg-slate-200'"
                    >
                        <span
                            aria-hidden="true"
                            class="pointer-events-none absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm ring-1 ring-black/5 transition-transform"
                            :class="locked ? 'translate-x-[1.375rem]' : 'translate-x-0.5'"
                        ></span>
                    </button>
                </li>
            @endforeach
        </ul>
    </section>

    @if (auth()->user()?->isSuperAdmin())
        <section class="{{ $setPanel }}">
            <h3 class="text-base font-semibold text-slate-900">{{ __('Student dashboard branding') }}</h3>
            <p class="text-sm text-slate-600">
                {{ __('Background photo behind the mobile profile card on the student dashboard. Saved as a compressed JPEG (max ~180 KB).') }}
            </p>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                <div
                    class="aspect-[16/9] w-full max-w-md bg-cover bg-center"
                    style="background-image: url('{{ $studentDashboardBannerUrl }}');"
                    role="img"
                    aria-label="{{ __('Current student dashboard banner preview') }}"
                ></div>
            </div>
            @if ($studentDashboardHasCustomBanner)
                <p class="text-xs font-medium text-emerald-800">{{ __('Using a custom banner.') }}</p>
            @else
                <p class="text-xs text-slate-500">{{ __('Using the default banner.') }}</p>
            @endif

            <form
                method="post"
                action="{{ route('admin.settings.student-dashboard-banner.update') }}"
                enctype="multipart/form-data"
                class="space-y-4"
            >
                @csrf
                <div>
                    <label for="banner_image" class="mb-1 block text-sm text-slate-600">{{ __('Upload new banner') }}</label>
                    <input
                        id="banner_image"
                        name="banner_image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="{{ $setInput }} max-w-md"
                    />
                    @error('banner_image')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-slate-500">{{ __('JPEG, PNG, or WebP. Optimized to 1280×720 or smaller.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        {{ __('Save banner') }}
                    </button>
                </div>
            </form>

            @if ($studentDashboardHasCustomBanner)
                <form method="post" action="{{ route('admin.settings.student-dashboard-banner.update') }}" class="pt-2">
                    @csrf
                    <input type="hidden" name="remove_banner" value="1" />
                    <button type="submit" class="text-sm font-semibold text-rose-700 hover:text-rose-900">
                        {{ __('Reset to default banner') }}
                    </button>
                </form>
            @endif

            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
                {{ __('The wallet-style mobile theme toggle lives in the “Student dashboard” section above (saved with the main settings form).') }}
            </p>
        </section>
    @endif

    @if (session('scroll_to_setting_lock'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var el = document.getElementById(@json('setting-lock-'.session('scroll_to_setting_lock')));
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            </script>
        @endpush
    @endif
</x-layouts.admin>
