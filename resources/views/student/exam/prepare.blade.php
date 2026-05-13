<x-layouts.student>
    <x-slot name="title">{{ __('Exam entry') }}</x-slot>
    <x-slot name="subtitle">{{ $quiz->title }}</x-slot>

    <div class="mx-auto max-w-3xl py-2">
            @if ($entryBlocked)
                <div class="mb-6 rounded-xl border border-qs-danger/35 bg-qs-danger-soft px-4 py-3 text-sm text-qs-danger">
                    {{ __('Exam entry is temporarily unavailable. Please try again later or contact support.') }}
                </div>
            @endif

            <div class="mb-8 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-qs-muted">
                <span id="step-pill-rules" class="rounded-full border border-qs-accent bg-qs-accent/20 px-3 py-1 text-qs-text">{{ __('Rules') }}</span>
                @if (!($isAssignment ?? false))
                    <span id="step-pill-permissions" class="rounded-full border border-qs-soft px-3 py-1">{{ __('Access') }}</span>
                @endif
                <span id="step-pill-1" class="rounded-full border border-qs-soft px-3 py-1">{{ __('Overview') }}</span>
                @if ($otpEnabled)
                    <span id="step-pill-2" class="rounded-full border border-qs-soft px-3 py-1">{{ __('OTP') }}</span>
                @endif
                @if ($snapshotRequired)
                    <span id="step-pill-3" class="rounded-full border border-qs-soft px-3 py-1">{{ __('Camera') }}</span>
                @endif
                <span id="step-pill-start" class="rounded-full border border-qs-soft px-3 py-1">{{ __('Start') }}</span>
            </div>

            <div class="qs-surface space-y-6 p-6 shadow-sm">
                <section id="panel-rules">
                    <h3 class="text-lg font-semibold text-qs-text">{{ ($isAssignment ?? false) ? __('Assignment integrity') : __('Exam rules & integrity') }}</h3>
                    @if ($isAssignment ?? false)
                        <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-qs-text">
                            <li>{{ __('Complete this assignment yourself, using only sources and collaboration rules your course allows.') }}</li>
                            <li>{{ __('Typed responses must be entered in this page. Copy and paste is blocked in answer fields to support academic integrity.') }}</li>
                            <li>{{ __('This coursework does not use live camera or audio invigilation unless your school explicitly enables an exception.') }}</li>
                        </ul>
                    @else
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-qs-text">
                        <li>{{ __('Complete this exam honestly, on your own, without unauthorised help or materials, unless your institution explicitly allows otherwise.') }}</li>
                        <li>{{ __('Follow invigilator or institution instructions (for example fullscreen, staying visible on camera, and not switching away from the exam without permission).') }}</li>
                        <li>{{ __('Do not copy, share, or capture exam content. Suspicious behaviour may be logged for review.') }}</li>
                    </ul>
                    <div class="mt-5 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950">
                        <p class="font-semibold">{{ __('When your work may be submitted automatically') }}</p>
                        <ul class="mt-2 list-disc space-y-1.5 pl-5">
                            <li>{{ __('If your school enables proctoring, repeated or serious monitoring signals can increase a violation score. After warnings, policy may trigger automatic submission of your attempt.') }}</li>
                            <li>{{ __('Staff may also end or submit a session when your institution’s rules allow it (for example emergencies or confirmed misconduct).') }}</li>
                            <li>{{ __('The timer or submission window may still apply — submit before time expires when you are allowed to.') }}</li>
                        </ul>
                    </div>
                    @endif
                    <label class="mt-5 flex cursor-pointer items-start gap-3 text-sm text-qs-text">
                        <input id="chk-rules-agree" type="checkbox" class="mt-1 h-4 w-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent" @if ($entryBlocked) disabled @endif />
                        <span>{{ ($isAssignment ?? false)
                            ? __('I confirm I will follow the rules above and submit my own work in the answer fields.')
                            : __('I have read and agree to these rules and understand that my attempt may be auto-submitted or ended under my institution’s proctoring policy.') }}</span>
                    </label>
                    <button type="button" id="btn-rules-next" class="qs-btn-primary mt-6" disabled @if ($entryBlocked) disabled @endif>
                        {{ __('Continue') }}
                    </button>
                </section>

                @unless ($isAssignment ?? false)
                <section id="panel-permissions" class="hidden">
                    <h3 class="text-lg font-semibold text-qs-text">{{ __('Camera, microphone & location') }}</h3>
                    <p class="mt-2 text-sm text-qs-muted">
                        {{ __('This exam uses browser permissions for proctoring and session integrity. Tap each control once and allow access in your browser. Streams stop after confirmation; you may be asked to open the camera again for a verification photo.') }}
                    </p>
                    <div class="mt-5 space-y-3">
                        <div class="flex flex-col gap-2 rounded-xl border border-qs-soft bg-qs-soft/35 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-qs-text">{{ __('Camera') }}</p>
                                <p id="perm-camera-msg" class="mt-0.5 text-xs text-qs-muted">{{ __('Not granted yet') }}</p>
                            </div>
                            <button type="button" id="btn-perm-camera" class="qs-btn-secondary shrink-0 text-sm" @if ($entryBlocked) disabled @endif>{{ __('Allow camera') }}</button>
                        </div>
                        <div class="flex flex-col gap-2 rounded-xl border border-qs-soft bg-qs-soft/35 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-qs-text">{{ __('Microphone') }}</p>
                                <p id="perm-mic-msg" class="mt-0.5 text-xs text-qs-muted">{{ __('Not granted yet') }}</p>
                            </div>
                            <button type="button" id="btn-perm-mic" class="qs-btn-secondary shrink-0 text-sm" @if ($entryBlocked) disabled @endif>{{ __('Allow microphone') }}</button>
                        </div>
                        <div class="flex flex-col gap-2 rounded-xl border border-qs-soft bg-qs-soft/35 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-qs-text">{{ __('Location') }}</p>
                                <p id="perm-loc-msg" class="mt-0.5 text-xs text-qs-muted">{{ __('Not granted yet') }}</p>
                            </div>
                            <button type="button" id="btn-perm-location" class="qs-btn-secondary shrink-0 text-sm" @if ($entryBlocked) disabled @endif>{{ __('Allow location') }}</button>
                        </div>
                    </div>
                    <p id="perm-hint" class="mt-3 text-xs text-qs-muted" role="status"></p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="button" id="btn-permissions-back" class="qs-btn-secondary text-sm">{{ __('Back') }}</button>
                        <button type="button" id="btn-permissions-next" class="qs-btn-primary text-sm" disabled @if ($entryBlocked) disabled @endif>{{ __('Continue') }}</button>
                    </div>
                </section>
                @endunless

                <section id="panel-overview" class="hidden">
                    <h3 class="text-lg font-semibold text-qs-text">{{ ($isAssignment ?? false) ? __('Assignment overview') : __('Exam overview') }}</h3>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex justify-between gap-4 border-b border-qs-soft pb-2">
                            <dt class="text-qs-muted">{{ __('Course') }}</dt>
                            <dd class="text-right font-medium text-qs-text">{{ $quiz->course?->code }} — {{ $quiz->course?->title }}</dd>
                        </div>
                        @if ($quiz->due_at)
                            <div class="flex justify-between gap-4 border-b border-qs-soft pb-2">
                                <dt class="text-qs-muted">{{ __('Due') }}</dt>
                                <dd class="text-right font-medium text-qs-text">{{ $quiz->due_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4 border-b border-qs-soft pb-2">
                            <dt class="text-qs-muted">{{ ($isAssignment ?? false) ? __('Time budget (minutes)') : __('Duration') }}</dt>
                            <dd class="text-right font-medium text-qs-text">{{ $quiz->duration_minutes }} {{ __('minutes') }}</dd>
                        </div>
                        @if ($quiz->start_time || $quiz->end_time)
                            <div class="flex justify-between gap-4 border-b border-qs-soft pb-2">
                                <dt class="text-qs-muted">{{ __('Window') }}</dt>
                                <dd class="text-right text-qs-text">
                                    {{ $quiz->start_time?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                                    —
                                    {{ $quiz->end_time?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                    @if ($otpEnabled)
                        <p class="mt-4 text-sm text-qs-muted">
                            {{ __('Phone verification is required for this institution.') }}
                            @if (! $smsEnabled)
                                <span class="font-medium text-qs-text">{{ __('SMS delivery may be limited — follow on-screen prompts.') }}</span>
                            @endif
                        </p>
                    @endif
                    @if ($snapshotRequired)
                        <p class="mt-2 text-sm text-qs-muted">{{ __('Before starting, we will take a verification photo and keep camera monitoring active during the exam, according to your school’s proctoring settings.') }}</p>
                    @endif
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="button" id="btn-overview-back" class="qs-btn-secondary text-sm">{{ __('Back') }}</button>
                        <button type="button" id="btn-overview-next" class="qs-btn-primary text-sm" @if ($entryBlocked) disabled @endif>
                            {{ __('Continue') }}
                        </button>
                    </div>
                </section>

                @if ($otpEnabled)
                    <section id="panel-otp" class="hidden">
                        <h3 class="text-lg font-semibold text-qs-text">{{ __('Phone verification') }}</h3>
                        <p class="mt-2 text-sm text-qs-muted">{{ __('We will send a code to the phone number on your account when you request it.') }}</p>
                        <p id="otp-hint" class="mt-2 text-xs text-qs-muted"></p>
                        <button type="button" id="btn-request-otp" class="qs-btn-secondary mt-4 text-sm">
                            {{ __('Send verification code') }}
                        </button>
                        <div id="otp-input-row" class="mt-4 hidden">
                            <label class="block text-sm font-medium text-qs-text" for="otp-code">{{ __('6-digit code') }}</label>
                            <input id="otp-code" type="text" maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code"
                                class="qs-input mt-1 max-w-xs font-mono text-lg tracking-widest" />
                            <button type="button" id="btn-verify-otp" class="qs-btn-primary mt-3 text-sm">
                                {{ __('Verify code') }}
                            </button>
                        </div>
                        <p id="otp-message" class="mt-3 text-sm text-qs-muted" role="status"></p>
                        <button type="button" id="btn-otp-back" class="qs-btn-secondary mt-6 text-sm">{{ __('Back') }}</button>
                    </section>
                @endif

                @if ($snapshotRequired)
                    <section id="panel-snapshot" class="hidden">
                        <h3 class="text-lg font-semibold text-qs-text">{{ __('Exam verification photo') }}</h3>
                        <p class="mt-2 text-sm text-qs-muted">{{ __('Allow camera access. We confirm a person is visible (on-device) before capture unlocks, then you take one session photo — not automated identity matching.') }}</p>
                        <div id="snap-preview-wrap" class="relative mt-4 hidden max-w-md overflow-hidden rounded-lg border border-qs-soft bg-black">
                            <video id="snap-video" class="block w-full" autoplay muted playsinline></video>
                        </div>
                        <canvas id="snap-canvas" class="hidden"></canvas>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" id="snap-open-camera" class="qs-btn-secondary text-sm">{{ __('Open camera') }}</button>
                            <button type="button" id="snap-capture" class="qs-btn-primary text-sm hidden">{{ __('Capture photo') }}</button>
                            <button type="button" id="snap-retake" class="qs-btn-secondary text-sm hidden">{{ __('Retake') }}</button>
                        </div>
                        <p id="snap-status" class="mt-3 text-sm text-qs-muted" role="status"></p>
                        <button type="button" id="btn-snapshot-back" class="qs-btn-secondary mt-6 text-sm">{{ __('Back') }}</button>
                    </section>
                @endif

                <section id="panel-start" class="hidden">
                    <h3 class="text-lg font-semibold text-qs-text">{{ ($isAssignment ?? false) ? __('Ready to work') : __('Ready to begin') }}</h3>
                    <p class="mt-2 text-sm text-qs-muted">{{ ($isAssignment ?? false)
                        ? __('When you start, you can type and save your answers. There is no live invigilation camera for this coursework by default.')
                        : __('When you start, the exam timer may begin immediately according to your institution’s rules.') }}</p>
                    <p id="start-message" class="mt-3 text-sm text-qs-muted" role="status"></p>
                    <button type="button" id="btn-start-exam" class="qs-btn-primary mt-6" @if ($entryBlocked) disabled @endif>
                        {{ ($isAssignment ?? false) ? __('Start assignment') : __('Start exam now') }}
                    </button>
                </section>
            </div>
    </div>

    @push('scripts')
        <script type="module">
            const examId = {{ (int) $quiz->id }};
            const otpEnabled = @json($otpEnabled);
            const snapshotRequired = @json($snapshotRequired);
            const entryBlocked = @json($entryBlocked);
            const isAssignment = @json($isAssignment ?? false);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const panelRules = document.getElementById('panel-rules');
            const panelPermissions = document.getElementById('panel-permissions');
            const panelOverview = document.getElementById('panel-overview');
            const panelOtp = document.getElementById('panel-otp');
            const panelSnapshot = document.getElementById('panel-snapshot');
            const panelStart = document.getElementById('panel-start');

            let verificationBlob = null;
            let snapStream = null;
            let faceLandmarker = null;
            let snapDetectRaf = null;
            let snapHumanFrames = 0;
            let snapMpBypass = false;

            let permCameraOk = false;
            let permMicOk = false;
            let permLocOk = false;

            const msgPermOk = '{{ __('Access granted.') }}';
            const msgPermDenied = '{{ __('Access was denied or unavailable. Check this site’s permissions in your browser settings and try again.') }}';
            const msgLocUnsupported = '{{ __('Location is not supported in this browser.') }}';
            const msgSecureContext = '{{ __('Location usually requires HTTPS (or localhost). Use a secure link if this fails.') }}';

            function showPanel(name) {
                if (panelRules) panelRules.classList.toggle('hidden', name !== 'rules');
                if (panelPermissions) panelPermissions.classList.toggle('hidden', name !== 'permissions');
                if (panelOverview) panelOverview.classList.toggle('hidden', name !== 'overview');
                if (panelOtp) panelOtp.classList.toggle('hidden', name !== 'otp');
                if (panelSnapshot) panelSnapshot.classList.toggle('hidden', name !== 'snapshot');
                if (panelStart) panelStart.classList.toggle('hidden', name !== 'start');
            }

            function setPillActive(step) {
                const reset = (id) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.classList.remove('border-qs-accent', 'bg-qs-accent/20', 'text-qs-text');
                    el.classList.add('border-qs-soft');
                };
                const on = (id) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.classList.add('border-qs-accent', 'bg-qs-accent/20', 'text-qs-text');
                    el.classList.remove('border-qs-soft');
                };
                ['step-pill-rules', 'step-pill-permissions', 'step-pill-1', 'step-pill-2', 'step-pill-3', 'step-pill-start'].forEach(reset);
                if (step === 'rules') on('step-pill-rules');
                if (step === 'permissions') on('step-pill-permissions');
                if (step === 'overview') on('step-pill-1');
                if (step === 'otp') on('step-pill-2');
                if (step === 'snapshot') on('step-pill-3');
                if (step === 'start') on('step-pill-start');
            }

            function updatePermissionsContinue() {
                const btn = document.getElementById('btn-permissions-next');
                if (!btn) return;
                btn.disabled = entryBlocked || !permCameraOk || !permMicOk || !permLocOk;
            }

            function stylePermMessage(el, ok) {
                if (!el) return;
                el.classList.toggle('text-emerald-700', ok);
                el.classList.toggle('text-qs-muted', !ok);
            }

            async function parseJsonSafe(res) {
                try {
                    return await res.json();
                } catch {
                    return {};
                }
            }

            function friendlyStartError(status, body) {
                if (status === 503 || body?.status === 'service_unavailable') {
                    return '{{ __('Verification is temporarily unavailable. Please try again shortly.') }}';
                }
                if (status === 409) {
                    return '{{ __('Another start request is in progress. Wait a moment and try again.') }}';
                }
                if (status === 429) {
                    return '{{ __('Too many attempts. Please wait before trying again.') }}';
                }
                const msg = body?.message || '';
                if (typeof msg === 'string' && msg.includes('OTP')) {
                    return msg;
                }
                if (typeof msg === 'string' && (msg.includes('verification photo') || msg.includes('Camera'))) {
                    return msg;
                }
                if (status === 422) {
                    return typeof msg === 'string' && msg ? msg : '{{ __('We could not start the exam with the details provided. Review the steps or contact support.') }}';
                }
                return '{{ __('Something went wrong. Please try again.') }}';
            }

            function friendlyOtpVerifyError(body) {
                const msg = body?.message || '';
                if (typeof msg !== 'string' || !msg) {
                    return '{{ __('Verification could not be completed. Request a new code.') }}';
                }
                if (msg.includes('expired')) {
                    return '{{ __('This code has expired. Request a new code.') }}';
                }
                if (msg.includes('Too many')) {
                    return '{{ __('Too many incorrect attempts. Start again to receive a new code.') }}';
                }
                if (msg.includes('No verification code')) {
                    return '{{ __('No active code. Send a new verification code first.') }}';
                }
                return msg;
            }

            async function postJson(url, payload) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload ?? {}),
                });
                const body = await parseJsonSafe(res);
                return { res, body };
            }

            async function postStartMultipart(extraFields) {
                const fd = new FormData();
                fd.append('exam_id', String(examId));
                if (verificationBlob) {
                    fd.append('verification_snapshot', verificationBlob, 'verification.jpg');
                }
                if (extraFields?.hardware_concurrency != null) {
                    fd.append('hardware_concurrency', String(extraFields.hardware_concurrency));
                }
                const res = await fetch(@json(route('exam-sessions.start')), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: fd,
                });
                const body = await parseJsonSafe(res);
                return { res, body };
            }

            let otpVerified = !otpEnabled;

            if (isAssignment) {
                permCameraOk = true;
                permMicOk = true;
                permLocOk = true;
            }

            const chkRules = document.getElementById('chk-rules-agree');
            const btnRulesNext = document.getElementById('btn-rules-next');
            chkRules?.addEventListener('change', () => {
                if (btnRulesNext) {
                    btnRulesNext.disabled = entryBlocked || !chkRules.checked;
                }
            });
            if (btnRulesNext && chkRules && !entryBlocked) {
                btnRulesNext.disabled = !chkRules.checked;
            }

            document.getElementById('btn-rules-next')?.addEventListener('click', () => {
                if (entryBlocked) return;
                if (!chkRules?.checked) return;
                if (isAssignment) {
                    showPanel('overview');
                    setPillActive('overview');
                    return;
                }
                showPanel('permissions');
                setPillActive('permissions');
            });

            const permHintEl = document.getElementById('perm-hint');
            if (permHintEl && typeof window.isSecureContext !== 'undefined' && !window.isSecureContext) {
                permHintEl.textContent = msgSecureContext;
            }

            document.getElementById('btn-permissions-back')?.addEventListener('click', () => {
                showPanel('rules');
                setPillActive('rules');
            });

            document.getElementById('btn-permissions-next')?.addEventListener('click', () => {
                if (entryBlocked) return;
                if (!permCameraOk || !permMicOk || !permLocOk) return;
                showPanel('overview');
                setPillActive('overview');
            });

            document.getElementById('btn-perm-camera')?.addEventListener('click', async () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('perm-camera-msg');
                try {
                    if (!navigator.mediaDevices?.getUserMedia) {
                        throw new Error('no-gum');
                    }
                    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    stream.getTracks().forEach((t) => t.stop());
                    permCameraOk = true;
                    if (msgEl) msgEl.textContent = msgPermOk;
                    stylePermMessage(msgEl, true);
                    document.getElementById('btn-perm-camera')?.setAttribute('disabled', 'disabled');
                } catch {
                    permCameraOk = false;
                    if (msgEl) {
                        msgEl.textContent = msgPermDenied;
                        stylePermMessage(msgEl, false);
                    }
                }
                updatePermissionsContinue();
            });

            document.getElementById('btn-perm-mic')?.addEventListener('click', async () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('perm-mic-msg');
                try {
                    if (!navigator.mediaDevices?.getUserMedia) {
                        throw new Error('no-gum');
                    }
                    const stream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                    stream.getTracks().forEach((t) => t.stop());
                    permMicOk = true;
                    if (msgEl) msgEl.textContent = msgPermOk;
                    stylePermMessage(msgEl, true);
                    document.getElementById('btn-perm-mic')?.setAttribute('disabled', 'disabled');
                } catch {
                    permMicOk = false;
                    if (msgEl) {
                        msgEl.textContent = msgPermDenied;
                        stylePermMessage(msgEl, false);
                    }
                }
                updatePermissionsContinue();
            });

            document.getElementById('btn-perm-location')?.addEventListener('click', () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('perm-loc-msg');
                if (!('geolocation' in navigator) || !navigator.geolocation) {
                    permLocOk = false;
                    if (msgEl) {
                        msgEl.textContent = msgLocUnsupported;
                        stylePermMessage(msgEl, false);
                    }
                    updatePermissionsContinue();
                    return;
                }
                navigator.geolocation.getCurrentPosition(
                    () => {
                        permLocOk = true;
                        if (msgEl) msgEl.textContent = msgPermOk;
                        stylePermMessage(msgEl, true);
                        document.getElementById('btn-perm-location')?.setAttribute('disabled', 'disabled');
                        updatePermissionsContinue();
                    },
                    () => {
                        permLocOk = false;
                        if (msgEl) {
                            msgEl.textContent = msgPermDenied;
                            stylePermMessage(msgEl, false);
                        }
                        updatePermissionsContinue();
                    },
                    { enableHighAccuracy: false, maximumAge: 0, timeout: 20000 },
                );
            });

            document.getElementById('btn-overview-back')?.addEventListener('click', () => {
                if (isAssignment) {
                    showPanel('rules');
                    setPillActive('rules');
                    return;
                }
                showPanel('permissions');
                setPillActive('permissions');
            });

            document.getElementById('btn-overview-next')?.addEventListener('click', () => {
                if (entryBlocked) return;
                if (otpEnabled) {
                    showPanel('otp');
                    setPillActive('otp');
                } else if (snapshotRequired) {
                    showPanel('snapshot');
                    setPillActive('snapshot');
                } else {
                    showPanel('start');
                    setPillActive('start');
                }
            });

            document.getElementById('btn-otp-back')?.addEventListener('click', () => {
                showPanel('overview');
                setPillActive('overview');
            });

            document.getElementById('btn-snapshot-back')?.addEventListener('click', () => {
                void stopSnapCamera();
                if (otpEnabled) {
                    showPanel('otp');
                    setPillActive('otp');
                } else {
                    showPanel('overview');
                    setPillActive('overview');
                }
            });

            const otpHint = document.getElementById('otp-hint');
            if (otpHint && {{ (int) $otpExpirySeconds }} > 0) {
                otpHint.textContent = '{{ __('Codes expire after') }} {{ (int) $otpExpirySeconds }} {{ __('seconds.') }}';
            }

            document.getElementById('btn-request-otp')?.addEventListener('click', async () => {
                const msgEl = document.getElementById('otp-message');
                msgEl.textContent = '{{ __('Sending…') }}';
                const { res, body } = await postJson(@json(route('exam-sessions.start')), { exam_id: examId });
                if (res.ok && body?.status === 'otp_required') {
                    msgEl.textContent = body.message || '{{ __('Enter the code sent to your phone.') }}';
                    document.getElementById('otp-input-row')?.classList.remove('hidden');
                    return;
                }
                if (res.ok && body?.status === 'otp_pending') {
                    msgEl.textContent = body.message || '{{ __('A code is already on its way. Enter it below.') }}';
                    document.getElementById('otp-input-row')?.classList.remove('hidden');
                    return;
                }
                if (res.ok && body?.status === 'continue') {
                    otpVerified = true;
                    msgEl.textContent = '{{ __('Verified — continuing.') }}';
                    afterOtpOk();
                    return;
                }
                msgEl.textContent = friendlyStartError(res.status, body);
            });

            function afterOtpOk() {
                if (snapshotRequired) {
                    showPanel('snapshot');
                    setPillActive('snapshot');
                } else {
                    showPanel('start');
                    setPillActive('start');
                }
            }

            document.getElementById('btn-verify-otp')?.addEventListener('click', async () => {
                const msgEl = document.getElementById('otp-message');
                const code = (document.getElementById('otp-code')?.value || '').trim();
                if (!/^[0-9]{6}$/.test(code)) {
                    msgEl.textContent = '{{ __('Enter the 6-digit code.') }}';
                    return;
                }
                msgEl.textContent = '{{ __('Checking…') }}';
                const { res, body } = await postJson(@json(route('exam-sessions.verify-otp')), {
                    exam_id: examId,
                    otp_code: code,
                });
                if (res.ok && body?.status === 'otp_verified') {
                    otpVerified = true;
                    msgEl.textContent = '{{ __('Code accepted.') }}';
                    afterOtpOk();
                    return;
                }
                msgEl.textContent = friendlyOtpVerifyError(body);
            });

            const snapVideo = document.getElementById('snap-video');
            const snapCanvas = document.getElementById('snap-canvas');
            const snapWrap = document.getElementById('snap-preview-wrap');
            const snapStatus = document.getElementById('snap-status');
            const btnSnapOpen = document.getElementById('snap-open-camera');
            const btnSnapCapture = document.getElementById('snap-capture');
            const btnSnapRetake = document.getElementById('snap-retake');

            async function stopSnapCamera() {
                if (snapDetectRaf !== null) {
                    cancelAnimationFrame(snapDetectRaf);
                    snapDetectRaf = null;
                }
                snapHumanFrames = 0;
                if (snapStream) {
                    snapStream.getTracks().forEach((t) => t.stop());
                    snapStream = null;
                }
                if (snapVideo) snapVideo.srcObject = null;
            }

            async function initSnapFaceLandmarker() {
                if (faceLandmarker) {
                    return true;
                }
                try {
                    const { FilesetResolver, FaceLandmarker } = await import('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/+esm');
                    const vision = await FilesetResolver.forVisionTasks(
                        'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm',
                    );
                    faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
                        baseOptions: {
                            modelAssetPath:
                                'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
                            delegate: 'GPU',
                        },
                        runningMode: 'VIDEO',
                        outputFaceBlendshapes: false,
                        numFaces: 1,
                    });
                    return true;
                } catch {
                    return false;
                }
            }

            function stopSnapPresenceLoop() {
                if (snapDetectRaf !== null) {
                    cancelAnimationFrame(snapDetectRaf);
                    snapDetectRaf = null;
                }
            }

            function startSnapPresenceLoop() {
                stopSnapPresenceLoop();
                const tick = () => {
                    if (!snapStream || !snapVideo || snapVideo.readyState < 2) {
                        snapDetectRaf = requestAnimationFrame(tick);
                        return;
                    }
                    if (!faceLandmarker) {
                        snapDetectRaf = requestAnimationFrame(tick);
                        return;
                    }
                    const result = faceLandmarker.detectForVideo(snapVideo, performance.now());
                    const hasFace = Array.isArray(result.faceLandmarks) && result.faceLandmarks.length > 0;
                    if (hasFace) {
                        snapHumanFrames += 1;
                    } else {
                        snapHumanFrames = 0;
                    }
                    if (snapHumanFrames >= 4) {
                        stopSnapPresenceLoop();
                        btnSnapCapture?.classList.remove('hidden');
                        snapStatus.textContent = '{{ __('You are visible — capture when you are ready.') }}';
                        return;
                    }
                    snapDetectRaf = requestAnimationFrame(tick);
                };
                snapDetectRaf = requestAnimationFrame(tick);
            }

            btnSnapOpen?.addEventListener('click', async () => {
                snapStatus.textContent = '';
                snapMpBypass = false;
                snapHumanFrames = 0;
                btnSnapCapture?.classList.add('hidden');
                try {
                    snapStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    snapVideo.srcObject = snapStream;
                    snapWrap?.classList.remove('hidden');
                    await snapVideo.play();
                    const mpOk = await initSnapFaceLandmarker();
                    if (mpOk) {
                        snapStatus.textContent = '{{ __('Align your face in the frame. Capture unlocks when we detect you on camera.') }}';
                        startSnapPresenceLoop();
                    } else {
                        snapMpBypass = true;
                        btnSnapCapture?.classList.remove('hidden');
                        snapStatus.textContent =
                            '{{ __('Camera active. Automated presence checks could not load — you may capture when you are ready.') }}';
                    }
                } catch {
                    snapStatus.textContent = '{{ __('Camera access was denied or is unavailable. Allow camera in your browser settings to continue, or ask your coordinator if this exam does not require a photo.') }}';
                }
            });

            btnSnapCapture?.addEventListener('click', async () => {
                if (!snapVideo || snapVideo.readyState < 2) return;
                if (faceLandmarker && !snapMpBypass) {
                    const check = faceLandmarker.detectForVideo(snapVideo, performance.now());
                    const hasFace = Array.isArray(check.faceLandmarks) && check.faceLandmarks.length > 0;
                    if (!hasFace) {
                        snapStatus.textContent = '{{ __('Step back into the frame, wait a second, then try capture again.') }}';
                        return;
                    }
                }
                const w = snapVideo.videoWidth || 640;
                const h = snapVideo.videoHeight || 480;
                const tw = Math.min(720, w);
                const scale = tw / w;
                const th = Math.max(1, Math.floor(h * scale));
                snapCanvas.width = tw;
                snapCanvas.height = th;
                const ctx = snapCanvas.getContext('2d');
                if (!ctx) return;
                ctx.drawImage(snapVideo, 0, 0, tw, th);
                verificationBlob = await new Promise((resolve) => {
                    snapCanvas.toBlob((b) => resolve(b), 'image/jpeg', 0.85);
                });
                await stopSnapCamera();
                btnSnapOpen?.classList.add('hidden');
                btnSnapCapture?.classList.add('hidden');
                btnSnapRetake?.classList.remove('hidden');
                snapStatus.textContent = '{{ __('Photo captured. Continue to start the exam.') }}';
                showPanel('start');
                setPillActive('start');
            });

            btnSnapRetake?.addEventListener('click', async () => {
                verificationBlob = null;
                btnSnapOpen?.classList.remove('hidden');
                btnSnapCapture?.classList.add('hidden');
                btnSnapRetake?.classList.add('hidden');
                snapWrap?.classList.add('hidden');
                snapStatus.textContent = '';
                showPanel('snapshot');
                setPillActive('snapshot');
            });

            document.getElementById('btn-start-exam')?.addEventListener('click', async () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('start-message');
                if (otpEnabled && !otpVerified) {
                    msgEl.textContent = '{{ __('Complete phone verification first.') }}';
                    return;
                }
                if (snapshotRequired && !verificationBlob) {
                    msgEl.textContent = '{{ __('Capture your verification photo before starting.') }}';
                    showPanel('snapshot');
                    setPillActive('snapshot');
                    return;
                }
                msgEl.textContent = '{{ __('Starting…') }}';
                const hw = typeof navigator.hardwareConcurrency === 'number' ? navigator.hardwareConcurrency : null;
                let res;
                let body;
                if (snapshotRequired && verificationBlob) {
                    ({ res, body } = await postStartMultipart({ hardware_concurrency: hw }));
                } else {
                    ({ res, body } = await postJson(@json(route('exam-sessions.start')), {
                        exam_id: examId,
                        hardware_concurrency: hw,
                    }));
                }
                if (res.ok && body?.session_id) {
                    window.location.href = `/student/exam/${encodeURIComponent(body.session_id)}`;
                    return;
                }
                if (res.ok && (body?.status === 'otp_required' || body?.status === 'otp_pending')) {
                    msgEl.textContent = body.message || '';
                    showPanel('otp');
                    setPillActive('otp');
                    return;
                }
                msgEl.textContent = friendlyStartError(res.status, body);
            });

            showPanel('rules');
            setPillActive('rules');

            window.addEventListener('beforeunload', () => {
                void stopSnapCamera();
            });
        </script>
    @endpush
</x-layouts.student>
