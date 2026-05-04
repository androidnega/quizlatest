@props([
    'headingId' => 'oq-hero-heading',
])

@php
    $headingId = (string) $headingId;
@endphp

<div
    {{ $attributes->merge([
        'class' => 'online-quiz-hero group w-full min-w-0 max-w-lg overflow-x-hidden rounded-3xl border border-qs-soft bg-white p-4 shadow-lg shadow-qs-text/5 outline-none ring-offset-2 ring-offset-white transition focus-visible:ring-2 focus-visible:ring-qs-primary sm:p-6 md:max-w-xl',
        'data-online-quiz-hero' => '1',
        'tabindex' => '0',
        'role' => 'group',
        'aria-labelledby' => $headingId,
        'aria-pressed' => 'false',
    ]) }}
>
    <style>
        .online-quiz-hero { --oq-primary: #166534; --oq-soft: #dce8e0; --oq-cream: #faf7f2; --oq-text: #0f2918; --oq-muted: #5c6b62; --oq-rose: #9f1239; }
        .online-quiz-hero svg { display: block; width: 100%; height: auto; }
        .online-quiz-hero .oq-anim { animation-play-state: running; }
        .online-quiz-hero--paused .oq-anim { animation-play-state: paused !important; }

        @keyframes oq-glow-pulse {
            0%, 100% { opacity: 0.12; }
            50% { opacity: 0.28; }
        }
        @keyframes oq-cursor-blink {
            0%, 45% { opacity: 1; }
            50%, 100% { opacity: 0; }
        }
        @keyframes oq-clock-tick {
            to { transform: rotate(360deg); }
        }
        @keyframes oq-note-line {
            0% { stroke-dashoffset: 28; }
            100% { stroke-dashoffset: 0; }
        }
        @keyframes oq-steam {
            0% { opacity: 0.35; transform: translateY(4px); }
            100% { opacity: 0; transform: translateY(-14px); }
        }
        @keyframes oq-head-bob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }
        @keyframes oq-hand-type {
            0%, 100% { transform: rotate(-4deg); }
            50% { transform: rotate(6deg); }
        }
        @keyframes oq-robot-bob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-1.5px); }
        }
        @keyframes oq-robot-arm-l {
            0%, 100% { transform: rotate(-6deg); }
            50% { transform: rotate(4deg); }
        }
        @keyframes oq-robot-arm-r {
            0%, 100% { transform: rotate(6deg); }
            50% { transform: rotate(-4deg); }
        }
        @keyframes oq-key-1 {
            0%, 100% { opacity: 0.55; }
            50% { opacity: 1; }
        }
        @keyframes oq-key-2 {
            0%, 100% { opacity: 0.65; }
            50% { opacity: 1; }
        }
        @keyframes oq-key-3 {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
        @keyframes oq-dots {
            0%, 100% { opacity: 0.25; }
            33% { opacity: 1; }
            66% { opacity: 0.45; }
        }

        .online-quiz-hero .oq-glow { animation: oq-glow-pulse 2.8s ease-in-out infinite; }
        .online-quiz-hero .oq-cursor { animation: oq-cursor-blink 1.05s step-end infinite; }
        .online-quiz-hero .oq-clock-hand { transform-origin: 0 0; animation: oq-clock-tick 72s linear infinite; }
        .online-quiz-hero .oq-note-path-1 { animation: oq-note-line 4s ease-out infinite; stroke-dasharray: 28; stroke-dashoffset: 28; }
        .online-quiz-hero .oq-note-path-2 { animation: oq-note-line 4s ease-out 0.5s infinite; stroke-dasharray: 36; stroke-dashoffset: 36; }
        .online-quiz-hero .oq-note-path-3 { animation: oq-note-line 4s ease-out 1s infinite; stroke-dasharray: 24; stroke-dashoffset: 24; }
        .online-quiz-hero .oq-steam-1 { transform-origin: center bottom; animation: oq-steam 2.2s ease-out infinite; }
        .online-quiz-hero .oq-steam-2 { transform-origin: center bottom; animation: oq-steam 2.2s ease-out 0.7s infinite; }
        .online-quiz-hero .oq-steam-3 { transform-origin: center bottom; animation: oq-steam 2.2s ease-out 1.3s infinite; }
        .online-quiz-hero .oq-head { transform-origin: 132px 178px; animation: oq-head-bob 3.2s ease-in-out infinite; }
        .online-quiz-hero .oq-hand-l { transform-origin: 158px 228px; animation: oq-hand-type 0.45s ease-in-out infinite; }
        .online-quiz-hero .oq-hand-r { transform-origin: 198px 226px; animation: oq-hand-type 0.45s ease-in-out 0.12s infinite reverse; }
        .online-quiz-hero .oq-robot-body { transform-origin: 326px 270px; animation: oq-robot-bob 2.4s ease-in-out infinite; }
        .online-quiz-hero .oq-robot-arm-l { transform-origin: 318px 264px; animation: oq-robot-arm-l 0.5s ease-in-out infinite; }
        .online-quiz-hero .oq-robot-arm-r { transform-origin: 334px 264px; animation: oq-robot-arm-r 0.5s ease-in-out infinite; }
        .online-quiz-hero .oq-key-a { animation: oq-key-1 0.55s ease-in-out infinite; }
        .online-quiz-hero .oq-key-b { animation: oq-key-2 0.55s ease-in-out 0.18s infinite; }
        .online-quiz-hero .oq-key-c { animation: oq-key-3 0.55s ease-in-out 0.36s infinite; }
        .online-quiz-hero .oq-dots { animation: oq-dots 1.2s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
            .online-quiz-hero .oq-anim { animation: none !important; }
            .online-quiz-hero .oq-cursor { opacity: 1; }
            .online-quiz-hero .oq-note-path-1,
            .online-quiz-hero .oq-note-path-2,
            .online-quiz-hero .oq-note-path-3 { stroke-dashoffset: 0 !important; }
        }
    </style>

    <p id="{{ $headingId }}" class="sr-only">{{ __('Interactive illustration of a student taking an online quiz. Click or press Space while this area is focused to pause or resume motion.') }}</p>

    <div class="overflow-hidden rounded-2xl border border-qs-soft/70 bg-qs-card/40">
    <svg
        class="mx-auto max-h-[min(52vh,420px)] w-full max-w-full select-none"
        viewBox="0 0 440 312"
        preserveAspectRatio="xMidYMid meet"
        role="img"
        aria-label="{{ __('Animated illustration of a student taking an online quiz on a computer with notes, clock, keyboard, and a small robot assistant.') }}"
        focusable="false"
    >
        {{-- Titles --}}
        <text x="220" y="26" text-anchor="middle" fill="#0f2918" font-size="14" font-weight="600" font-family="ui-sans-serif, system-ui, sans-serif">{{ __('Student Taking an Online Quiz') }}</text>
        <text x="220" y="46" text-anchor="middle" fill="#5c6b62" font-size="11" font-family="ui-sans-serif, system-ui, sans-serif">{{ __('Verified students. Smart quizzes. Trusted results.') }}</text>

        {{-- Wall --}}
        <rect x="24" y="58" width="392" height="118" rx="6" fill="#ffffff" stroke="#dce8e0" stroke-width="1.5"/>

        {{-- Wall quiz note --}}
        <g transform="translate(36, 72)">
            <rect width="78" height="92" rx="4" fill="#ffffff" stroke="#166534" stroke-width="1.5"/>
            <text x="39" y="18" text-anchor="middle" fill="#9f1239" font-size="9" font-weight="600" font-family="ui-sans-serif, system-ui, sans-serif">{{ __('Quiz') }}</text>
            <path class="oq-anim oq-note-path-1" d="M12 32h54" fill="none" stroke="#5c6b62" stroke-width="1.5" stroke-linecap="round"/>
            <path class="oq-anim oq-note-path-2" d="M12 46h62" fill="none" stroke="#5c6b62" stroke-width="1.5" stroke-linecap="round"/>
            <path class="oq-anim oq-note-path-3" d="M12 60h40" fill="none" stroke="#5c6b62" stroke-width="1.5" stroke-linecap="round"/>
        </g>

        {{-- Clock (hand rotates about center) --}}
        <g transform="translate(322, 118)">
            <circle r="26" cx="0" cy="0" fill="#ffffff" stroke="#166534" stroke-width="2"/>
            <line x1="0" y1="0" x2="0" y2="-16" stroke="#166534" stroke-width="2" stroke-linecap="round"/>
            <g class="oq-anim oq-clock-hand">
                <line x1="0" y1="0" x2="12" y2="-6" stroke="#0f2918" stroke-width="1.5" stroke-linecap="round"/>
            </g>
            <circle cx="0" cy="0" r="2.5" fill="#166534"/>
        </g>

        {{-- Desk surface --}}
        <rect x="20" y="248" width="400" height="56" rx="8" fill="#faf7f2" stroke="#dce8e0" stroke-width="1.5"/>

        {{-- Chair back --}}
        <path d="M88 248 L88 175 Q88 158 105 158 L125 158 L125 248 Z" fill="#dce8e0" stroke="#166534" stroke-width="1"/>

        {{-- Student body --}}
        <ellipse cx="132" cy="232" rx="34" ry="28" fill="#166534" opacity="0.92"/>
        <g class="oq-anim oq-head">
            <ellipse cx="132" cy="178" rx="20" ry="24" fill="#faf7f2" stroke="#166534" stroke-width="1.5"/>
            <ellipse cx="126" cy="176" rx="2" ry="2.5" fill="#0f2918"/>
            <ellipse cx="138" cy="176" rx="2" ry="2.5" fill="#0f2918"/>
        </g>

        {{-- Monitor stand + glow + screen --}}
        <rect x="208" y="228" width="24" height="22" fill="#5c6b62"/>
        <rect x="196" y="248" width="48" height="6" rx="2" fill="#0f2918" opacity="0.35"/>

        <rect class="oq-anim oq-glow" x="178" y="128" width="144" height="100" rx="10" fill="#166534"/>

        <rect x="182" y="132" width="136" height="92" rx="8" fill="#ffffff" stroke="#166534" stroke-width="2.5"/>
        <rect x="190" y="140" width="120" height="76" rx="4" fill="#faf7f2" stroke="#dce8e0"/>

        {{-- Quiz UI in monitor --}}
        <rect x="198" y="148" width="40" height="5" rx="2" fill="#166534" opacity="0.85"/>
        <rect x="198" y="158" width="88" height="4" rx="2" fill="#dce8e0"/>
        <rect x="198" y="166" width="72" height="4" rx="2" fill="#dce8e0"/>
        <rect x="198" y="174" width="80" height="4" rx="2" fill="#dce8e0"/>
        <rect x="198" y="186" width="8" height="8" rx="2" fill="#166534" opacity="0.35"/>
        <rect x="210" y="188" width="56" height="3" rx="1" fill="#5c6b62"/>
        <rect x="198" y="196" width="8" height="8" rx="2" fill="#166534" opacity="0.35"/>
        <rect x="210" y="198" width="48" height="3" rx="1" fill="#5c6b62"/>
        <line class="oq-anim oq-cursor" x1="268" y1="186" x2="268" y2="198" stroke="#166534" stroke-width="2" stroke-linecap="round"/>

        {{-- Paper notes on desk --}}
        <g transform="translate(38, 218)">
            <rect width="52" height="38" rx="3" fill="#ffffff" stroke="#dce8e0" stroke-width="1"/>
            <line x1="10" y1="12" x2="42" y2="12" stroke="#5c6b62" stroke-width="1"/>
            <line x1="10" y1="20" x2="36" y2="20" stroke="#5c6b62" stroke-width="1"/>
            <line x1="10" y1="28" x2="40" y2="28" stroke="#5c6b62" stroke-width="1"/>
        </g>

        {{-- Cup + steam --}}
        <g transform="translate(332, 218)">
            <path class="oq-anim oq-steam-1" d="M14 4 Q12 -4 10 -10" fill="none" stroke="#dce8e0" stroke-width="2" stroke-linecap="round"/>
            <path class="oq-anim oq-steam-2" d="M22 4 Q24 -6 26 -12" fill="none" stroke="#dce8e0" stroke-width="2" stroke-linecap="round"/>
            <path class="oq-anim oq-steam-3" d="M30 4 Q28 -5 26 -10" fill="none" stroke="#dce8e0" stroke-width="2" stroke-linecap="round"/>
            <rect x="8" y="8" width="28" height="22" rx="3" fill="#ffffff" stroke="#166534" stroke-width="1.5"/>
            <line x1="8" y1="14" x2="36" y2="14" stroke="#faf7f2" stroke-width="3"/>
        </g>

        {{-- Keyboard --}}
        <g transform="translate(168, 256)">
            <rect width="104" height="22" rx="4" fill="#ffffff" stroke="#166534" stroke-width="1.5"/>
            <rect class="oq-anim oq-key-a" x="6" y="6" width="12" height="10" rx="2" fill="#dce8e0" stroke="#166534" stroke-width="0.75"/>
            <rect class="oq-anim oq-key-b" x="22" y="6" width="12" height="10" rx="2" fill="#dce8e0" stroke="#166534" stroke-width="0.75"/>
            <rect class="oq-anim oq-key-c" x="38" y="6" width="12" height="10" rx="2" fill="#dce8e0" stroke="#166534" stroke-width="0.75"/>
            <rect class="oq-anim oq-key-b" x="54" y="6" width="12" height="10" rx="2" fill="#dce8e0" stroke="#166534" stroke-width="0.75"/>
            <rect class="oq-anim oq-key-a" x="70" y="6" width="12" height="10" rx="2" fill="#dce8e0" stroke="#166534" stroke-width="0.75"/>
            <rect class="oq-anim oq-key-c" x="86" y="6" width="12" height="10" rx="2" fill="#dce8e0" stroke="#166534" stroke-width="0.75"/>
        </g>

        {{-- Student hands (in front of torso, near keyboard) --}}
        <ellipse class="oq-anim oq-hand-l" cx="158" cy="228" rx="10" ry="8" fill="#faf7f2" stroke="#166534" stroke-width="1"/>
        <ellipse class="oq-anim oq-hand-r" cx="198" cy="226" rx="10" ry="8" fill="#faf7f2" stroke="#166534" stroke-width="1"/>

        {{-- Typing dots above robot --}}
        <g class="oq-anim oq-dots" transform="translate(316, 242)" aria-hidden="true">
            <circle cx="0" cy="0" r="2" fill="#166534"/>
            <circle cx="8" cy="0" r="2" fill="#166534"/>
            <circle cx="16" cy="0" r="2" fill="#166534"/>
        </g>

        {{-- Small toy robot near keyboard (right of monitor frame; does not cover screen) --}}
        <g class="oq-anim oq-robot-body" transform="translate(306, 250)">
            <rect x="0" y="10" width="22" height="18" rx="4" fill="#ffffff" stroke="#166534" stroke-width="1.25"/>
            <circle cx="11" cy="6" r="9" fill="#faf7f2" stroke="#166534" stroke-width="1.25"/>
            <circle cx="8" cy="5" r="1.5" fill="#166534"/>
            <circle cx="14" cy="5" r="1.5" fill="#166534"/>
            <line x1="11" y1="8" x2="11" y2="10" stroke="#9f1239" stroke-width="1" stroke-linecap="round"/>
            <g class="oq-anim oq-robot-arm-l">
                <path d="M0 18 L-6 26" stroke="#166534" stroke-width="2" stroke-linecap="round"/>
            </g>
            <g class="oq-anim oq-robot-arm-r">
                <path d="M22 18 L28 26" stroke="#166534" stroke-width="2" stroke-linecap="round"/>
            </g>
            <rect x="6" y="26" width="10" height="4" rx="1" fill="#dce8e0"/>
        </g>
    </svg>
    </div>

    <p
        class="online-quiz-hero__hint mt-3 px-1 text-center text-xs leading-snug text-qs-muted"
        data-oq-hint
        aria-live="polite"
        data-oq-hint-playing="{{ __('Click illustration to pause animation') }}"
        data-oq-hint-paused="{{ __('Click illustration to resume animation') }}"
    >{{ __('Click illustration to pause animation') }}</p>
</div>

<script>
    (function () {
        document.querySelectorAll('[data-online-quiz-hero]').forEach(function (root) {
            if (root.dataset.oqBound === '1') {
                return;
            }
            root.dataset.oqBound = '1';

            const hint = root.querySelector('[data-oq-hint]');

            function syncHints() {
                const isPaused = root.classList.contains('online-quiz-hero--paused');
                root.setAttribute('aria-pressed', isPaused ? 'true' : 'false');
                if (hint) {
                    const playText = hint.getAttribute('data-oq-hint-playing') || '';
                    const pauseText = hint.getAttribute('data-oq-hint-paused') || '';
                    hint.textContent = isPaused ? pauseText : playText;
                }
            }

            function toggle() {
                root.classList.toggle('online-quiz-hero--paused');
                syncHints();
            }

            root.addEventListener('click', function (e) {
                if (e.target.closest('a,button,input,select,textarea')) {
                    return;
                }
                toggle();
            });

            root.addEventListener('keydown', function (e) {
                if (e.code !== 'Space' && e.code !== 'Enter') {
                    return;
                }
                e.preventDefault();
                toggle();
            });

            syncHints();
        });
    })();
</script>
