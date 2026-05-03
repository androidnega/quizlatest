<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">
            {{ __('Exam entry') }} — {{ $quiz->title }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
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
                @if ($faceRequired)
                    <span id="step-pill-3" class="rounded-full border border-qs-soft px-3 py-1">{{ __('Face') }}</span>
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
                    @if ($faceRequired)
                        <p class="mt-2 text-sm text-qs-muted">{{ __('You will verify your face against your enrolled portrait before the timer starts.') }}</p>
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

                @if ($faceRequired)
                    <section id="panel-face" class="hidden">
                        <h3 class="text-lg font-semibold text-qs-text">{{ __('Face verification') }}</h3>
                        <p class="mt-2 text-sm text-qs-muted">{{ __('Align your face in the frame, then capture. You may retry once if the check does not pass.') }}</p>
                        <video id="face-video" class="mt-4 hidden w-full rounded-lg border border-qs-soft bg-black" autoplay muted playsinline></video>
                        <canvas id="face-canvas" class="hidden"></canvas>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" id="face-start-camera" class="qs-btn-secondary text-sm">{{ __('Start camera') }}</button>
                            <button type="button" id="face-capture" class="qs-btn-primary text-sm">{{ __('Capture face') }}</button>
                        </div>
                        <p id="face-status" class="mt-3 text-sm text-qs-muted" role="status"></p>
                        <button type="button" id="btn-face-back" class="qs-btn-secondary mt-6 text-sm">{{ __('Back') }}</button>
                        <button type="button" id="btn-face-next" class="qs-btn-primary mt-3 ms-0 text-sm sm:ms-3" disabled>{{ __('Continue') }}</button>
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
    </div>

    @push('scripts')
        <script type="module">
            import { FilesetResolver, FaceLandmarker } from 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14';

            const examId = {{ (int) $quiz->id }};
            const otpEnabled = @json($otpEnabled);
            const faceRequired = @json($faceRequired);
            const entryBlocked = @json($entryBlocked);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const panelOverview = document.getElementById('panel-overview');
            const panelOtp = document.getElementById('panel-otp');
            const panelFace = document.getElementById('panel-face');
            const panelStart = document.getElementById('panel-start');

            function showPanel(name) {
                if (panelOverview) panelOverview.classList.toggle('hidden', name !== 'overview');
                if (panelOtp) panelOtp.classList.toggle('hidden', name !== 'otp');
                if (panelFace) panelFace.classList.toggle('hidden', name !== 'face');
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
                if (step === 'face') on('step-pill-3');
                if (step === 'start') on('step-pill-start');
            }

            async function parseJsonSafe(res) {
                try {
                    return await res.json();
                } catch {
                    return {};
                }
            }

            function friendlyStartError(status, body, rawText) {
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
                if (typeof msg === 'string' && msg.includes('Face')) {
                    return msg;
                }
                if (status === 422) {
                    return '{{ __('We could not start the exam with the details provided. Review the steps or contact support.') }}';
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

            let otpVerified = !otpEnabled;
            let faceEmbedding = null;
            let faceRetryAttempt = 0;

            document.getElementById('btn-overview-next')?.addEventListener('click', () => {
                if (entryBlocked) return;
                if (otpEnabled) {
                    showPanel('otp');
                    setPillActive('otp');
                } else if (faceRequired) {
                    showPanel('face');
                    setPillActive('face');
                } else {
                    showPanel('start');
                    setPillActive('start');
                }
            });

            document.getElementById('btn-otp-back')?.addEventListener('click', () => {
                showPanel('overview');
                setPillActive('overview');
            });

            document.getElementById('btn-face-back')?.addEventListener('click', () => {
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
                msgEl.textContent = friendlyStartError(res.status, body, '');
            });

            function afterOtpOk() {
                if (faceRequired) {
                    showPanel('face');
                    setPillActive('face');
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

            /** Face capture (same embedding shape as registration) */
            let faceLandmarker = null;
            let faceStream = null;
            const video = document.getElementById('face-video');
            const canvas = document.getElementById('face-canvas');
            const faceStatus = document.getElementById('face-status');
            const btnFaceNext = document.getElementById('btn-face-next');

            async function initLandmarker() {
                if (faceLandmarker) return faceLandmarker;
                const vision = await FilesetResolver.forVisionTasks(
                    'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm',
                );
                faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
                    baseOptions: {
                        modelAssetPath:
                            'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
                    },
                    runningMode: 'IMAGE',
                    numFaces: 1,
                });
                return faceLandmarker;
            }

            function buildEmbedding(landmarks) {
                const points = [1, 33, 61, 199, 263, 291];
                const vector = points.flatMap((idx) => {
                    const p = landmarks[idx] || { x: 0, y: 0, z: 0 };
                    return [p.x, p.y, p.z];
                });
                const mag = Math.sqrt(vector.reduce((sum, v) => sum + v * v, 0)) || 1;
                return vector.map((v) => Number((v / mag).toFixed(6)));
            }

            document.getElementById('face-start-camera')?.addEventListener('click', async () => {
                try {
                    faceStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    video.srcObject = faceStream;
                    video.classList.remove('hidden');
                    faceStatus.textContent = '{{ __('Camera active. Capture when ready.') }}';
                } catch {
                    faceStatus.textContent = '{{ __('Camera access was denied or is unavailable.') }}';
                }
            });

            document.getElementById('face-capture')?.addEventListener('click', async () => {
                if (!video?.videoWidth || !video?.videoHeight) {
                    faceStatus.textContent = '{{ __('Camera not ready yet.') }}';
                    return;
                }
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.82));
                if (!blob) {
                    faceStatus.textContent = '{{ __('Could not capture image.') }}';
                    return;
                }
                const landmarker = await initLandmarker();
                const bitmap = await createImageBitmap(blob);
                const result = landmarker.detect(bitmap);
                const landmarks = result?.faceLandmarks?.[0];
                if (!landmarks) {
                    faceStatus.textContent = '{{ __('No face detected. Try again.') }}';
                    return;
                }
                faceEmbedding = buildEmbedding(landmarks);
                faceStatus.textContent = '{{ __('Face sample captured.') }}';
                btnFaceNext.disabled = false;
            });

            document.getElementById('btn-face-next')?.addEventListener('click', () => {
                showPanel('start');
                setPillActive('start');
            });

            document.getElementById('btn-start-exam')?.addEventListener('click', async () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('start-message');
                if (otpEnabled && !otpVerified) {
                    msgEl.textContent = '{{ __('Complete phone verification first.') }}';
                    return;
                }
                if (faceRequired && (!faceEmbedding || faceEmbedding.length < 3)) {
                    msgEl.textContent = '{{ __('Capture your face before starting.') }}';
                    return;
                }
                msgEl.textContent = '{{ __('Starting…') }}';
                const payload = {
                    exam_id: examId,
                    face_retry_attempt: faceRetryAttempt,
                    hardware_concurrency: typeof navigator.hardwareConcurrency === 'number' ? navigator.hardwareConcurrency : null,
                };
                if (faceRequired && faceEmbedding) {
                    payload.face_embedding = faceEmbedding;
                }
                const { res, body } = await postJson(@json(route('exam-sessions.start')), payload);
                if (res.ok && body?.session_id) {
                    window.location.href = `/student/exam/${encodeURIComponent(body.session_id)}`;
                    return;
                }
                if (res.status === 422) {
                    const raw = body?.message || '';
                    if (typeof raw === 'string' && raw.includes('Retry once') && faceRetryAttempt === 0) {
                        faceRetryAttempt = 1;
                        msgEl.textContent = '{{ __('Face check did not pass. Adjust lighting and try once more from the face step.') }}';
                        showPanel('face');
                        setPillActive('face');
                        return;
                    }
                }
                msgEl.textContent = friendlyStartError(res.status, body, '');
            });

            showPanel('overview');
            setPillActive('overview');
        </script>
    @endpush
</x-app-layout>
