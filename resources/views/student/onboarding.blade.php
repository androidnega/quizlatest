<x-guest-layout
    content-max="max-w-3xl"
    :show-header="false"
    :page-title="__('Complete your profile')"
    :eyebrow="__('First-time setup')"
    :heading="__('Finish enrolling your account')"
    :description="__('Complete the 3 quick steps below.')"
>
    <form id="onboarding-form" method="POST" action="{{ route('student.onboarding.store') }}" class="space-y-8" enctype="multipart/form-data" x-data="{ step: 1, maxStep: 3 }">
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
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="qs-input" placeholder="{{ __('Full name') }}" @if (trim((string) $user->name) === '') required @endif />
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
            <p class="text-sm text-qs-muted">{{ __('Follow the guidance below. This takes a few seconds and helps exam face verification.') }}</p>
            <ol class="grid gap-1 text-xs text-qs-muted sm:grid-cols-2">
                <li id="ob-step-center">1. {{ __('Center your face') }}</li>
                <li id="ob-step-left">2. {{ __('Move slightly left') }}</li>
                <li id="ob-step-right">3. {{ __('Move slightly right') }}</li>
                <li id="ob-step-still">4. {{ __('Blink or hold still') }}</li>
                <li id="ob-step-done" class="sm:col-span-2">5. {{ __('Capture complete') }}</li>
            </ol>
            <video id="ob-video" class="mt-2 hidden w-full max-w-md rounded-lg border border-qs-soft bg-black" autoplay muted playsinline></video>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="ob-start" class="qs-btn-secondary text-sm">{{ __('Start camera') }}</button>
                <button type="button" id="ob-retry" class="qs-btn-secondary text-sm" disabled>{{ __('Retry') }}</button>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                <div id="ob-progress" class="h-2 w-0 rounded-full bg-emerald-600 transition-all duration-200"></div>
            </div>
            <p id="ob-status" class="text-sm text-qs-muted" role="status"></p>
            <x-input-error :messages="$errors->get('face')" class="mt-2" />
            <input type="hidden" name="face_embedding_json" id="face_embedding_json" value="{{ old('face_embedding_json') }}" />
            <input type="hidden" name="face_liveness_embedding_json" id="face_liveness_embedding_json" value="{{ old('face_liveness_embedding_json') }}" />
            <div>
                <x-input-label for="face_snapshot" :value="__('Portrait photo (optional)')" />
                <input id="face_snapshot" name="face_snapshot" type="file" accept="image/jpeg,image/png" class="mt-1 block w-full text-sm text-qs-text file:mr-4 file:rounded-lg file:border-0 file:bg-qs-card file:px-4 file:py-2 file:text-sm file:font-semibold file:text-qs-text" />
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="button" x-show="step > 1" @click="step = Math.max(1, step - 1)" class="qs-btn-secondary">
                {{ __('Back') }}
            </button>
            <button type="button" x-show="step < maxStep" @click="step = Math.min(maxStep, step + 1)" class="qs-btn-secondary">
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
        const btnRetry = document.getElementById('ob-retry');
        const progressBar = document.getElementById('ob-progress');
        const hid1 = document.getElementById('face_embedding_json');
        const hid2 = document.getElementById('face_liveness_embedding_json');
        const form = document.getElementById('onboarding-form');
        const stepEls = {
            center: document.getElementById('ob-step-center'),
            left: document.getElementById('ob-step-left'),
            right: document.getElementById('ob-step-right'),
            still: document.getElementById('ob-step-still'),
            done: document.getElementById('ob-step-done'),
        };

        let stream = null;
        let detector = null;
        let detectTimer = null;
        let lastNoseX = null;
        let stableCount = 0;
        let checking = false;
        let state = {
            center: false,
            left: false,
            right: false,
            still: false,
            done: false,
        };
        let samples = [];

        function setStatus(t) {
            statusEl.textContent = t || '';
        }

        function updateStepUI() {
            const order = ['center', 'left', 'right', 'still', 'done'];
            let completed = 0;
            order.forEach((key) => {
                const el = stepEls[key];
                if (!el) return;
                if (state[key]) {
                    completed += 1;
                    el.classList.add('font-semibold', 'text-emerald-700');
                } else {
                    el.classList.remove('font-semibold', 'text-emerald-700');
                }
            });
            progressBar.style.width = `${(completed / order.length) * 100}%`;
        }

        async function stopCamera() {
            if (detectTimer) {
                clearInterval(detectTimer);
                detectTimer = null;
            }
            if (stream) {
                stream.getTracks().forEach((t) => t.stop());
                stream = null;
            }
        }

        function resetCaptureState() {
            state = { center: false, left: false, right: false, still: false, done: false };
            samples = [];
            lastNoseX = null;
            stableCount = 0;
            hid1.value = '';
            hid2.value = '';
            updateStepUI();
        }

        async function captureSample() {
            if (!detector) return null;
            const result = await detector.detectFromVideo(video);
            return result.embedding || null;
        }

        function averageEmbedding(vectors) {
            if (!Array.isArray(vectors) || vectors.length === 0) return null;
            const len = vectors[0].length;
            const out = new Array(len).fill(0);
            vectors.forEach((v) => {
                for (let i = 0; i < len; i += 1) out[i] += Number(v[i] || 0);
            });
            for (let i = 0; i < len; i += 1) out[i] /= vectors.length;
            const magnitude = Math.sqrt(out.reduce((sum, n) => sum + (n * n), 0)) || 1;
            return out.map((n) => Number((n / magnitude).toFixed(6)));
        }

        async function finalizeCapture() {
            const first = await captureSample();
            await new Promise((resolve) => setTimeout(resolve, 350));
            const second = await captureSample();
            await new Promise((resolve) => setTimeout(resolve, 350));
            const third = await captureSample();
            const valid = [first, second, third].filter(Boolean);
            if (valid.length < 2) {
                setStatus('{{ __('Could not capture enough clear samples. Tap Retry.') }}');
                return;
            }
            const base = valid[0];
            const consistencyOk = valid.slice(1).every((v) => detector.similarityPercent(base, v) >= 72);
            if (!consistencyOk) {
                setStatus('{{ __('Face samples were inconsistent. Keep still and tap Retry.') }}');
                return;
            }
            const finalTemplate = averageEmbedding(valid);
            hid1.value = JSON.stringify(finalTemplate);
            hid2.value = JSON.stringify(valid[valid.length - 1]);
            state.done = true;
            updateStepUI();
            setStatus('{{ __('Capture complete. You can now submit.') }}');
            btnRetry.disabled = false;
            await stopCamera();
        }

        async function evaluateFrame() {
            if (checking || document.hidden || !detector || !video.srcObject || state.done) return;
            checking = true;
            try {
                const result = await detector.detectFromVideo(video);
                if (!result || result.faceCount < 1) {
                    setStatus('{{ __('Face not found. Center your face in the camera.') }}');
                    checking = false;
                    return;
                }
                if (result.faceCount > 1) {
                    setStatus('{{ __('One face only. Ask others to move out of frame.') }}');
                    checking = false;
                    return;
                }
                const m = result.metrics || {};
                if (m.tooFar) {
                    setStatus('{{ __('Move closer to the camera.') }}');
                    checking = false;
                    return;
                }
                if (m.tooClose) {
                    setStatus('{{ __('Move slightly back from the camera.') }}');
                    checking = false;
                    return;
                }
                if (!m.centered) {
                    setStatus('{{ __('Center your face in the frame.') }}');
                    checking = false;
                    return;
                }
                state.center = true;
                if (!state.left) {
                    setStatus('{{ __('Good. Now move your head slightly left.') }}');
                    if (m.noseX < 0.44) state.left = true;
                    updateStepUI();
                    checking = false;
                    return;
                }
                if (!state.right) {
                    setStatus('{{ __('Great. Now move your head slightly right.') }}');
                    if (m.noseX > 0.56) state.right = true;
                    updateStepUI();
                    checking = false;
                    return;
                }
                if (!state.still) {
                    if (lastNoseX !== null && Math.abs(m.noseX - lastNoseX) < 0.012) {
                        stableCount += 1;
                    } else {
                        stableCount = 0;
                    }
                    lastNoseX = m.noseX;
                    setStatus('{{ __('Almost done. Blink once or hold still briefly.') }}');
                    if (stableCount >= 2) {
                        state.still = true;
                        updateStepUI();
                        await finalizeCapture();
                    }
                }
            } catch (e) {
                setStatus('{{ __('Camera check failed. Tap Retry.') }}');
            } finally {
                checking = false;
            }
        }

        btnStart.addEventListener('click', async () => {
            try {
                resetCaptureState();
                const module = await import(@json(\Illuminate\Support\Facades\Vite::asset('resources/js/faceTemplateService.js')));
                detector = new module.FaceTemplateService();
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                video.srcObject = stream;
                video.classList.remove('hidden');
                await video.play();
                btnRetry.disabled = false;
                setStatus('{{ __('Camera ready. Follow the guidance steps.') }}');
                detectTimer = setInterval(evaluateFrame, 450);
            } catch (e) {
                setStatus('{{ __('Could not access the camera. Allow camera permission and tap Retry.') }}');
            }
        });

        btnRetry.addEventListener('click', async () => {
            await stopCamera();
            resetCaptureState();
            btnStart.click();
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden && detectTimer) {
                clearInterval(detectTimer);
                detectTimer = null;
            } else if (!document.hidden && !detectTimer && stream && !state.done) {
                detectTimer = setInterval(evaluateFrame, 450);
            }
        });

        form.addEventListener('submit', (e) => {
            if (!hid1.value || !hid2.value) {
                e.preventDefault();
                setStatus('{{ __('Complete face capture before submitting.') }}');
                return;
            }
            if (!detector) return;
            const a = JSON.parse(hid1.value);
            const b = JSON.parse(hid2.value);
            const sim = detector.similarityPercent(a, b);
            if (sim < 78 || sim > 99.95) {
                e.preventDefault();
                setStatus('{{ __('The two samples did not match enough for a live check. Wait a second between captures and try again.') }}');
            }
        });

        window.addEventListener('beforeunload', () => {
            stopCamera();
        });
    </script>
@endpush
