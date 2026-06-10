{{--
    Marketing-page-only head: Tailwind Play CDN + brand colors.

    This partial is intentionally only included by /about and / (homepage).
    Every other page in QuizSnap continues to use the @vite() build with the
    qs-* design tokens. Do NOT include this anywhere else in the system —
    Play CDN ships a "not for production" warning and a runtime JIT.

    Brand colors are wired through Tailwind's theme so we can use stock
    utilities (text-qs-primary, bg-qs-bg, border-qs-soft, …) without
    writing any custom CSS.
--}}

{{--
    Suppress only Tailwind's "Play CDN should not be used in production"
    console warning. Every other console.warn() call — from any script,
    third-party or our own — must continue to flow through normally.

    This MUST run before <script src="https://cdn.tailwindcss.com…">
    so the override is in place by the time Tailwind boots and emits.
--}}
<script>
(function () {
    var originalWarn = console.warn.bind(console);
    var SUPPRESS = 'cdn.tailwindcss.com should not be used in production';
    console.warn = function () {
        for (var i = 0; i < arguments.length; i++) {
            if (typeof arguments[i] === 'string' && arguments[i].indexOf(SUPPRESS) !== -1) {
                return;
            }
        }
        originalWarn.apply(console, arguments);
    };
})();
</script>

<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'qs-primary': '#56AEBB',
                    'qs-primary-deep': '#3f8e9a',
                    'qs-text': '#0f343a',
                    'qs-muted': '#5a7378',
                    'qs-soft': '#e6eef0',
                    'qs-bg': '#f5fafa',
                    'qs-card': '#ffffff',
                    'qs-danger': '#e46f2e',
                },
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
                    brand: ['Antonio', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                },
                boxShadow: {
                    'qs-soft': '0 1px 0 rgba(15,52,58,0.02), 0 18px 40px -28px rgba(15,52,58,0.18)',
                    'qs-card': '0 1px 0 rgba(15,52,58,0.02), 0 12px 28px -24px rgba(15,52,58,0.18)',
                    'qs-card-hover': '0 30px 60px -32px rgba(15,52,58,0.32)',
                },
            },
        },
    };
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Antonio:wght@500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
