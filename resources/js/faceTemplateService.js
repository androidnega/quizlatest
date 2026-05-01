import { FilesetResolver, FaceLandmarker } from '@mediapipe/tasks-vision';

export class FaceTemplateService {
    constructor() {
        this.landmarker = null;
    }

    async init() {
        if (this.landmarker) {
            return;
        }

        const vision = await FilesetResolver.forVisionTasks('https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm');
        this.landmarker = await FaceLandmarker.createFromOptions(vision, {
            baseOptions: {
                modelAssetPath: 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
            },
            runningMode: 'IMAGE',
            numFaces: 1,
        });
    }

    async extractEmbeddingFromVideo(videoElement) {
        await this.init();

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

    buildEmbedding(landmarks) {
        const points = [1, 33, 61, 199, 263, 291];
        const vector = points.flatMap((index) => {
            const point = landmarks[index] || { x: 0, y: 0, z: 0 };
            return [point.x, point.y, point.z];
        });
        const magnitude = Math.sqrt(vector.reduce((sum, value) => sum + (value * value), 0)) || 1;
        return vector.map((value) => Number((value / magnitude).toFixed(6)));
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
