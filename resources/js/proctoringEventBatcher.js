function csrfToken() {
    if (typeof document === 'undefined') return '';
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function encodeBatchBody(eventsPayload, preferGzip) {
    const json = JSON.stringify(eventsPayload);
    if (
        !preferGzip ||
        typeof CompressionStream === 'undefined' ||
        typeof Response === 'undefined'
    ) {
        return { body: json, encoding: null };
    }

    try {
        const stream = new Blob([json]).stream().pipeThrough(new CompressionStream('gzip'));
        const buf = await new Response(stream).arrayBuffer();
        return { body: buf, encoding: 'gzip' };
    } catch {
        return { body: json, encoding: null };
    }
}

export class ProctoringEventBatcher {
    constructor(options) {
        this.examSessionKey = options.examSessionKey;
        this.apiClient = options.apiClient || window.axios;
        this.flushIntervalMs = options.flushIntervalMs ?? 4500;
        this.maxBatch = options.maxBatch ?? 14;
        this.preferGzip = options.preferGzip !== false;

        this.queue = [];
        this.timer = null;
        this.pendingResolvers = [];
    }

    enqueue(eventPayload) {
        return new Promise((resolve, reject) => {
            this.queue.push(eventPayload);
            this.pendingResolvers.push({ resolve, reject });
            if (this.queue.length >= this.maxBatch) {
                void this.flush();
                return;
            }
            this.scheduleFlush();
        });
    }

    scheduleFlush() {
        if (this.timer) return;
        this.timer = window.setTimeout(() => {
            this.timer = null;
            void this.flush();
        }, this.flushIntervalMs);
    }

    async flush() {
        if (this.timer) {
            window.clearTimeout(this.timer);
            this.timer = null;
        }

        if (!this.queue.length) {
            return null;
        }

        const batchEvents = this.queue.splice(0, this.queue.length);
        const resolvers = this.pendingResolvers.splice(0, this.pendingResolvers.length);

        const payload = { events: batchEvents };
        const url = `/exam-sessions/${encodeURIComponent(this.examSessionKey)}/proctoring-events/batch`;

        try {
            const { body, encoding } = await encodeBatchBody(payload, this.preferGzip);

            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            };

            if (encoding === 'gzip') {
                headers['Content-Type'] = 'application/octet-stream';
                headers['X-Qs-Encoding'] = 'gzip';
            } else {
                headers['Content-Type'] = 'application/json';
            }

            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || `Batch rejected (${response.status})`);
            }

            resolvers.forEach(({ resolve }) => resolve(data));

            return data;
        } catch (err) {
            resolvers.forEach(({ reject }) => reject(err));
            throw err;
        }
    }

    async flushPending() {
        return this.flush();
    }
}
