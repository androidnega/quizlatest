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
        // Audit P2.5: lengthen the idle flush window to 9s and keep 4.5s
        // when an event is pending. Prevents the batcher from POSTing
        // empty arrays every 4.5s on a calm exam (no tab switches, no
        // detection events).
        this.activeFlushIntervalMs = options.flushIntervalMs ?? 4500;
        this.idleFlushIntervalMs = options.idleFlushIntervalMs ?? 9000;
        this.flushIntervalMs = this.activeFlushIntervalMs;
        this.maxBatch = options.maxBatch ?? 14;
        this.preferGzip = options.preferGzip !== false;
        this.onFlushResult = typeof options.onFlushResult === 'function' ? options.onFlushResult : null;
        // Audit Phase 8: pause flushes while the tab is hidden — listeners
        // continue to enqueue events, we just don't ship them until the
        // student returns. Stops a backgrounded laptop from sending
        // proctoring batches every 9s for hours.
        this.pausedWhenHidden = options.pausedWhenHidden !== false;

        this.queue = [];
        this.timer = null;
        this.pendingResolvers = [];

        if (this.pausedWhenHidden && typeof document !== 'undefined') {
            this._visibilityHandler = () => {
                if (document.visibilityState === 'visible' && this.queue.length) {
                    void this.flush();
                }
            };
            document.addEventListener('visibilitychange', this._visibilityHandler);
        }
    }

    enqueue(eventPayload) {
        return new Promise((resolve, reject) => {
            this.queue.push(eventPayload);
            this.pendingResolvers.push({ resolve, reject });
            // Active interval whenever there is real work pending.
            this.flushIntervalMs = this.activeFlushIntervalMs;
            if (this.queue.length >= this.maxBatch) {
                void this.flush();
                return;
            }
            this.scheduleFlush();
        });
    }

    scheduleFlush() {
        if (this.timer) return;
        // Audit P2.5: skip empty timer wakeups while the tab is hidden.
        if (
            this.pausedWhenHidden
            && typeof document !== 'undefined'
            && document.visibilityState === 'hidden'
            && this.queue.length === 0
        ) {
            return;
        }
        const interval = this.queue.length ? this.activeFlushIntervalMs : this.idleFlushIntervalMs;
        this.timer = window.setTimeout(() => {
            this.timer = null;
            void this.flush();
        }, interval);
    }

    async flush() {
        if (this.timer) {
            window.clearTimeout(this.timer);
            this.timer = null;
        }

        // Audit P2.5: empty flushes were a major source of wasted requests.
        // Returning here keeps the batcher silent during quiet exam moments.
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

            if (this.onFlushResult) {
                try {
                    this.onFlushResult(data);
                } catch {
                    //
                }
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
