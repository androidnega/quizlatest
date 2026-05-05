export class FaceTemplateService {
    constructor() {
        this.landmarker = null;
        this.runningMode = 'IMAGE';
    }

    async init({ runningMode = 'IMAGE' } = {}) {
        if (this.landmarker) {
            if (this.runningMode !== runningMode) {
                this.landmarker.setOptions({ runningMode });
                this.runningMode = runningMode;
            }
            return;
        }

        const { FilesetResolver, FaceLandmarker } = await import('@mediapipe/tasks-vision');
        const vision = await FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm');
        this.landmarker = await FaceLandmarker.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
            },
            runningMode,
            numFaces: 2,
        });
        this.runningMode = runningMode;
    }

    async extractEmbeddingFromVideo(videoElement) {
        await this.init({ runningMode: 'IMAGE' });

        const canvas = document.createElement('canvas');
        canvas.width = videoElement.videoWidth || 640;
        canvas.height = videoElement.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
        const bitmap = await createImageBitmap(canvas);
        const result = this.landmarker.detect(bitmap);
        const landmarks = result?.faceLandmarks?.[0];
        if (!landmarks) {
            return null;
        }

        return this.buildEmbedding(landmarks);
    }

    async detectFromVideo(videoElement) {
        await this.init({ runningMode: 'VIDEO' });
        const ts = performance.now();
        const result = this.landmarker.detectForVideo(videoElement, ts);
        const faces = Array.isArray(result?.faceLandmarks) ? result.faceLandmarks : [];
        const primary = faces[0] ?? null;

        return {
            faceCount: faces.length,
            landmarks: primary,
            embedding: primary ? this.buildEmbedding(primary) : null,
            metrics: primary ? this.computeMetrics(primary) : null,
        };
    }

    buildEmbedding(landmarks) {
        const points = [1, 33, 61, 199, 263, 291];
        const vector = points.flatMap((index) => {
            const point = landmarks[index] || { x: 0, y: 0, z: 0 };
            return [point.x, point.y, point.z];
        });
        const magnitude = Math.sqrt(vector.reduce((sum, value) => sum + (value * value), 0)) || 1;
        return vector.map((value) => Number((value / magnitude).toFixed(6)));
    }

    computeMetrics(landmarks) {
        const nose = landmarks[1] || { x: 0.5, y: 0.5 };
        const leftEye = landmarks[33] || { x: 0.4, y: 0.5 };
        const rightEye = landmarks[263] || { x: 0.6, y: 0.5 };
        const eyeDistance = Math.abs(rightEye.x - leftEye.x);
        const centered = Math.abs(nose.x - 0.5) < 0.22 && Math.abs(nose.y - 0.5) < 0.26;
        const tooFar = eyeDistance < 0.08;
        const tooClose = eyeDistance > 0.4;

        return {
            noseX: nose.x,
            centered,
            tooFar,
            tooClose,
        };
    }

    similarityPercent(template, probe) {
        const length = Math.min(template.length, probe.length);
        if (length === 0) {
            return 0;
        }

        let dot = 0;
        let normA = 0;
        let normB = 0;
        for (let i = 0; i < length; i++) {
            dot += template[i] * probe[i];
            normA += template[i] * template[i];
            normB += probe[i] * probe[i];
        }

        if (normA <= 0 || normB <= 0) {
            return 0;
        }

        const cosine = dot / (Math.sqrt(normA) * Math.sqrt(normB));
        return Number((((Math.max(-1, Math.min(1, cosine)) + 1) / 2) * 100).toFixed(2));
    }
}
