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

export class ProctoringRuntimeEngine {
    constructor(options) {
        this.videoElement = options.videoElement;
        this.sessionId = options.sessionId;
        this.examId = options.examId;
        this.studentId = options.studentId;
        this.apiClient = options.apiClient || window.axios;
        this.performanceProfile = options.performanceProfile ?? null;

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

        this.faceTimer = setInterval(() => this.runFaceChecks(), this.faceIntervalMs);

        if (this.phoneModel && this.phoneIntervalMs) {
            this.phoneTimer = setInterval(() => this.runPhoneDetection(), this.phoneIntervalMs);
        }

        this.bindBrowserEvents();
    }

    async stop() {
        clearInterval(this.faceTimer);
        clearInterval(this.phoneTimer);
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
            void this.emitSensorEvent('tab_switch', { source: 'resize_change' }, true);
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
            await this.emitSensorEvent('face_missing', { faces }, true);
            return;
        }

        if (faces > 1) {
            await this.emitSensorEvent('multiple_faces', { faces }, true);
        }

        let headDirection = 'center';
        if (this.poseLandmarker) {
            const poseResult = this.poseLandmarker.detectForVideo(this.videoElement, nowMs);
            headDirection = this.estimateHeadDirection(faceResult?.faceLandmarks?.[0], poseResult?.landmarks?.[0]);
        }

        await this.emitSensorEvent('face_presence', { faces, head_direction: headDirection }, false);
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
