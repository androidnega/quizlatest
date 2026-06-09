/**
 * Conservative camera constraints to avoid driver/GPU overload on student devices.
 * Used for proctoring and exam-entry verification flows.
 */
export const qsCameraVideoOnly = {
    facingMode: 'user',
    width: { ideal: 640, max: 1280 },
    height: { ideal: 480, max: 720 },
    frameRate: { ideal: 15, max: 24 },
};

export const qsCameraWithMic = {
    video: qsCameraVideoOnly,
    audio: true,
};

export const qsCameraVideoOnlyRequest = {
    video: qsCameraVideoOnly,
    audio: false,
};

export const qsMicOnlyRequest = {
    video: false,
    audio: true,
};

/**
 * @param {boolean} withAudio
 * @returns {MediaStreamConstraints}
 */
export function qsProctoringMediaRequest(withAudio = true) {
    return withAudio ? qsCameraWithMic : qsCameraVideoOnlyRequest;
}
