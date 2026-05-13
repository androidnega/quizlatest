import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

function csrfToken() {
    if (typeof document === 'undefined') return '';
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function createProctoringEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
    const forceTLS = scheme === 'https';

    const rawHost = import.meta.env.VITE_REVERB_HOST;
    const wsHost =
        typeof rawHost === 'string' && rawHost.trim() !== ''
            ? rawHost.trim()
            : window.location.hostname;

    const rawPort = import.meta.env.VITE_REVERB_PORT;
    const wsPort = Number(
        rawPort !== undefined && rawPort !== null && String(rawPort).trim() !== ''
            ? rawPort
            : 8080,
    );
    const wssPort = Number(
        rawPort !== undefined && rawPort !== null && String(rawPort).trim() !== ''
            ? rawPort
            : forceTLS
              ? 443
              : wsPort,
    );

    return new Echo({
        broadcaster: 'reverb',
        key,
        wsHost,
        wsPort,
        wssPort,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${window.location.origin}/broadcasting/auth`,
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
            },
        },
    });
}

/**
 * Subscribes to exam-session WebSocket events and forwards payloads into ExamStateEngine only.
 *
 * @param {import('./examStateEngine.js').ExamStateEngine} examStateEngine
 */
export function subscribeExamSessionChannels(echo, sessionId, examStateEngine) {
    if (!echo || !sessionId) {
        return () => {};
    }
    if (!examStateEngine?.handleRealtimeEvent) {
        return () => {};
    }

    const channel = echo.private(`exam-session.${sessionId}`);

    channel.listen('.proctoring.warning', (payload) =>
        examStateEngine.handleRealtimeEvent('proctoring.warning', payload),
    );
    channel.listen('.proctoring.risk-update', (payload) =>
        examStateEngine.handleRealtimeEvent('proctoring.risk-update', payload),
    );
    channel.listen('.exam.autosubmit', (payload) => examStateEngine.handleRealtimeEvent('exam.autosubmit', payload));
    channel.listen('.exam.held-result', (payload) =>
        examStateEngine.handleRealtimeEvent('exam.held-result', payload),
    );
    channel.listen('.exam.governance-update', (payload) =>
        examStateEngine.handleRealtimeEvent('exam.governance-update', payload),
    );

    return () => {
        echo.leave(`exam-session.${sessionId}`);
    };
}
