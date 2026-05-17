@php
    $heroImage = asset('images/home/quizsnap-hero.jpg');
@endphp

<div
    {{ $attributes->merge([
        'class' => 'home-hero-visual group relative w-full min-w-0 max-w-md sm:max-w-lg md:max-w-xl lg:max-w-2xl',
        'data-online-quiz-hero' => '1',
    ]) }}
>
    <style>
        .home-hero-visual {
            --hh-primary: var(--qs-primary);
            --hh-soft: var(--qs-soft);
        }

        @keyframes hh-glow {
            0%, 100% { opacity: 0.45; transform: scale(0.96); }
            50% { opacity: 0.72; transform: scale(1.02); }
        }

        @keyframes hh-ring {
            0%, 100% { transform: rotate(-6deg) scale(1); opacity: 0.55; }
            50% { transform: rotate(4deg) scale(1.04); opacity: 0.85; }
        }

        .home-hero-visual .hh-glow {
            animation: hh-glow 5.5s ease-in-out infinite;
        }

        .home-hero-visual .hh-ring {
            animation: hh-ring 8s ease-in-out infinite;
        }

        @media (prefers-reduced-motion: reduce) {
            .home-hero-visual .hh-glow,
            .home-hero-visual .hh-ring {
                animation: none !important;
            }
        }
    </style>

    <p class="sr-only">
        {{ __('QuizSnap promotional illustration: a student on a laptop in a teal chair beside a phone showing secure digital quizzes and exams for schools.') }}
    </p>

    <div class="relative mx-auto aspect-[4/3] w-full max-h-[min(58vh,480px)] sm:max-h-[min(62vh,520px)]">
        <div
            class="hh-glow pointer-events-none absolute inset-[8%] -z-10 rounded-[40%] bg-[var(--hh-primary)]/20 blur-3xl"
            aria-hidden="true"
        ></div>

        <div
            class="hh-ring pointer-events-none absolute -right-3 top-[6%] -z-10 h-28 w-28 rounded-full border-2 border-[var(--hh-primary)]/25 sm:-right-5 sm:h-36 sm:w-36"
            aria-hidden="true"
        ></div>
        <div
            class="hh-ring pointer-events-none absolute -bottom-2 -left-4 -z-10 h-24 w-24 rounded-full border border-[var(--hh-soft)] bg-[var(--hh-primary)]/8 sm:-left-6 sm:h-32 sm:w-32"
            style="animation-delay: -2.5s;"
            aria-hidden="true"
        ></div>

        <img
            src="{{ $heroImage }}"
            alt=""
            width="1024"
            height="768"
            decoding="async"
            fetchpriority="high"
            class="relative z-10 h-full w-full object-contain object-center drop-shadow-[0_24px_48px_rgba(26,43,48,0.12)]"
        />
    </div>
</div>
