import * as cocoSsd from '@tensorflow-models/coco-ssd';
import '@tensorflow/tfjs-backend-webgl';
import { FaceLandmarker, PoseLandmarker, FilesetResolver } from '@mediapipe/tasks-vision';

export class ProctoringRuntimeEngine {
    constructor(options) {
        this.videoElement = options.videoElement;
        this.sessionId = options.sessionId;
        this.examId = options.examId;
        this.studentId = options.studentId;
        this.apiClient = options.apiClient || window.axios;
        this.faceIntervalMs = 12000;
        this.phoneIntervalMs = 25000;
        this.cooldownMs = 45000;
        this.lastEscalationAt = {};

        this.state = {
            violation_score: 0,
            violation_events: [],
            last_event_time: null,
            risk_state: 'normal',
        };
    }

    async init() {
        const vision = await FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm');

        this.faceLandmarker = await FaceLandmarker.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
            },
            runningMode: 'VIDEO',
            numFaces: 2,
        });

        this.poseLandmarker = await PoseLandmarker.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_lite/float16/1/pose_landmarker_lite.task',
            },
            runningMode: 'VIDEO',
            numPoses: 1,
        });

        this.phoneModel = await cocoSsd.load();
    }

    start() {
        this.faceTimer = setInterval(() => this.runFaceChecks(), this.faceIntervalMs);
        this.phoneTimer = setInterval(() => this.runPhoneDetection(), this.phoneIntervalMs);
        this.bindBrowserEvents();
    }

    stop() {
        clearInterval(this.faceTimer);
        clearInterval(this.phoneTimer);
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        document.removeEventListener('fullscreenchange', this.onFullscreenChange);
        window.removeEventListener('blur', this.onBlur);
        window.removeEventListener('resize', this.onResize);
    }

    bindBrowserEvents() {
        this.onVisibilityChange = () => {
            if (document.hidden) {
                this.handleViolationEvent('tab_switch', {});
            }
        };
        this.onFullscreenChange = () => {
            if (!document.fullscreenElement) {
                this.handleViolationEvent('fullscreen_exit', {});
            }
        };
        this.onBlur = () => {
            this.handleViolationEvent('tab_switch', { source: 'window_blur' });
        };
        this.onResize = () => {
            this.handleViolationEvent('tab_switch', { source: 'resize_change' });
        };

        document.addEventListener('visibilitychange', this.onVisibilityChange);
        document.addEventListener('fullscreenchange', this.onFullscreenChange);
        window.addEventListener('blur', this.onBlur);
        window.addEventListener('resize', this.onResize);
    }

    async runFaceChecks() {
        if (!this.videoElement || this.videoElement.readyState < 2) {
            return;
        }

        const nowMs = performance.now();
        const faceResult = this.faceLandmarker.detectForVideo(this.videoElement, nowMs);
        const faces = faceResult?.faceLandmarks?.length || 0;

        if (faces === 0) {
            await this.handleViolationEvent('face_missing', { faces });
            return;
        }

        if (faces > 1) {
            await this.handleViolationEvent('multiple_faces', { faces });
        }

        const poseResult = this.poseLandmarker.detectForVideo(this.videoElement, nowMs);
        const headDirection = this.estimateHeadDirection(faceResult?.faceLandmarks?.[0], poseResult?.landmarks?.[0]);
        await this.logEvent('face_presence', { faces, head_direction: headDirection });
    }

    async runPhoneDetection() {
        if (!this.videoElement || this.videoElement.readyState < 2) {
            return;
        }

        const predictions = await this.phoneModel.detect(this.videoElement);
        const phone = predictions.find((item) => item.class === 'cell phone' && item.score >= 0.55);
        if (!phone) {
            return;
        }

        await this.handleViolationEvent('phone_detected', {
            confidence: phone.score,
            bbox: phone.bbox,
            capture_snapshot: true,
        });
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

    async handleViolationEvent(eventType, metadata) {
        const now = Date.now();
        if (this.lastEscalationAt[eventType] && now - this.lastEscalationAt[eventType] < this.cooldownMs) {
            await this.logEvent(eventType, { ...metadata, cooldown_applied: true });
            return;
        }

        this.lastEscalationAt[eventType] = now;
        await this.logEvent(eventType, metadata, true);
    }

    async logEvent(eventType, metadata, flagged = false) {
        await this.apiClient.post(`/exam-sessions/${this.sessionId}/proctoring-events`, {
            event_type: eventType,
            flagged,
            metadata: {
                ...metadata,
                session_id: this.sessionId,
                student_id: this.studentId,
                exam_id: this.examId,
            },
        });
    }
}
