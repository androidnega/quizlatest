<x-guest-layout
    content-max="max-w-3xl"
    :page-title="__('Complete your profile')"
    :eyebrow="__('First-time setup')"
    :heading="__('Finish enrolling your account')"
    :description="__('Confirm your details, choose a password, and capture your face twice for a quick live check. Your phone was verified during sign-in.')"
>
    <form id="onboarding-form" method="POST" action="{{ route('student.onboarding.store') }}" class="space-y-8" enctype="multipart/form-data">
        @csrf

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" :value="__('Full name')" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" @if (trim((string) $user->name) === '') required @endif />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" name="password" type="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
            </div>
        </div>

        <div class="qs-surface space-y-4 p-6">
            <h2 class="text-lg font-semibold text-qs-text">{{ __('Face enrollment') }}</h2>
            <p class="text-sm text-qs-muted">{{ __('Allow camera access, then capture two samples a few seconds apart while looking at the camera.') }}</p>
            <video id="ob-video" class="mt-2 hidden w-full max-w-md rounded-lg border border-qs-soft bg-black" autoplay muted playsinline></video>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="ob-start" class="qs-btn-secondary text-sm">{{ __('Start camera') }}</button>
                <button type="button" id="ob-cap1" class="qs-btn-secondary text-sm" disabled>{{ __('Capture sample 1') }}</button>
                <button type="button" id="ob-cap2" class="qs-btn-secondary text-sm" disabled>{{ __('Capture sample 2') }}</button>
            </div>
            <p id="ob-status" class="text-sm text-qs-muted" role="status"></p>
            <x-input-error :messages="$errors->get('face')" class="mt-2" />
            <input type="hidden" name="face_embedding_json" id="face_embedding_json" value="{{ old('face_embedding_json') }}" />
            <input type="hidden" name="face_liveness_embedding_json" id="face_liveness_embedding_json" value="{{ old('face_liveness_embedding_json') }}" />
            <div>
                <x-input-label for="face_snapshot" :value="__('Portrait photo (optional)')" />
                <input id="face_snapshot" name="face_snapshot" type="file" accept="image/jpeg,image/png" class="mt-1 block w-full text-sm text-qs-text file:mr-4 file:rounded-lg file:border-0 file:bg-qs-card file:px-4 file:py-2 file:text-sm file:font-semibold file:text-qs-text" />
            </div>
        </div>

        <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
            {{ __('Complete setup and sign in') }}
        </button>
    </form>
</x-guest-layout>

@push('scripts')
    <script type="module">
        const statusEl = document.getElementById('ob-status');
        const video = document.getElementById('ob-video');
        const btnStart = document.getElementById('ob-start');
        const btn1 = document.getElementById('ob-cap1');
        const btn2 = document.getElementById('ob-cap2');
        const hid1 = document.getElementById('face_embedding_json');
        const hid2 = document.getElementById('face_liveness_embedding_json');
        const form = document.getElementById('onboarding-form');

        let stream = null;
        let emb1 = null;
        let emb2 = null;

        function setStatus(t) {
            statusEl.textContent = t || '';
        }

        btnStart.addEventListener('click', async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                video.srcObject = stream;
                video.classList.remove('hidden');
                await video.play();
                btn1.disabled = false;
                setStatus('{{ __('Camera ready. Capture sample 1.') }}');
            } catch (e) {
                setStatus('{{ __('Could not access the camera.') }}');
            }
        });

        async function captureEmbedding() {
            const FaceTemplateService = window.FaceTemplateService;
            if (!FaceTemplateService) {
                throw new Error('Face module unavailable');
            }
            const svc = new FaceTemplateService();
            return svc.extractEmbeddingFromVideo(video);
        }

        btn1.addEventListener('click', async () => {
            setStatus('{{ __('Hold still…') }}');
            emb1 = await captureEmbedding();
            if (!emb1) {
                setStatus('{{ __('No face detected. Try again.') }}');
                return;
            }
            hid1.value = JSON.stringify(emb1);
            btn2.disabled = false;
            setStatus('{{ __('Sample 1 saved. Wait a moment, then capture sample 2.') }}');
        });

        btn2.addEventListener('click', async () => {
            setStatus('{{ __('Hold still…') }}');
            emb2 = await captureEmbedding();
            if (!emb2) {
                setStatus('{{ __('No face detected. Try again.') }}');
                return;
            }
            hid2.value = JSON.stringify(emb2);
            const FaceTemplateService = window.FaceTemplateService;
            const svc = new FaceTemplateService();
            const sim = svc.similarityPercent(emb1, emb2);
            setStatus('{{ __('Samples recorded. Similarity:') }} ' + sim + '%');
        });

        form.addEventListener('submit', (e) => {
            if (!hid1.value || !hid2.value) {
                e.preventDefault();
                setStatus('{{ __('Capture both face samples before submitting.') }}');
                return;
            }
            const FaceTemplateService = window.FaceTemplateService;
            const svc = new FaceTemplateService();
            const a = JSON.parse(hid1.value);
            const b = JSON.parse(hid2.value);
            const sim = svc.similarityPercent(a, b);
            if (sim < 78 || sim > 99.95) {
                e.preventDefault();
                setStatus('{{ __('The two samples did not match enough for a live check. Wait a second between captures and try again.') }}');
            }
        });
    </script>
@endpush
