/**
 * Block pinch-zoom, ctrl+wheel zoom, and double-tap zoom (iOS/Android/desktop trackpad).
 */
function lockViewportMeta() {
    const meta = document.querySelector('meta[name="viewport"]');
    if (!meta) {
        return;
    }

    const content =
        'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover, user-scalable=no';

    const apply = () => meta.setAttribute('content', content);

    apply();
    window.addEventListener('orientationchange', apply);
    window.addEventListener('pageshow', apply);
}

function preventViewportZoom() {
    lockViewportMeta();

    document.addEventListener(
        'gesturestart',
        (event) => {
            event.preventDefault();
        },
        { passive: false },
    );

    document.addEventListener(
        'gesturechange',
        (event) => {
            event.preventDefault();
        },
        { passive: false },
    );

    document.addEventListener(
        'gestureend',
        (event) => {
            event.preventDefault();
        },
        { passive: false },
    );

    document.addEventListener(
        'touchstart',
        (event) => {
            if (event.touches.length > 1) {
                event.preventDefault();
            }
        },
        { passive: false },
    );

    document.addEventListener(
        'touchmove',
        (event) => {
            if (event.touches.length > 1) {
                event.preventDefault();
            }
        },
        { passive: false },
    );

    let lastTouchEnd = 0;
    document.addEventListener(
        'touchend',
        (event) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        },
        { passive: false },
    );

    document.addEventListener(
        'wheel',
        (event) => {
            if (event.ctrlKey) {
                event.preventDefault();
            }
        },
        { passive: false },
    );
}

preventViewportZoom();
