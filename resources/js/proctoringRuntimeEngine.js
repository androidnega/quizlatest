import * as cocoSsd from '@tensorflow-models/coco-ssd';
import '@tensorflow/tfjs-backend-webgl';
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
        /** When true, only tab/fullscreen/window listeners — no camera models or intervals. */
        this.browserOnly = false;

        this.faceIntervalMs = this.performanceProfile?.face_interval_ms ?? 12000;
        this.phoneIntervalMs = this.performanceProfile?.phone_interval_ms ?? 25000;

        this.eventBatcher =
            options.eventBatcher ??
            new ProctoringEventBatcher({
                examSessionKey: options.sessionId,
                apiClient: this.apiClient,
            });

        /** Server debounce: require consecutive misses before logging face_missing. */
        this.faceMissStreak = 0;
        this.lastMultipleFacesEmitMs = 0;
        this.lastPhoneEmitMs = 0;
        this.resizeEmitTimer = null;
        /** After emitting face_missing, suppress repeats until a face is seen again. */
        this.faceMissingOpen = false;

        this.localPreviewRafId = null;
        this.lastLocalPreviewMs = 0;
        this.localPreviewMinIntervalMs = 110;
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
                },
                runningMode: 'VIDEO',
                numPoses: 1,
            });
        }

        const enableCoco = this.performanceProfile?.enable_coco_ssd !== false;
        if (enableCoco) {
            this.phoneModel = await cocoSsd.load();
        }
    }

    start() {
        if (this.browserOnly) {
            this.bindBrowserEvents();
            return;
        }

        this.faceIntervalMs = this.performanceProfile?.face_interval_ms ?? this.faceIntervalMs;
        this.phoneIntervalMs = this.performanceProfile?.phone_interval_ms ?? this.phoneIntervalMs;

        this.faceTimer = setInterval(() => void this.runFaceChecks(), this.faceIntervalMs);

        if (this.phoneModel && this.phoneIntervalMs) {
            this.phoneTimer = setInterval(() => void this.runPhoneDetection(), this.phoneIntervalMs);
        }

        this.bindBrowserEvents();

        if (this.previewCanvas && this.faceLandmarker && this.videoElement) {
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
        clearInterval(this.phoneTimer);
        if (this.localPreviewRafId) {
            window.cancelAnimationFrame(this.localPreviewRafId);
            this.localPreviewRafId = null;
        }
        if (this.resizeEmitTimer) {
            window.clearTimeout(this.resizeEmitTimer);
            this.resizeEmitTimer = null;
        }
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        window.removeEventListener('blur', this.onBlur);
        window.removeEventListener('resize', this.onResize);

        try {
            await this.eventBatcher.flushPending();
        } catch {
            //
        }
    }

    bindBrowserEvents() {
        this.onVisibilityChange = () => {
            if (document.hidden) {
                void this.emitSensorEvent('tab_switch', {}, true);
            }
        };
        this.onFullscreenChange = () => {
            if (!document.fullscreenElement) {
                void this.emitSensorEvent('fullscreen_exit', {}, true);
            }
        };
        this.onBlur = () => {
            void this.emitSensorEvent('tab_switch', { source: 'window_blur' }, true);
        };
        this.onResize = () => {
            if (this.resizeEmitTimer) {
                window.clearTimeout(this.resizeEmitTimer);
            }
            this.resizeEmitTimer = window.setTimeout(() => {
                this.resizeEmitTimer = null;
                void this.emitSensorEvent('tab_switch', { source: 'resize_change' }, true);
            }, 2500);
        };

        document.addEventListener('visibilitychange', this.onVisibilityChange);
        document.addEventListener('fullscreenchange', this.onFullscreenChange);
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
            const t = Date.now();
            if (t - this.lastMultipleFacesEmitMs > 20000) {
                this.lastMultipleFacesEmitMs = t;
                await this.emitSensorEvent('multiple_faces', { faces }, true);
            }
            return;
        }
    }

    async runPhoneDetection() {
        if (!this.videoElement || this.videoElement.readyState < 2 || !this.phoneModel) {
            return;
        }

        const predictions = await this.phoneModel.detect(this.videoElement);
        const phone = predictions.find((item) => item.class === 'cell phone' && item.score >= 0.55);
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
