import '@tensorflow/tfjs-backend-webgl';
import * as tf from '@tensorflow/tfjs-core';
import * as cocoSsd from '@tensorflow-models/coco-ssd';

let modelPromise = null;
let loadFailed = false;

async function ensureModel() {
    if (loadFailed) {
        return null;
    }
    if (!modelPromise) {
        modelPromise = (async () => {
            await tf.ready();
            return cocoSsd.load();
        })();
    }
    try {
        return await modelPromise;
    } catch {
        loadFailed = true;
        modelPromise = null;

        return null;
    }
}

/**
 * COCO-SSD person check. When the model cannot load, returns true so MediaPipe-only flows still work.
 *
 * @param {HTMLVideoElement} video
 * @returns {Promise<boolean>}
 */
export async function qsExamPrepareDetectPerson(video) {
    if (!video || video.readyState < 2) {
        return false;
    }
    const model = await ensureModel();
    if (!model) {
        return true;
    }
    try {
        const preds = await model.detect(video, 10, 0.45);

        return preds.some((p) => p.class === 'person' && p.score >= 0.5);
    } catch {
        return false;
    }
}

if (typeof window !== 'undefined') {
    window.qsExamPrepareDetectPerson = qsExamPrepareDetectPerson;
}
