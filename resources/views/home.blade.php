<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'QUIZSNAP') }} — {{ __('Secure digital exams') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --home-ink: #0c1f14;
            --home-mist: #e8f0ea;
            --home-leaf: #166534;
            --home-leaf-soft: #dcfce7;
            --home-paper: #fafdfb;
        }
        .home-hero-svg { overflow: visible; }
        @keyframes home-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        @keyframes home-scan {
            0% { transform: translateX(-8%); opacity: 0.35; }
            50% { opacity: 0.85; }
            100% { transform: translateX(8%); opacity: 0.35; }
        }
        .home-anim-card { animation: home-float 5s ease-in-out infinite; }
        .home-anim-scan { animation: home-scan 4.5s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) {
            .home-anim-card, .home-anim-scan { animation: none !important; }
        }
    </style>
</head>
<body class="font-sans antialiased bg-white text-qs-text">
    <div class="min-h-screen bg-white text-[color:var(--home-ink)]">
        <header class="border-b border-qs-soft bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <span class="text-lg font-semibold tracking-tight text-qs-primary">{{ config('app.name', 'QUIZSNAP') }}</span>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <a href="{{ route('login') }}" class="qs-btn-secondary min-h-[44px] px-4 py-2.5 text-sm font-semibold">{{ __('Student login') }}</a>
                    <a href="{{ route('staff.login') }}" class="qs-btn-primary min-h-[44px] px-4 py-2.5 text-sm font-semibold">{{ __('Staff portal') }}</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="qs-btn-secondary min-h-[44px] px-4 py-2.5 text-sm font-semibold">{{ __('Dashboard') }}</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:px-8 lg:grid-cols-2 lg:items-center lg:gap-14 lg:py-20">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-qs-muted">{{ __('Built for schools') }}</p>
                    <h1 class="mt-3 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">
                        {{ __('Secure digital exams for schools') }}
                    </h1>
                    <p class="mt-5 max-w-xl text-base leading-relaxed text-qs-muted">
                        {{ __('Plan assessments, verify learners, run proctored sessions, and review outcomes — with practice quizzes alongside formal exams.') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] justify-center px-5 py-2.5 text-sm font-semibold">{{ __('Student login') }}</a>
                        <a href="{{ route('staff.login') }}" class="qs-btn-secondary min-h-[44px] justify-center px-5 py-2.5 text-sm font-semibold">{{ __('Staff portal') }}</a>
                    </div>
                </div>

                <div class="home-anim-card flex justify-center lg:justify-end">
                    <div class="w-full max-w-md rounded-2xl border border-[color:var(--home-mist)] bg-[color:var(--home-paper)] p-6 shadow-sm">
                        <svg class="home-hero-svg h-auto w-full" viewBox="0 0 320 220" role="img" aria-labelledby="home-hero-title">
                            <title id="home-hero-title">{{ __('Illustration of an answer sheet and checklist') }}</title>
                            <rect x="24" y="20" width="200" height="160" rx="12" fill="#fff" stroke="var(--home-leaf)" stroke-width="2"/>
                            <rect x="44" y="48" width="120" height="8" rx="4" fill="var(--home-leaf-soft)"/>
                            <rect x="44" y="68" width="90" height="8" rx="4" fill="#e5e7eb"/>
                            <rect x="44" y="88" width="100" height="8" rx="4" fill="#e5e7eb"/>
                            <circle cx="52" cy="118" r="5" fill="var(--home-leaf)"/>
                            <rect x="64" y="112" width="80" height="8" rx="4" fill="#e5e7eb"/>
                            <circle cx="52" cy="138" r="5" stroke="var(--home-leaf)" stroke-width="2" fill="#fff"/>
                            <rect x="64" y="132" width="70" height="8" rx="4" fill="#e5e7eb"/>
                            <path d="M200 40 L280 40 L280 200 L120 200 L120 120 Z" fill="var(--home-leaf-soft)" opacity="0.55"/>
                            <rect x="210" y="56" width="72" height="100" rx="8" fill="#fff" stroke="var(--home-leaf)" stroke-width="1.5"/>
                            <rect x="222" y="72" width="48" height="6" rx="3" fill="var(--home-leaf)" opacity="0.35"/>
                            <rect x="222" y="86" width="36" height="6" rx="3" fill="#d1d5db"/>
                            <rect x="222" y="100" width="40" height="6" rx="3" fill="#d1d5db"/>
                            <rect x="222" y="114" width="32" height="6" rx="3" fill="#d1d5db"/>
                            <rect class="home-anim-scan" x="208" y="128" width="76" height="4" rx="2" fill="var(--home-leaf)" opacity="0.5"/>
                        </svg>
                    </div>
                </div>
            </section>

            <section class="border-t border-qs-primary/25 bg-qs-text py-14 sm:py-16">
                <div class="mx-auto grid max-w-6xl gap-6 px-5 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
                    <div class="rounded-2xl border border-qs-soft/80 bg-white p-5 shadow-md shadow-black/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-primary">{{ __('Verified students') }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-qs-muted">{{ __('Index-first sign-in and onboarding keep exam access tied to real learners.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-qs-soft/80 bg-white p-5 shadow-md shadow-black/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-primary">{{ __('Smart exam builder') }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-qs-muted">{{ __('Structure sections, pool questions, and publish with clear delivery rules.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-qs-soft/80 bg-white p-5 shadow-md shadow-black/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-primary">{{ __('Proctoring & review') }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-qs-muted">{{ __('Monitor sessions and review evidence when integrity checks matter.') }}</p>
                    </div>
                    <div class="rounded-2xl border border-qs-soft/80 bg-white p-5 shadow-md shadow-black/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-primary">{{ __('Practice quizzes') }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-qs-muted">{{ __('Let students warm up with practice while keeping formal exams separate.') }}</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-qs-soft py-8 text-center text-xs text-qs-muted">
            © {{ date('Y') }} {{ config('app.name', 'QUIZSNAP') }}
        </footer>
    </div>
</body>
</html>
