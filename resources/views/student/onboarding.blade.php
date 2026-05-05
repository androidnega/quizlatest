<x-guest-layout
    content-max="max-w-3xl"
    :show-header="false"
    :page-title="__('Complete your profile')"
    :eyebrow="__('First-time setup')"
    :heading="__('Finish enrolling your account')"
    :description="__('Complete the 3 quick steps below.')"
>
    <form
        id="onboarding-form"
        method="POST"
        action="{{ route('student.onboarding.store') }}"
        class="space-y-8"
        enctype="multipart/form-data"
        x-data="{
            step: {{ (int) old('step', (int) ($draft['step'] ?? 1)) }},
            maxStep: 3,
            stepError: '',
            nextStep() {
                this.stepError = '';
                if (this.step === 1) {
                    const name = document.getElementById('name');
                    if (name?.hasAttribute('required') && !String(name.value || '').trim()) {
                        this.stepError = '{{ __('Please enter your full name to continue.') }}';
                        return;
                    }
                }
                if (this.step === 2) {
                    const p1 = document.getElementById('password');
                    const p2 = document.getElementById('password_confirmation');
                    const v1 = String(p1?.value || '');
                    const v2 = String(p2?.value || '');
                    if (!v1 || !v2) {
                        this.stepError = '{{ __('Enter and confirm your password to continue.') }}';
                        return;
                    }
                    if (v1 !== v2) {
                        this.stepError = '{{ __('Passwords do not match.') }}';
                        return;
                    }
                    if (v1.length < 8) {
                        this.stepError = '{{ __('Use at least 8 characters for your password.') }}';
                        return;
                    }
                }
                this.step = Math.min(this.maxStep, this.step + 1);
                window.__onboardingSaveStep?.(this.step);
            },
            prevStep() {
                this.stepError = '';
                this.step = Math.max(1, this.step - 1);
                window.__onboardingSaveStep?.(this.step);
            }
        }"
        data-onboarding-user-id="{{ $user->id }}"
    >
        @csrf

        <div class="mb-3 sm:hidden">
            <div class="flex items-center justify-between text-xs font-semibold text-qs-muted">
                <span>{{ __('Step') }} <span x-text="step"></span> {{ __('of') }} <span x-text="maxStep"></span></span>
                <span x-text="step === 1 ? '{{ __('Profile') }}' : (step === 2 ? '{{ __('Password') }}' : '{{ __('Face setup') }}')"></span>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-slate-200">
                <div class="h-1.5 rounded-full bg-emerald-600 transition-all duration-200" :style="`width: ${(step / maxStep) * 100}%`"></div>
            </div>
        </div>

        <div class="mb-2 hidden flex-wrap gap-2 sm:flex">
            <span :class="step >= 1 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 1') }} · {{ __('Profile') }}</span>
            <span :class="step >= 2 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 2') }} · {{ __('Password') }}</span>
            <span :class="step >= 3 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 3') }} · {{ __('Face setup') }}</span>
        </div>

        <section x-show="step === 1" x-cloak class="grid gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('Full name')" />
                <input id="name" name="name" type="text" value="{{ old('name', $draft['name'] ?? $user->name) }}" class="qs-input" placeholder="{{ __('Full name') }}" @if (trim((string) $user->name) === '') required @endif />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
        </section>

        <section x-show="step === 2" x-cloak class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <input id="password" name="password" type="password" required autocomplete="new-password" class="qs-input" placeholder="{{ __('Create password') }}" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="qs-input" placeholder="{{ __('Confirm password') }}" />
            </div>
        </section>

        <section x-show="step === 3" x-cloak class="qs-surface space-y-4 p-6">
            <h2 class="text-lg font-semibold text-qs-text">{{ __('Face enrollment') }}</h2>
            <p class="text-sm text-qs-muted">{{ __('Start camera, then capture two clear face samples.') }}</p>
            <video id="ob-video" class="mt-2 hidden w-full max-w-md rounded-lg border border-qs-soft bg-black" autoplay muted playsinline></video>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="ob-start" class="qs-btn-secondary text-sm">{{ __('Start camera') }}</button>
                <button type="button" id="ob-capture" class="qs-btn-secondary text-sm" disabled>{{ __('Capture sample 1') }}</button>
                <button type="button" id="ob-retry" class="qs-btn-secondary text-sm" disabled>{{ __('Retry') }}</button>
            </div>
            <p id="ob-status" class="text-sm text-qs-muted" role="status"></p>
            <p id="ob-error" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></p>
            <x-input-error :messages="$errors->get('face')" class="mt-2" />
            <input type="hidden" name="face_embedding_json" id="face_embedding_json" value="{{ old('face_embedding_json', $draft['face_embedding_json'] ?? '') }}" />
            <input type="hidden" name="face_liveness_embedding_json" id="face_liveness_embedding_json" value="{{ old('face_liveness_embedding_json', $draft['face_liveness_embedding_json'] ?? '') }}" />
            <input type="hidden" name="step" id="onboarding_step" value="{{ (int) old('step', (int) ($draft['step'] ?? 1)) }}" />
            <div>
                <x-input-label for="face_snapshot" :value="__('Portrait photo (required)')" />
                <input id="face_snapshot" name="face_snapshot" type="file" accept="image/jpeg,image/png" class="mt-1 block w-full text-sm text-qs-text file:mr-4 file:rounded-lg file:border-0 file:bg-qs-card file:px-4 file:py-2 file:text-sm file:font-semibold file:text-qs-text" />
                <x-input-error :messages="$errors->get('face_snapshot')" class="mt-2" />
            </div>
        </section>

        <p x-show="stepError" x-text="stepError" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

        <div class="flex flex-wrap gap-3">
            <button type="button" x-show="step > 1" @click="prevStep()" class="qs-btn-secondary">
                {{ __('Back') }}
            </button>
            <button type="button" x-show="step < maxStep" @click="nextStep()" class="qs-btn-secondary">
                {{ __('Next') }}
            </button>
            <button type="submit" x-show="step === maxStep" class="qs-btn-primary">
                {{ __('Complete setup and sign in') }}
            </button>
        </div>
    </form>
</x-guest-layout>

@push('scripts')
    <script type="module">
        const statusEl = document.getElementById('ob-status');
        const video = document.getElementById('ob-video');
        const btnStart = document.getElementById('ob-start');
        const btnCapture = document.getElementById('ob-capture');
        const btnRetry = document.getElementById('ob-retry');
        const hid1 = document.getElementById('face_embedding_json');
        const hid2 = document.getElementById('face_liveness_embedding_json');
        const form = document.getElementById('onboarding-form');
        const errorEl = document.getElementById('ob-error');
        const nameInput = document.getElementById('name');
        const pwdInput = document.getElementById('password');
        const pwdConfInput = document.getElementById('password_confirmation');
        const faceSnapshotInput = document.getElementById('face_snapshot');
        const stepInput = document.getElementById('onboarding_step');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        let stream = null;
        let service = null;
        let firstSample = null;
        let faceGuidanceAvailable = false;

        function setStatus(t) {
            statusEl.textContent = t || '';
        }

        function clearError() {
            if (!errorEl) return;
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }

        function showError(context, error) {
            const name = String(error?.name || 'Error');
            const msg = String(error?.message || error || 'Unknown error');
            const lower = `${name} ${msg}`.toLowerCase();
            let composed = `${context}: ${name} — ${msg}`;
            if (lower.includes('notallowederror') || lower.includes('permission denied') || lower.includes('permission dismissed')) {
                composed = '{{ __('Camera permission denied. Please allow camera access and try again.') }}';
            } else if (lower.includes('notfounderror') || lower.includes('devicesnotfounderror') || lower.includes('requested device not found')) {
                composed = '{{ __('No camera was found on this device.') }}';
            } else if (lower.includes('notreadableerror') || lower.includes('track start') || lower.includes('device in use')) {
                composed = '{{ __('Camera could not start because another app may be using it.') }}';
            } else if (lower.includes('securityerror') || lower.includes('insecure context')) {
                composed = '{{ __('Camera requires HTTPS on live servers. Use HTTPS or localhost.') }}';
            } else if (lower.includes('mediad evices unavailable') || lower.includes('media devices unavailable') || lower.includes('getusermedia')) {
                composed = '{{ __('Browser does not support camera access. Use a modern browser.') }}';
            }
            setStatus(composed);
            if (errorEl) {
                errorEl.textContent = composed;
                errorEl.classList.remove('hidden');
            }
            console.error('[onboarding-camera]', context, error);
        }

        async function resolveFaceTemplateService() {
            const loader = window.loadFaceTemplateService;
            if (typeof loader === 'function') {
                return await loader();
            }
            const mod = await import(@json(\Illuminate\Support\Facades\Vite::asset('resources/js/faceTemplateService.js')));
            return mod.FaceTemplateService;
        }

        async function saveDraft(patch) {
            if (!form || !csrfToken) return;
            try {
                await fetch(@json(route('student.onboarding.draft')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        step: Number(patch?.step),
                        name: typeof patch?.name === 'string' ? patch.name : undefined,
                        face_embedding_json: typeof patch?.face_embedding_json === 'string' ? patch.face_embedding_json : undefined,
                        face_liveness_embedding_json: typeof patch?.face_liveness_embedding_json === 'string' ? patch.face_liveness_embedding_json : undefined,
                    }),
                });
            } catch (_) {
                // Non-blocking draft save.
            }
        }

        function captureFrameData(videoElement) {
            const canvas = document.createElement('canvas');
            canvas.width = videoElement.videoWidth || 640;
            canvas.height = videoElement.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            if (!ctx) return null;
            ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
            return { canvas, ctx };
        }

        function buildFallbackEmbedding(videoElement) {
            const frame = captureFrameData(videoElement);
            if (!frame) return null;
            const { canvas, ctx } = frame;
            const gridW = 6;
            const gridH = 3;
            const cellW = Math.floor(canvas.width / gridW);
            const cellH = Math.floor(canvas.height / gridH);
            const vector = [];
            for (let gy = 0; gy < gridH; gy++) {
                for (let gx = 0; gx < gridW; gx++) {
                    const x = gx * cellW;
                    const y = gy * cellH;
                    const img = ctx.getImageData(x, y, Math.max(1, cellW), Math.max(1, cellH)).data;
                    let sum = 0;
                    let px = 0;
                    for (let i = 0; i < img.length; i += 4) {
                        sum += (img[i] + img[i + 1] + img[i + 2]) / 3;
                        px++;
                    }
                    vector.push(px > 0 ? (sum / px) / 255 : 0);
                }
            }
            const mag = Math.sqrt(vector.reduce((acc, v) => acc + (v * v), 0)) || 1;
            return vector.map((v) => Number((v / mag).toFixed(6)));
        }

        function computeSimilarity(a, b) {
            if (service && typeof service.similarityPercent === 'function') {
                return service.similarityPercent(a, b);
            }
            const length = Math.min(a.length, b.length);
            if (length === 0) return 0;
            let dot = 0;
            let normA = 0;
            let normB = 0;
            for (let i = 0; i < length; i++) {
                dot += a[i] * b[i];
                normA += a[i] * a[i];
                normB += b[i] * b[i];
            }
            if (normA <= 0 || normB <= 0) return 0;
            const cosine = dot / (Math.sqrt(normA) * Math.sqrt(normB));
            return Number((((Math.max(-1, Math.min(1, cosine)) + 1) / 2) * 100).toFixed(2));
        }

        async function extractSample(videoElement) {
            if (service && typeof service.extractEmbeddingFromVideo === 'function') {
                try {
                    const sample = await service.extractEmbeddingFromVideo(videoElement);
                    if (sample) return sample;
                } catch (_) {
                    faceGuidanceAvailable = false;
                    setStatus('{{ __('Face guidance is unavailable, but camera capture can continue.') }}');
                }
            }
            return buildFallbackEmbedding(videoElement);
        }

        function restoreDraft() {
            if (hid1.value && hid2.value) {
                btnCapture.textContent = '{{ __('Capture complete') }}';
                btnCapture.disabled = true;
                setStatus('{{ __('Saved face samples restored. You can submit or tap Retry.') }}');
                btnRetry.disabled = false;
            }
        }

        window.__onboardingSaveStep = function(step) {
            const normalizedStep = Number(step) || 1;
            if (stepInput) stepInput.value = String(normalizedStep);
            saveDraft({ step: normalizedStep, name: nameInput?.value || '' });
        };

        async function stopCamera() {
            if (stream) {
                stream.getTracks().forEach((t) => t.stop());
                stream = null;
            }
        }

        function resetCaptureState() {
            firstSample = null;
            hid1.value = '';
            hid2.value = '';
            btnCapture.disabled = true;
            btnCapture.textContent = '{{ __('Capture sample 1') }}';
        }

        btnStart.addEventListener('click', async () => {
            try {
                clearError();
                setStatus('{{ __('Starting camera…') }}');
                btnStart.disabled = true;
                resetCaptureState();
                service = null;
                faceGuidanceAvailable = false;
                try {
                    const FaceTemplateService = await resolveFaceTemplateService();
                    service = new FaceTemplateService();
                    faceGuidanceAvailable = true;
                } catch (_) {
                    setStatus('{{ __('Face guidance is unavailable, but camera capture can continue.') }}');
                }
                if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                    throw new Error('media devices unavailable');
                }
                if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                    throw new Error('insecure context');
                }
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                video.srcObject = stream;
                video.classList.remove('hidden');
                await video.play();
                btnStart.disabled = true;
                btnStart.textContent = '{{ __('Camera ready') }}';
                btnRetry.disabled = false;
                btnCapture.disabled = false;
                setStatus('{{ __('Camera started. Please capture two clear face samples.') }}');
            } catch (e) {
                showError('Start camera failed', e);
                btnStart.disabled = false;
                btnStart.textContent = '{{ __('Start camera') }}';
            } finally {
                if (!stream) {
                    btnStart.disabled = false;
                }
            }
        });

        btnRetry.addEventListener('click', async () => {
            await stopCamera();
            resetCaptureState();
            btnStart.disabled = false;
            btnStart.textContent = '{{ __('Start camera') }}';
            setStatus('{{ __('Camera reset. Start camera again.') }}');
        });

        btnCapture.addEventListener('click', async () => {
            try {
                clearError();
                if (!video.srcObject) return;
                const sample = await extractSample(video);
                if (!sample) {
                    setStatus('{{ __('No clear face detected. Keep your face visible and try again.') }}');
                    return;
                }
                if (!firstSample) {
                    firstSample = sample;
                    hid1.value = JSON.stringify(sample);
                    saveDraft({ face_embedding_json: hid1.value });
                    btnCapture.textContent = '{{ __('Capture sample 2') }}';
                    setStatus('{{ __('Sample 1 captured. Slightly adjust and capture sample 2.') }}');
                    return;
                }
                hid2.value = JSON.stringify(sample);
                saveDraft({ face_liveness_embedding_json: hid2.value });
                const sim = computeSimilarity(firstSample, sample);
                if (sim < 68) {
                    setStatus('{{ __('Samples are too different. Tap Retry and capture again.') }}');
                    return;
                }
                const snapshotCanvas = document.createElement('canvas');
                snapshotCanvas.width = video.videoWidth || 640;
                snapshotCanvas.height = video.videoHeight || 480;
                const ctx = snapshotCanvas.getContext('2d');
                if (ctx) {
                    ctx.drawImage(video, 0, 0, snapshotCanvas.width, snapshotCanvas.height);
                    const blob = await new Promise((resolve) => snapshotCanvas.toBlob(resolve, 'image/jpeg', 0.9));
                    if (blob) {
                        const file = new File([blob], 'onboarding-portrait.jpg', { type: 'image/jpeg' });
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        faceSnapshotInput.files = dt.files;
                    }
                }
                setStatus('{{ __('Sample 2 captured. Face enrollment ready.') }}');
                btnCapture.disabled = true;
                await stopCamera();
            } catch (e) {
                showError('Capture failed', e);
            }
        });

        form.addEventListener('submit', (e) => {
            clearError();
            const p1 = String(pwdInput?.value || '');
            const p2 = String(pwdConfInput?.value || '');
            if (!p1 || !p2) {
                e.preventDefault();
                setStatus('{{ __('Password is required before completing onboarding.') }}');
                return;
            }
            if (p1 !== p2) {
                e.preventDefault();
                setStatus('{{ __('Passwords do not match.') }}');
                return;
            }
            if (!hid1.value || !hid2.value) {
                e.preventDefault();
                setStatus('{{ __('Complete face capture before submitting.') }}');
                return;
            }
            const a = JSON.parse(hid1.value);
            const b = JSON.parse(hid2.value);
            const sim = computeSimilarity(a, b);
            if (sim < 78 || sim > 99.95) {
                e.preventDefault();
                setStatus('{{ __('The two samples did not match enough for a live check. Wait a second between captures and try again.') }}');
            }
        });

        [nameInput, pwdInput, pwdConfInput].forEach((el) => {
            if (!el) return;
            el.addEventListener('input', () => {
                saveDraft({ name: nameInput?.value || '' });
            });
        });

        restoreDraft();

        window.addEventListener('beforeunload', () => {
            stopCamera();
        });

        if (!window.navigator || !window.navigator.mediaDevices) {
            showError('Camera unavailable', new Error('media devices unavailable'));
        }

        window.addEventListener('unhandledrejection', (event) => {
            showError('Unhandled promise rejection', event?.reason);
        });
        window.addEventListener('error', (event) => {
            if (event?.error) showError('Runtime error', event.error);
        });
    </script>
@endpush
