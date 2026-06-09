import '@tensorflow/tfjs-backend-cpu';
import * as tf from '@tensorflow/tfjs-core';
import { FaceLandmarker, PoseLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';

import { ProctoringEventBatcher } from './proctoringEventBatcher';

export async function fetchProctoringCapability(apiClient = window.axios) {
    const conn = typeof navigator !== 'undefined' ? navigator.connection : undefined;

    const { data } = await apiClient.post('/exam-sessions/proctoring-capability', {
        hardware_concurrency: typeof navigator !== 'undefined' ? navigator.hardwareConcurrency : null,
        device_memory_gb: typeof navigator !== 'undefined' ? navigator.deviceMemory : null,
        network_effective_type: conn?.effectiveType ?? null,
        save_data: conn?.saveData ?? null,
    });

    return data;
}

/** @typedef {'ok'|'no_face'|'multiple'|'off_center'} FramingHint */

/**
 * Local-only mesh-style preview: draw landmark bounds and classify framing without contacting the server.
 * @param {CanvasRenderingContext2D} ctx
 * @param {Array<{x:number,y:number,z?:number}>} landmarks
 * @param {number} w
 * @param {number} h
 * @returns {FramingHint}
 */
function drawFaceMeshPreview(ctx, landmarks, w, h) {
    ctx.clearRect(0, 0, w, h);
    if (!landmarks?.length) {
        return 'no_face';
    }

    let minX = 1;
    let minY = 1;
    let maxX = 0;
    let maxY = 0;
    for (const p of landmarks) {
        minX = Math.min(minX, p.x);
        maxX = Math.max(maxX, p.x);
        minY = Math.min(minY, p.y);
        maxY = Math.max(maxY, p.y);
    }

    const bw = maxX - minX;
    const bh = maxY - minY;
    const cx = (minX + maxX) / 2;
    const cy = (minY + maxY) / 2;

    const px = (x) => x * w;
    const py = (y) => y * h;

    ctx.strokeStyle = '#38bdf8';
    ctx.lineWidth = 1.25;
    ctx.globalAlpha = 0.55;
    const step = Math.max(1, Math.floor(landmarks.length / 90));
    for (let i = 0; i < landmarks.length; i += step) {
        const p = landmarks[i];
        ctx.fillStyle = '#7dd3fc';
        ctx.beginPath();
        ctx.arc(px(p.x), py(p.y), 1.1, 0, Math.PI * 2);
        ctx.fill();
    }
    ctx.globalAlpha = 1;

    const inSize = bw >= 0.22 && bh >= 0.26;
    const centered = cx >= 0.18 && cx <= 0.82 && cy >= 0.12 && cy <= 0.88;
    let hint = 'ok';
    if (!inSize || !centered) {
        hint = 'off_center';
    }

    ctx.strokeStyle = hint === 'ok' ? '#22c55e' : '#f97316';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(px(minX) - 2, py(minY) - 2, bw * w + 4, bh * h + 4);

    return hint;
}

export class ProctoringRuntimeEngine {
    constructor(options) {
        this.videoElement = options.videoElement;
        this.sessionId = options.sessionId;
        this.examId = options.examId;
        this.studentId = options.studentId;
        this.apiClient = options.apiClient || window.axios;
        this.performanceProfile = options.performanceProfile ?? null;
        /** Optional canvas for live mesh-style overlay (browser-only rendering). */
        this.previewCanvas = options.previewCanvas ?? null;
        /** @type {((hint: FramingHint) => void) | null} */
        this.onFramingHint = options.onFramingHint ?? null;

        this.faceLandmarker = null;
        this.poseLandmarker = null;
        this.phoneModel = null;
        this.phoneModelPromise = null;
        this.phoneModelLoadFailed = false;
        /** When true, only tab/fullscreen/window listeners — no camera models or intervals. */
        this.browserOnly = false;

        // Audit Phase 8 / P2.5 / Section 6.3.3: hardware-aware defaults.
        // Low-end devices (<4 cores or <4 GB RAM) get longer intervals so
        // the face/phone inference doesn't dominate the CPU. The server
        // can still override via performance_profile.
        const hwCores = Number(this.performanceProfile?.hardware_concurrency)
            || (typeof navigator !== 'undefined' ? Number(navigator.hardwareConcurrency) : 0);
        const memGb = Number(this.performanceProfile?.device_memory_gb)
            || (typeof navigator !== 'undefined' ? Number(navigator.deviceMemory) : 0);
        const isLowEnd = (hwCores > 0 && hwCores < 4) || (memGb > 0 && memGb < 4);
        this.faceIntervalMs = this.performanceProfile?.face_interval_ms
            ?? (isLowEnd ? 18000 : 12000);
        this.phoneIntervalMs = this.performanceProfile?.phone_interval_ms
            ?? (isLowEnd ? 35000 : 25000);
        this.isLowEndHardware = isLowEnd;

        this.eventBatcher =
            options.eventBatcher ??
            new ProctoringEventBatcher({
                examSessionKey: options.sessionId,
                apiClient: this.apiClient,
            });

        this.phoneScoreThreshold = Number(options.performanceProfile?.phone_detection_confidence_threshold);
        if (!Number.isFinite(this.phoneScoreThreshold) || this.phoneScoreThreshold <= 0) {
            this.phoneScoreThreshold = 0.55;
        }

        this.externalDisplayCheckEnabled =
            options.performanceProfile?.external_display_detection_enabled !== false &&
            typeof window !== 'undefined' &&
            typeof window.getScreenDetails === 'function';

        this.lastFaceObstructEmitMs = 0;
        this.screenDetailsTimer = null;
        this.faceMissStreak = 0;
        this.lastMultipleFacesEmitMs = 0;
        this.lastPhoneEmitMs = 0;
        this.resizeEmitTimer = null;
        /** Wall-clock ms when 2+ faces were first seen in the current streak; null when 0/1 faces. */
        this.multipleFacesStartedAt = null;
        /** Verification timer that emits the final "30-second" auto-submit event. */
        this.multipleFacesAutoSubmitTimer = null;
        /** Continuous multi-face seconds that should trigger auto-submission. */
        this.multipleFacesAutoSubmitSeconds = 30;
        /** After emitting face_missing, suppress repeats until a face is seen again. */
        this.faceMissingOpen = false;

        this.localPreviewRafId = null;
        this.lastLocalPreviewMs = 0;
        // Audit Section 6.3.3: was 250ms (≈4 fps mesh inference). Drop to
        // 800ms on capable hardware and 1500ms on low-end hardware. The
        // overlay still feels responsive but face mesh inference no longer
        // dominates CPU on student laptops.
        this.localPreviewMinIntervalMs = this.isLowEndHardware ? 1500 : 800;

        // Audit Phase 8: pause heavy timers when the tab is hidden. The
        // tab_switch listener still fires from the browser-event side.
        this._visibilityHandler = null;
        this._timersPausedForVisibility = false;
    }

    async ensurePhoneModel() {
        if (this.phoneModel || this.phoneModelLoadFailed) {
            return this.phoneModel;
        }
        if (!this.phoneModelPromise) {
            this.phoneModelPromise = (async () => {
                await tf.ready();
                if (tf.getBackend() !== 'cpu') {
                    await tf.setBackend('cpu');
                }
                await tf.ready();
                const cocoSsd = await import('@tensorflow-models/coco-ssd');

                return cocoSsd.load();
            })();
        }
        try {
            this.phoneModel = await this.phoneModelPromise;

            return this.phoneModel;
        } catch {
            this.phoneModelLoadFailed = true;
            this.phoneModelPromise = null;

            return null;
        }
    }

    /**
     * @param {{ browserOnly?: boolean }} [options]
     */
    async init(options = {}) {
        if (options.browserOnly) {
            this.browserOnly = true;
            return;
        }

        const vision = await FilesetResolver.forVisionTasks(
            'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm',
        );

        this.faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath:
                    'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
                delegate: 'CPU',
            },
            runningMode: 'VIDEO',
            numFaces: 2,
        });

        const enablePose = this.performanceProfile?.enable_pose_landmarker !== false;
        if (enablePose) {
            this.poseLandmarker = await PoseLandmarker.createFromOptions(vision, {
                baseOptions: {
                    modelAssetPath:
                        'https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task',
                    delegate: 'CPU',
                },
                runningMode: 'VIDEO',
                numPoses: 1,
            });
        }

        const enableCoco = this.performanceProfile?.enable_coco_ssd !== false;
        if (enableCoco) {
            await this.ensurePhoneModel();
        }
    }

    start() {
        if (this.browserOnly) {
            this.bindBrowserEvents();
            return;
        }

        this.faceIntervalMs = this.performanceProfile?.face_interval_ms ?? this.faceIntervalMs;
        this.phoneIntervalMs = this.performanceProfile?.phone_interval_ms ?? this.phoneIntervalMs;

        this._startTimers();
        this.bindBrowserEvents();

        if (this.externalDisplayCheckEnabled) {
            this.screenDetailsTimer = setInterval(() => void this.checkExternalScreens(), 90000);
            void this.checkExternalScreens();
        }

        if (this.previewCanvas && this.faceLandmarker && this.videoElement) {
            this.startLocalPreviewLoop();
        }

        // Audit Phase 8 / Section 6.3.3: stop face / phone / preview RAF
        // work while the tab is hidden. The tab_switch detector keeps
        // firing because it lives on the document visibility listener
        // chain — but mediapipe and tfjs go silent so a backgrounded tab
        // stops eating CPU.
        if (typeof document !== 'undefined') {
            this._visibilityHandler = () => {
                if (document.visibilityState === 'hidden') {
                    this._pauseTimersForVisibility();
                } else {
                    this._resumeTimersForVisibility();
                }
            };
            document.addEventListener('visibilitychange', this._visibilityHandler);
        }
    }

    _startTimers() {
        if (!this.faceTimer) {
            this.faceTimer = setInterval(() => void this.runFaceChecks(), this.faceIntervalMs);
        }
        if (this.phoneModel && this.phoneIntervalMs && !this.phoneTimer) {
            this.phoneTimer = setInterval(() => void this.runPhoneDetection(), this.phoneIntervalMs);
        }
    }

    _pauseTimersForVisibility() {
        if (this._timersPausedForVisibility) return;
        this._timersPausedForVisibility = true;
        if (this.faceTimer) {
            clearInterval(this.faceTimer);
            this.faceTimer = null;
        }
        if (this.phoneTimer) {
            clearInterval(this.phoneTimer);
            this.phoneTimer = null;
        }
        if (this.localPreviewRafId) {
            window.cancelAnimationFrame(this.localPreviewRafId);
            this.localPreviewRafId = null;
        }
    }

    _resumeTimersForVisibility() {
        if (!this._timersPausedForVisibility) return;
        this._timersPausedForVisibility = false;
        this._startTimers();
        if (this.previewCanvas && this.faceLandmarker && this.videoElement && !this.localPreviewRafId) {
            this.startLocalPreviewLoop();
        }
    }

    startLocalPreviewLoop() {
        const loop = (ts) => {
            this.localPreviewRafId = window.requestAnimationFrame(loop);
            if (ts - this.lastLocalPreviewMs < this.localPreviewMinIntervalMs) {
                return;
            }
            this.lastLocalPreviewMs = ts;
            void this.runLocalPreviewFrame();
        };
        this.localPreviewRafId = window.requestAnimationFrame(loop);
    }

    async runLocalPreviewFrame() {
        const video = this.videoElement;
        const canvas = this.previewCanvas;
        if (!video || !canvas || video.readyState < 2 || !this.faceLandmarker) {
            return;
        }

        const w = canvas.clientWidth || video.videoWidth || 320;
        const h = canvas.clientHeight || video.videoHeight || 240;
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w;
            canvas.height = h;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        ctx.drawImage(video, 0, 0, w, h);

        const nowMs = performance.now();
        const faceResult = this.faceLandmarker.detectForVideo(video, nowMs);
        const lm = faceResult?.faceLandmarks?.[0];
        const faces = faceResult?.faceLandmarks?.length || 0;

        let hint = 'no_face';
        if (faces > 1) {
            hint = 'multiple';
            ctx.fillStyle = 'rgba(239, 68, 68, 0.25)';
            ctx.fillRect(0, 0, w, h);
        } else if (lm) {
            hint = drawFaceMeshPreview(ctx, lm, w, h);
        }

        if (typeof this.onFramingHint === 'function') {
            try {
                this.onFramingHint(hint);
            } catch {
                //
            }
        }
    }

    async stop() {
        clearInterval(this.faceTimer);
        this.faceTimer = null;
        clearInterval(this.phoneTimer);
        this.phoneTimer = null;
        if (this.screenDetailsTimer) {
            clearInterval(this.screenDetailsTimer);
            this.screenDetailsTimer = null;
        }
        if (this.localPreviewRafId) {
            window.cancelAnimationFrame(this.localPreviewRafId);
            this.localPreviewRafId = null;
        }
        if (this.resizeEmitTimer) {
            window.clearTimeout(this.resizeEmitTimer);
            this.resizeEmitTimer = null;
        }
        if (this.multipleFacesAutoSubmitTimer) {
            clearTimeout(this.multipleFacesAutoSubmitTimer);
            this.multipleFacesAutoSubmitTimer = null;
        }
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        if (this._visibilityHandler) {
            document.removeEventListener('visibilitychange', this._visibilityHandler);
            this._visibilityHandler = null;
        }
        document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        document.removeEventListener('webkitfullscreenchange', this.onFullscreenChange);
        window.removeEventListener('blur', this.onBlur);
        window.removeEventListener('resize', this.onResize);

        try {
            await this.eventBatcher.flushPending();
        } catch {
            //
        }
    }

    bindBrowserEvents() {
        // Tab switches are recorded once per leave/return cycle in studentExamRuntime.js
        // (server-authoritative count + auto-submit on strike 3).
        this.onVisibilityChange = () => {};
        this.onFullscreenChange = () => {
            const inFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
            if (!inFs) {
                void this.emitSensorEvent('fullscreen_exit', {}, true);
            }
        };
        this.onBlur = () => {};
        this.onResize = () => {
            if (this.resizeEmitTimer) {
                window.clearTimeout(this.resizeEmitTimer);
            }
            this.resizeEmitTimer = null;
        };

        document.addEventListener('visibilitychange', this.onVisibilityChange);
        document.addEventListener('fullscreenchange', this.onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', this.onFullscreenChange);
        window.addEventListener('blur', this.onBlur);
        window.addEventListener('resize', this.onResize);
    }

    async runFaceChecks() {
        if (!this.videoElement || this.videoElement.readyState < 2 || !this.faceLandmarker) {
            return;
        }

        const nowMs = performance.now();
        const faceResult = this.faceLandmarker.detectForVideo(this.videoElement, nowMs);
        const faces = faceResult?.faceLandmarks?.length || 0;

        if (faces === 0) {
            this.faceMissStreak += 1;
            if (this.faceMissStreak >= 2 && !this.faceMissingOpen) {
                this.faceMissingOpen = true;
                await this.emitSensorEvent('face_missing', { faces, source: 'interval_debounced' }, true);
            }
            return;
        }

        this.faceMissStreak = 0;
        this.faceMissingOpen = false;

        if (faces > 1) {
            await this.handleMultipleFacesDetected(faces);
            return;
        }

        this.resetMultipleFacesStreak();

        if (faces === 1 && this.poseLandmarker) {
            const poseRes = this.poseLandmarker.detectForVideo(this.videoElement, nowMs);
            const plm = poseRes?.landmarks?.[0];
            const faceLm = faceResult?.faceLandmarks?.[0];
            if (plm && faceLm && faceLm.length > 5) {
                const nose = faceLm[1] ?? faceLm[4];
                const lw = plm[15];
                const rw = plm[16];
                if (nose && lw && rw) {
                    const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
                    const minw = Math.min(dist(nose, lw), dist(nose, rw));
                    if (minw < 0.12) {
                        const t = Date.now();
                        if (t - this.lastFaceObstructEmitMs > 14000) {
                            this.lastFaceObstructEmitMs = t;
                            await this.emitSensorEvent(
                                'face_obstructed',
                                { source: 'pose_wrist_near_face', wrist_face_distance: minw },
                                true,
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Track and emit progressive `multiple_faces` events. When 2+ faces have
     * been continuously visible for {@link multipleFacesAutoSubmitSeconds}
     * seconds, we surface a final event carrying duration_ms so the server
     * will auto-submit. Until then we emit lightweight progress ticks for UI.
     *
     * @param {number} faces
     */
    async handleMultipleFacesDetected(faces) {
        const nowMs = Date.now();
        if (this.multipleFacesStartedAt == null) {
            this.multipleFacesStartedAt = nowMs;
            this.lastMultipleFacesEmitMs = nowMs;
            await this.emitSensorEvent(
                'multiple_faces',
                {
                    faces,
                    duration_ms: 0,
                    phase: 'started',
                    threshold_seconds: this.multipleFacesAutoSubmitSeconds,
                },
                true,
            );

            // Schedule a verification pass slightly *after* the threshold so
            // we don't rely on the polling cadence to catch the cutoff.
            this.scheduleMultipleFacesAutoSubmitCheck();
            return;
        }

        const durationMs = nowMs - this.multipleFacesStartedAt;
        const thresholdMs = this.multipleFacesAutoSubmitSeconds * 1000;

        if (durationMs >= thresholdMs) {
            this.lastMultipleFacesEmitMs = nowMs;
            await this.emitSensorEvent(
                'multiple_faces',
                {
                    faces,
                    duration_ms: durationMs,
                    phase: 'auto_submit_threshold_reached',
                    threshold_seconds: this.multipleFacesAutoSubmitSeconds,
                },
                true,
            );
            return;
        }

        // Throttle in-between progress events so we don't flood the batcher.
        if (nowMs - this.lastMultipleFacesEmitMs >= 8000) {
            this.lastMultipleFacesEmitMs = nowMs;
            await this.emitSensorEvent(
                'multiple_faces',
                {
                    faces,
                    duration_ms: durationMs,
                    phase: 'continuing',
                    threshold_seconds: this.multipleFacesAutoSubmitSeconds,
                },
                true,
            );
        }
    }

    resetMultipleFacesStreak() {
        this.multipleFacesStartedAt = null;
        if (this.multipleFacesAutoSubmitTimer) {
            clearTimeout(this.multipleFacesAutoSubmitTimer);
            this.multipleFacesAutoSubmitTimer = null;
        }
    }

    scheduleMultipleFacesAutoSubmitCheck() {
        if (this.multipleFacesAutoSubmitTimer) {
            clearTimeout(this.multipleFacesAutoSubmitTimer);
            this.multipleFacesAutoSubmitTimer = null;
        }
        // Fire 1 s after the threshold so the polling result is fresh.
        const ms = (this.multipleFacesAutoSubmitSeconds + 1) * 1000;
        this.multipleFacesAutoSubmitTimer = setTimeout(() => {
            this.multipleFacesAutoSubmitTimer = null;
            void this.runFaceChecks();
        }, ms);
    }

    async runPhoneDetection() {
        if (!this.videoElement || this.videoElement.readyState < 2 || !this.phoneModel) {
            return;
        }

        const predictions = await this.phoneModel.detect(this.videoElement);
        const phone = predictions.find(
            (item) => item.class === 'cell phone' && item.score >= this.phoneScoreThreshold,
        );
        if (!phone) {
            return;
        }

        const t = Date.now();
        if (t - this.lastPhoneEmitMs < 35000) {
            return;
        }
        this.lastPhoneEmitMs = t;

        await this.emitSensorEvent(
            'phone_detected',
            {
                confidence: phone.score,
                bbox: phone.bbox,
                capture_snapshot: true,
            },
            true,
        );
    }

    async checkExternalScreens() {
        if (!this.externalDisplayCheckEnabled || typeof window.getScreenDetails !== 'function') {
            return;
        }
        try {
            const details = await window.getScreenDetails();
            const n = details?.screens?.length ?? 0;
            if (n > 1) {
                await this.emitSensorEvent(
                    'external_display_risk',
                    { screen_count: n, source: 'window_management_api' },
                    true,
                );
            }
        } catch {
            //
        }
    }

    estimateHeadDirection(faceLandmarks, poseLandmarks) {
        if (!faceLandmarks || faceLandmarks.length < 264) {
            return 'center';
        }

        const left = faceLandmarks[33]?.x ?? 0.5;
        const right = faceLandmarks[263]?.x ?? 0.5;
        const center = (left + right) / 2;
        if (center < 0.45) return 'left';
        if (center > 0.55) return 'right';
        return 'center';
    }

    async emitSensorEvent(eventType, metadata, flagged = false) {
        const payload = {
            event_type: eventType,
            flagged,
            metadata: {
                ...metadata,
                session_id: this.sessionId,
                student_id: this.studentId,
                exam_id: this.examId,
            },
        };

        return this.eventBatcher.enqueue(payload);
    }
}
