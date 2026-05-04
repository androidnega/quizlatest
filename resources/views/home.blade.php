<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('Secure digital quizzes and exams for schools — verified students, structured assessments, and trusted results.') }}">
    <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Secure digital quizzes and exams') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-qs-text antialiased">
    {{-- Sticky header: clear brand + primary action --}}
    <header class="sticky top-0 z-50 border-b border-qs-soft bg-white/95 shadow-sm backdrop-blur-md">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
            <x-brand-logo class="text-xl sm:text-2xl" interactive :href="url('/')" />
            <nav class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-5 py-2.5 text-sm font-semibold">
                    {{ __('Sign in') }}
                </a>
                <a href="{{ route('staff.login') }}" class="text-sm font-medium text-qs-muted underline-offset-4 transition hover:text-qs-primary hover:underline">
                    {{ __('Staff portal') }}
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="qs-btn-secondary min-h-[44px] px-4 py-2.5 text-sm font-semibold">
                        {{ __('Dashboard') }}
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        {{-- Hero --}}
        <section class="border-b border-qs-soft bg-white">
            <div class="mx-auto grid max-w-6xl gap-12 px-5 py-16 sm:gap-14 sm:py-20 md:grid-cols-2 md:items-center md:py-24 lg:gap-16 lg:px-8">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-qs-primary">{{ __('Built for schools') }}</p>
                    <h1 class="mt-4 text-3xl font-semibold leading-[1.12] tracking-tight text-qs-text sm:text-4xl lg:text-5xl">
                        <span class="block">{{ __('Secure digital') }}</span>
                        <span class="mt-1 block min-w-0 max-w-full overflow-x-auto whitespace-nowrap text-qs-primary [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <x-animated-word variant="hero" as="span" :text="__('quizzes and exams')" class="font-semibold text-qs-primary" />
                        </span>
                        <span class="mt-1 block text-qs-text">{{ __('for schools') }}</span>
                    </h1>
                    <p class="sr-only">{{ __('Verified students. Smart assessments. Trusted results.') }}</p>
                    <p class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm font-semibold text-qs-text sm:text-base" aria-hidden="true">
                        <span class="text-qs-primary">{{ __('Verified students') }}</span>
                        <span class="text-qs-muted">·</span>
                        <span class="text-qs-primary">{{ __('Smart assessments') }}</span>
                        <span class="text-qs-muted">·</span>
                        <span class="text-qs-primary">{{ __('Trusted results') }}</span>
                    </p>
                    <p class="mt-4 max-w-xl text-base leading-relaxed text-qs-muted sm:text-lg">
                        {{ __('Plan assessments, verify learners, run proctored sessions, and review outcomes — with practice quizzes alongside formal exams.') }}
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="{{ route('login') }}" class="qs-btn-primary min-h-[48px] px-6 py-3 text-sm font-semibold sm:text-base">
                            {{ __('Sign in with index number') }}
                        </a>
                    </div>
                </div>

                <div class="flex justify-center md:justify-end">
                    <div class="w-full max-w-md rounded-3xl border border-qs-soft bg-white p-6 shadow-xl shadow-qs-text/5 sm:p-8 md:max-w-lg">
                        <svg class="h-auto w-full" viewBox="0 0 320 220" role="img" aria-labelledby="home-hero-title">
                            <title id="home-hero-title">{{ __('Illustration of an answer sheet and checklist') }}</title>
                            <rect x="24" y="20" width="200" height="160" rx="12" fill="#fff" stroke="#166534" stroke-width="2"/>
                            <rect x="44" y="48" width="120" height="8" rx="4" fill="#dcfce7"/>
                            <rect x="44" y="68" width="90" height="8" rx="4" fill="#e5e7eb"/>
                            <rect x="44" y="88" width="100" height="8" rx="4" fill="#e5e7eb"/>
                            <circle cx="52" cy="118" r="5" fill="#166534"/>
                            <rect x="64" y="112" width="80" height="8" rx="4" fill="#e5e7eb"/>
                            <circle cx="52" cy="138" r="5" stroke="#166534" stroke-width="2" fill="#fff"/>
                            <rect x="64" y="132" width="70" height="8" rx="4" fill="#e5e7eb"/>
                            <path d="M200 40 L280 40 L280 200 L120 200 L120 120 Z" fill="#dcfce7" opacity="0.55"/>
                            <rect x="210" y="56" width="72" height="100" rx="8" fill="#fff" stroke="#166534" stroke-width="1.5"/>
                            <rect x="222" y="72" width="48" height="6" rx="3" fill="#166534" opacity="0.35"/>
                            <rect x="222" y="86" width="36" height="6" rx="3" fill="#d1d5db"/>
                            <rect x="222" y="100" width="40" height="6" rx="3" fill="#d1d5db"/>
                            <rect x="222" y="114" width="32" height="6" rx="3" fill="#d1d5db"/>
                            <rect x="208" y="128" width="76" height="4" rx="2" fill="#166534" opacity="0.45"/>
                        </svg>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-qs-soft bg-qs-card/30 py-10 text-center">
        <p class="text-sm font-medium text-qs-text">{{ config('app.name', 'QuizSnap') }}</p>
        <p class="mt-1 text-xs text-qs-muted">{{ __('Digital quizzes and exams for schools') }} · © {{ date('Y') }}</p>
    </footer>
</body>
</html>
