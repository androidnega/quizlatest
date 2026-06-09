<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover, user-scalable=no">
<script>
(function () {
    var content = 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover, user-scalable=no';
    var applyViewport = function () {
        var meta = document.querySelector('meta[name="viewport"]');
        if (meta) {
            meta.setAttribute('content', content);
        }
    };
    applyViewport();
    window.addEventListener('orientationchange', applyViewport);
    window.addEventListener('pageshow', applyViewport);

    var block = function (e) {
        e.preventDefault();
    };

    document.addEventListener('gesturestart', block, { passive: false });
    document.addEventListener('gesturechange', block, { passive: false });
    document.addEventListener('gestureend', block, { passive: false });

    document.addEventListener(
        'touchstart',
        function (e) {
            if (e.touches && e.touches.length > 1) {
                e.preventDefault();
            }
        },
        { passive: false },
    );

    document.addEventListener(
        'touchmove',
        function (e) {
            if (e.touches && e.touches.length > 1) {
                e.preventDefault();
            }
        },
        { passive: false },
    );

    var lastTouchEnd = 0;
    document.addEventListener(
        'touchend',
        function (e) {
            var now = Date.now();
            if (300 >= now - lastTouchEnd) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        },
        { passive: false },
    );

    document.addEventListener(
        'wheel',
        function (e) {
            if (e.ctrlKey) {
                e.preventDefault();
            }
        },
        { passive: false },
    );
})();
</script>
