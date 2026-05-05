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
                <span id="step-pill-1" class="rounded-full border border-qs-accent bg-qs-accent/20 px-3 py-1 text-qs-text">{{ __('1 Overview') }}</span>
                @if ($otpEnabled)
                    <span id="step-pill-2" class="rounded-full border border-qs-soft px-3 py-1">{{ __('2 OTP') }}</span>
                @endif
                @if ($snapshotRequired)
                    <span id="step-pill-3" class="rounded-full border border-qs-soft px-3 py-1">{{ __('Camera') }}</span>
                @endif
                <span id="step-pill-start" class="rounded-full border border-qs-soft px-3 py-1">{{ __('Start') }}</span>
            </div>

            <div class="qs-surface space-y-6 p-6 shadow-sm">
                <section id="panel-overview">
                    <h3 class="text-lg font-semibold text-qs-text">{{ __('Exam overview') }}</h3>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex justify-between gap-4 border-b border-qs-soft pb-2">
                            <dt class="text-qs-muted">{{ __('Course') }}</dt>
                            <dd class="text-right font-medium text-qs-text">{{ $quiz->course?->code }} — {{ $quiz->course?->title }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-qs-soft pb-2">
                            <dt class="text-qs-muted">{{ __('Duration') }}</dt>
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
                    <button type="button" id="btn-overview-next" class="qs-btn-primary mt-6" @if ($entryBlocked) disabled @endif>
                        {{ __('Continue') }}
                    </button>
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
                        <p class="mt-2 text-sm text-qs-muted">{{ __('Allow camera access, align yourself in the frame, then capture one clear photo. This is session evidence only — not automated face matching.') }}</p>
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
                    <h3 class="text-lg font-semibold text-qs-text">{{ __('Ready to begin') }}</h3>
                    <p class="mt-2 text-sm text-qs-muted">{{ __('When you start, the exam timer may begin immediately according to your institution’s rules.') }}</p>
                    <p id="start-message" class="mt-3 text-sm text-qs-muted" role="status"></p>
                    <button type="button" id="btn-start-exam" class="qs-btn-primary mt-6" @if ($entryBlocked) disabled @endif>
                        {{ __('Start exam now') }}
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
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const panelOverview = document.getElementById('panel-overview');
            const panelOtp = document.getElementById('panel-otp');
            const panelSnapshot = document.getElementById('panel-snapshot');
            const panelStart = document.getElementById('panel-start');

            let verificationBlob = null;
            let snapStream = null;

            function showPanel(name) {
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
                ['step-pill-1', 'step-pill-2', 'step-pill-3', 'step-pill-start'].forEach(reset);
                if (step === 'overview') on('step-pill-1');
                if (step === 'otp') on('step-pill-2');
                if (step === 'snapshot') on('step-pill-3');
                if (step === 'start') on('step-pill-start');
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
                if (snapStream) {
                    snapStream.getTracks().forEach((t) => t.stop());
                    snapStream = null;
                }
                if (snapVideo) snapVideo.srcObject = null;
            }

            btnSnapOpen?.addEventListener('click', async () => {
                snapStatus.textContent = '';
                try {
                    snapStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    snapVideo.srcObject = snapStream;
                    snapWrap?.classList.remove('hidden');
                    await snapVideo.play();
                    btnSnapCapture?.classList.remove('hidden');
                    snapStatus.textContent = '{{ __('Camera active. When you are ready, capture one clear photo.') }}';
                } catch {
                    snapStatus.textContent = '{{ __('Camera access was denied or is unavailable. Allow camera in your browser settings to continue, or ask your coordinator if this exam does not require a photo.') }}';
                }
            });

            btnSnapCapture?.addEventListener('click', async () => {
                if (!snapVideo || snapVideo.readyState < 2) return;
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

            showPanel('overview');
            setPillActive('overview');

            window.addEventListener('beforeunload', () => {
                void stopSnapCamera();
            });
        </script>
    @endpush
</x-layouts.student>
