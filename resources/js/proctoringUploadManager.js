export class ProctoringUploadManager {
    constructor(options) {
        this.videoElement = options.videoElement;
        this.sessionId = options.sessionId;
        this.quizId = options.quizId ?? null;
        this.eventType = options.eventType ?? 'snapshot';
        this.concurrentLimit = options.concurrentLimit ?? 2;
        this.uploadQueue = [];
        this.activeUploads = 0;
        this.isRunning = false;
        this.captureTimer = null;
        this.dbPromise = this.initDb();

        window.addEventListener('online', () => {
            this.retryPendingFromIndexedDb();
        });
    }

    start() {
        if (this.isRunning) {
            return;
        }

        this.isRunning = true;
        this.scheduleNextCapture();
        this.retryPendingFromIndexedDb();
    }

    stop() {
        this.isRunning = false;
        if (this.captureTimer) {
            clearTimeout(this.captureTimer);
        }
    }

    scheduleNextCapture() {
        if (!this.isRunning) {
            return;
        }

        const intervalMs = this.getCaptureIntervalMs();
        this.captureTimer = setTimeout(async () => {
            await this.captureAndQueue();
            this.scheduleNextCapture();
        }, intervalMs);
    }

    getCaptureIntervalMs() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (!connection) {
            return 20000;
        }

        const type = connection.effectiveType || '';
        const downlink = connection.downlink || 0;

        if (type === '4g' && downlink >= 2) {
            return 10000;
        }

        if (type === '3g' || downlink >= 0.8) {
            return 20000;
        }

        return 30000;
    }

    async captureAndQueue() {
        if (!this.videoElement || this.videoElement.readyState < 2) {
            return;
        }

        const blob = await this.captureCompressedJpeg();
        if (!blob) {
            return;
        }

        const item = {
            blob,
            timestamp: new Date().toISOString(),
            eventType: this.eventType,
            sessionId: this.sessionId,
            quizId: this.quizId,
        };

        this.uploadQueue.push(item);
        this.processQueue();
    }

    async captureCompressedJpeg() {
        const sourceWidth = this.videoElement.videoWidth || 640;
        const sourceHeight = this.videoElement.videoHeight || 480;
        const targetWidth = Math.min(320, sourceWidth);
        const scale = targetWidth / sourceWidth;
        const targetHeight = Math.max(1, Math.floor(sourceHeight * scale));

        const canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        const context = canvas.getContext('2d');
        if (!context) {
            return null;
        }

        context.drawImage(this.videoElement, 0, 0, targetWidth, targetHeight);

        return new Promise((resolve) => {
            canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.6);
        });
    }

    processQueue() {
        while (this.activeUploads < this.concurrentLimit && this.uploadQueue.length > 0) {
            const nextItem = this.uploadQueue.shift();
            if (!nextItem) {
                return;
            }

            this.activeUploads++;
            this.uploadItem(nextItem)
                .catch(async () => {
                    await this.savePendingToIndexedDb(nextItem);
                })
                .finally(() => {
                    this.activeUploads--;
                    this.processQueue();
                });
        }
    }

    async uploadItem(item) {
        const pathResponse = await window.axios.post('/proctoring/uploads/path', {
            session_id: item.sessionId,
            event_type: item.eventType,
            quiz_id: item.quizId,
        });

        const uploadToken = pathResponse.data.upload_token;
        const uploadUrl = pathResponse.data.upload_url;
        const metadataUrl = pathResponse.data.metadata_url;

        const formData = new FormData();
        formData.append('upload_token', uploadToken);
        formData.append('snapshot', item.blob, `snapshot_${Date.now()}.jpg`);

        await window.axios.post(uploadUrl, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        await window.axios.post(metadataUrl, {
            upload_token: uploadToken,
            timestamp: item.timestamp,
            metadata: {
                source: 'capture_queue',
            },
        });
    }

    async retryPendingFromIndexedDb() {
        const db = await this.dbPromise;
        const items = await this.readAllPending(db);
        for (const item of items) {
            try {
                await this.uploadItem(item);
                await this.deletePending(db, item.id);
            } catch (error) {
                return;
            }
        }
    }

    initDb() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('quizsnap-proctoring', 1);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains('pending_uploads')) {
                    db.createObjectStore('pending_uploads', { keyPath: 'id', autoIncrement: true });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async savePendingToIndexedDb(item) {
        const db = await this.dbPromise;
        return new Promise((resolve, reject) => {
            const tx = db.transaction('pending_uploads', 'readwrite');
            tx.objectStore('pending_uploads').add(item);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }

    readAllPending(db) {
        return new Promise((resolve, reject) => {
            const tx = db.transaction('pending_uploads', 'readonly');
            const request = tx.objectStore('pending_uploads').getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    deletePending(db, id) {
        return new Promise((resolve, reject) => {
            const tx = db.transaction('pending_uploads', 'readwrite');
            tx.objectStore('pending_uploads').delete(id);
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    }
}
