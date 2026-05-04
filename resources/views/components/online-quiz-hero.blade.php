<div
    {{ $attributes->merge([
        'class' => 'online-quiz-hero w-full min-w-0 max-w-lg overflow-x-hidden rounded-3xl border border-qs-soft bg-white p-4 shadow-lg shadow-qs-text/5 sm:p-6 md:max-w-xl',
        'data-online-quiz-hero' => '1',
    ]) }}
>
    <style>
        .online-quiz-hero { --oq-primary: #166534; --oq-soft: #dce8e0; --oq-cream: #faf7f2; --oq-text: #0f2918; --oq-muted: #5c6b62; --oq-rose: #9f1239; }
        .online-quiz-hero svg { display: block; width: 100%; height: auto; }
        .online-quiz-hero .oq-anim { animation-play-state: running; }

        @keyframes oq-glow-pulse {
            0%, 100% { opacity: 0.12; }
            50% { opacity: 0.28; }
        }
        @keyframes oq-cursor-blink {
            0%, 45% { opacity: 1; }
            50%, 100% { opacity: 0; }
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

        .online-quiz-hero .oq-glow { animation: oq-glow-pulse 2.8s ease-in-out infinite; }
        .online-quiz-hero .oq-cursor { animation: oq-cursor-blink 1.05s step-end infinite; }
        .online-quiz-hero .oq-note-path-1 { animation: oq-note-line 4s ease-out infinite; stroke-dasharray: 28; stroke-dashoffset: 28; }
        .online-quiz-hero .oq-note-path-2 { animation: oq-note-line 4s ease-out 0.5s infinite; stroke-dasharray: 36; stroke-dashoffset: 36; }
        .online-quiz-hero .oq-note-path-3 { animation: oq-note-line 4s ease-out 1s infinite; stroke-dasharray: 24; stroke-dashoffset: 24; }
        .online-quiz-hero .oq-steam-1 { transform-origin: center bottom; animation: oq-steam 2.2s ease-out infinite; }
        .online-quiz-hero .oq-steam-2 { transform-origin: center bottom; animation: oq-steam 2.2s ease-out 0.7s infinite; }
        .online-quiz-hero .oq-steam-3 { transform-origin: center bottom; animation: oq-steam 2.2s ease-out 1.3s infinite; }
        .online-quiz-hero .oq-head { transform-origin: 132px 178px; animation: oq-head-bob 3.2s ease-in-out infinite; }
        .online-quiz-hero .oq-hand-l { transform-origin: 158px 228px; animation: oq-hand-type 0.45s ease-in-out infinite; }
        .online-quiz-hero .oq-hand-r { transform-origin: 198px 226px; animation: oq-hand-type 0.45s ease-in-out 0.12s infinite reverse; }
        .online-quiz-hero .oq-key-a { animation: oq-key-1 0.55s ease-in-out infinite; }
        .online-quiz-hero .oq-key-b { animation: oq-key-2 0.55s ease-in-out 0.18s infinite; }
        .online-quiz-hero .oq-key-c { animation: oq-key-3 0.55s ease-in-out 0.36s infinite; }

        @media (prefers-reduced-motion: reduce) {
            .online-quiz-hero .oq-anim { animation: none !important; }
            .online-quiz-hero .oq-cursor { opacity: 1; }
            .online-quiz-hero .oq-note-path-1,
            .online-quiz-hero .oq-note-path-2,
            .online-quiz-hero .oq-note-path-3 { stroke-dashoffset: 0 !important; }
        }
    </style>

    <p class="sr-only">{{ __('Illustration of a student taking an online quiz at a computer with notes and keyboard.') }}</p>

    <div class="overflow-hidden rounded-2xl border border-qs-soft/70 bg-qs-card/40">
        <svg
            class="mx-auto max-h-[min(52vh,420px)] w-full max-w-full select-none"
            viewBox="0 0 440 312"
            preserveAspectRatio="xMidYMid meet"
            role="img"
            aria-hidden="true"
            focusable="false"
        >
            {{-- Wall (titles removed; wall shifted up) --}}
            <rect x="24" y="28" width="392" height="130" rx="6" fill="#ffffff" stroke="#dce8e0" stroke-width="1.5"/>

            {{-- Wall note (no title text on card) --}}
            <g transform="translate(36, 42)">
                <rect width="78" height="92" rx="4" fill="#ffffff" stroke="#166534" stroke-width="1.5"/>
                <path class="oq-anim oq-note-path-1" d="M12 24h54" fill="none" stroke="#5c6b62" stroke-width="1.5" stroke-linecap="round"/>
                <path class="oq-anim oq-note-path-2" d="M12 38h62" fill="none" stroke="#5c6b62" stroke-width="1.5" stroke-linecap="round"/>
                <path class="oq-anim oq-note-path-3" d="M12 52h40" fill="none" stroke="#5c6b62" stroke-width="1.5" stroke-linecap="round"/>
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

            {{-- Student hands --}}
            <ellipse class="oq-anim oq-hand-l" cx="158" cy="228" rx="10" ry="8" fill="#faf7f2" stroke="#166534" stroke-width="1"/>
            <ellipse class="oq-anim oq-hand-r" cx="198" cy="226" rx="10" ry="8" fill="#faf7f2" stroke="#166534" stroke-width="1"/>
        </svg>
    </div>
</div>
