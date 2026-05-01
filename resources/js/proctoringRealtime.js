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

    return new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
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

export function subscribeExamSessionChannels(echo, sessionId, handlers = {}) {
    if (!echo || !sessionId) {
        return () => {};
    }

    const channel = echo.private(`exam-session.${sessionId}`);

    if (handlers.onWarning) {
        channel.listen('.proctoring.warning', handlers.onWarning);
    }
    if (handlers.onRiskUpdate) {
        channel.listen('.proctoring.risk-update', handlers.onRiskUpdate);
    }
    if (handlers.onAutoSubmit) {
        channel.listen('.exam.autosubmit', handlers.onAutoSubmit);
    }
    if (handlers.onHeldResult) {
        channel.listen('.exam.held-result', handlers.onHeldResult);
    }

    return () => {
        echo.leave(`exam-session.${sessionId}`);
    };
}
