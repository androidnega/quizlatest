<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full border-gray-300 focus:border-blue-600 focus:ring-blue-600" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full border-gray-300 focus:border-blue-600 focus:ring-blue-600" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full border-gray-300 focus:border-blue-600 focus:ring-blue-600"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full border-gray-300 focus:border-blue-600 focus:ring-blue-600"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-4 rounded-lg border border-gray-200 p-4">
            <x-input-label for="face_image" :value="__('Portrait Capture (for face verification)')" />
            <p class="mt-1 text-xs text-gray-500">Capture one clear portrait. Embedding is generated in-browser for fast verification.</p>
            <video id="face-video" class="mt-2 hidden w-full rounded-md bg-black" autoplay muted playsinline></video>
            <canvas id="face-canvas" class="hidden"></canvas>
            <input type="file" id="face_image" name="face_image" accept="image/jpeg,image/png" class="mt-2 block w-full text-sm text-gray-700" />
            <input type="hidden" id="face_embedding" name="face_embedding" />
            <div class="mt-2 flex gap-2">
                <button type="button" id="start-camera" class="rounded-lg bg-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-300">Start Camera</button>
                <button type="button" id="capture-face" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Capture Face</button>
            </div>
            <p id="face-status" class="mt-2 text-xs text-gray-600">Face template not captured yet.</p>
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-blue-600 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-600" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4 bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:ring-blue-600">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

<script type="module">
    import { FilesetResolver, FaceLandmarker } from 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14';

    (() => {
        const video = document.getElementById('face-video');
        const canvas = document.getElementById('face-canvas');
        const faceImageInput = document.getElementById('face_image');
        const embeddingInput = document.getElementById('face_embedding');
        const statusEl = document.getElementById('face-status');
        const startBtn = document.getElementById('start-camera');
        const captureBtn = document.getElementById('capture-face');
        let faceLandmarker = null;
        let stream = null;

        async function initLandmarker() {
            if (faceLandmarker) {
                return faceLandmarker;
            }
            const vision = await FilesetResolver.forVisionTasks(
                'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm'
            );
            faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
                baseOptions: {
                    modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task'
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
            const mag = Math.sqrt(vector.reduce((sum, v) => sum + (v * v), 0)) || 1;
            return vector.map((v) => Number((v / mag).toFixed(6)));
        }

        async function startCamera() {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            video.classList.remove('hidden');
            statusEl.textContent = 'Camera active. Capture portrait now.';
        }

        async function captureFaceTemplate() {
            if (!video.videoWidth || !video.videoHeight) {
                statusEl.textContent = 'Camera not ready yet.';
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.8));
            if (!blob) {
                statusEl.textContent = 'Could not capture image.';
                return;
            }

            const file = new File([blob], `portrait_${Date.now()}.jpg`, { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(file);
            faceImageInput.files = dt.files;

            const landmarker = await initLandmarker();
            const imageBitmap = await createImageBitmap(blob);
            const result = landmarker.detect(imageBitmap);
            const landmarks = result?.faceLandmarks?.[0];
            if (!landmarks) {
                statusEl.textContent = 'No face detected. Try again.';
                return;
            }

            embeddingInput.value = JSON.stringify(buildEmbedding(landmarks));
            statusEl.textContent = 'Face template captured successfully.';
        }

        startBtn.addEventListener('click', () => {
            startCamera().catch(() => {
                statusEl.textContent = 'Unable to access camera.';
            });
        });

        captureBtn.addEventListener('click', () => {
            captureFaceTemplate().catch(() => {
                statusEl.textContent = 'Face capture failed.';
            });
        });
    })();
</script>
