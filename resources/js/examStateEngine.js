import { subscribeExamSessionChannels } from './proctoringRealtime';

const VALID_STATES = [
    'idle',
    'starting',
    'active',
    'warning',
    'locked',
    'held',
    'auto_submitting',
    'submitted',
];

export class ExamStateEngine {
    constructor() {
        this.state = 'idle';
        this.listeners = new Set();
        this.sessionRouteKey = null;
        this.apiClient = null;
        this.echoUnsubscribe = null;
        this.pollTimer = null;
        this.pollIntervalMs = 45000;
        this.echoRef = null;
        this.echoDisconnectBound = false;
        this.onEchoDisconnected = null;
        this.lastPayload = null;
    }

    getState() {
        return this.state;
    }

    getLastPayload() {
        return this.lastPayload;
    }

    subscribe(listener) {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }

    transition(nextState, payload = {}, source = 'internal') {
        if (!VALID_STATES.includes(nextState)) {
            return;
        }
        const previousState = this.state;
        this.state = nextState;
        this.lastPayload = payload;
        for (const fn of this.listeners) {
            try {
                fn({
                    state: this.state,
                    previousState,
                    payload,
                    source,
                });
            } catch {
                //
            }
        }
    }

    markIdle() {
        this.transition('idle', {}, 'client');
    }

    markStarting() {
        this.transition('starting', {}, 'client');
    }

    /**
     * Authoritative reconciliation from GET /exam-sessions/{session}/state
     */
    syncFromBackend(payload) {
        if (!payload?.exam_ui_state || !VALID_STATES.includes(payload.exam_ui_state)) {
            return;
        }
        this.transition(payload.exam_ui_state, payload, 'poll');
    }

    /**
     * Entry point for Laravel Echo events (no UI here).
     */
    handleRealtimeEvent(eventName, payload) {
        switch (eventName) {
            case 'proctoring.warning':
                this.transition('warning', payload, 'echo');
                break;
            case 'proctoring.risk-update': {
                const risk = payload?.risk_state ?? '';
                if (risk === 'locked') {
                    this.transition('locked', payload, 'echo');
                } else if (['warning', 'suspicious', 'critical'].includes(risk)) {
                    this.transition('warning', payload, 'echo');
                } else {
                    this.transition('active', payload, 'echo');
                }
                break;
            }
            case 'exam.autosubmit':
                this.transition('auto_submitting', payload, 'echo');
                break;
            case 'exam.held-result':
                this.transition('held', payload, 'echo');
                break;
            case 'exam.governance-update': {
                const snap = payload?.snapshot ?? payload;
                if (snap?.emergency_shutdown || snap?.modules_enabled === false) {
                    this.transition('locked', snap, 'echo');
                } else {
                    void this.pullStateFromBackend();
                }
                break;
            }
            default:
                break;
        }
    }

    configureApi(client) {
        this.apiClient = client || window.axios;
    }

    attachRealtime(echo, sessionId, apiClient) {
        this.configureApi(apiClient);
        this.sessionRouteKey = sessionId;
        this.detachRealtime(false);
        this.echoRef = echo;
        this.echoUnsubscribe = subscribeExamSessionChannels(echo, sessionId, this);
        this.bindEchoDisconnect(echo);
    }

    bindEchoDisconnect(echo) {
        const conn = echo?.connector?.pusher?.connection;
        if (!conn || this.echoDisconnectBound) {
            return;
        }
        this.echoDisconnectBound = true;
        this.onEchoDisconnected = () => {
            void this.pullStateFromBackend();
            this.startPolling(this.pollIntervalMs);
        };
        conn.bind('disconnected', this.onEchoDisconnected);
    }

    unbindEchoDisconnect() {
        const conn = this.echoRef?.connector?.pusher?.connection;
        if (conn && this.onEchoDisconnected) {
            conn.unbind('disconnected', this.onEchoDisconnected);
        }
        this.onEchoDisconnected = null;
        this.echoDisconnectBound = false;
    }

    detachRealtime(stopPollingToo = false) {
        if (typeof this.echoUnsubscribe === 'function') {
            this.echoUnsubscribe();
        }
        this.echoUnsubscribe = null;
        this.unbindEchoDisconnect();
        this.echoRef = null;
        if (stopPollingToo) {
            this.stopPolling();
        }
    }

    startPolling(intervalMs = this.pollIntervalMs, immediate = false) {
        this.pollIntervalMs = intervalMs;
        if (!this.sessionRouteKey || !this.apiClient) {
            return;
        }
        this.stopPolling();
        if (immediate) {
            void this.pullStateFromBackend();
        }
        this.pollTimer = window.setInterval(() => void this.pullStateFromBackend(), this.pollIntervalMs);
    }

    stopPolling() {
        if (this.pollTimer) {
            window.clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    async pullStateFromBackend() {
        if (!this.sessionRouteKey || !this.apiClient) {
            return null;
        }
        try {
            const response = await this.apiClient.get(
                `/exam-sessions/${encodeURIComponent(this.sessionRouteKey)}/state`,
            );
            this.syncFromBackend(response.data);
            return response.data;
        } catch {
            return null;
        }
    }

    dispose() {
        this.detachRealtime(true);
        this.listeners.clear();
        this.sessionRouteKey = null;
        this.lastPayload = null;
        this.markIdle();
    }
}
