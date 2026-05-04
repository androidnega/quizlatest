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
                    {{ __('Student login') }}
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

        {{-- Features --}}
        <section class="border-b border-qs-soft bg-qs-card/50 py-16 sm:py-20 md:py-24" aria-labelledby="home-features-heading">
            <div class="mx-auto max-w-6xl px-5 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 id="home-features-heading" class="text-2xl font-semibold tracking-tight text-qs-text sm:text-3xl">
                        {{ __('Built for integrity and clarity') }}
                    </h2>
                    <p class="mt-3 text-base leading-relaxed text-qs-muted sm:text-lg">
                        {{ __('One platform for verified students, structured exams, session oversight, and optional practice.') }}
                    </p>
                </div>

                <ul class="mt-12 grid gap-6 sm:grid-cols-2 lg:mt-14 lg:grid-cols-4 lg:gap-8">
                    <li class="group flex flex-col rounded-2xl border border-qs-soft bg-white p-6 shadow-sm transition hover:border-qs-primary/25 hover:shadow-md">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Verified students') }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Index-first sign-in and onboarding keep exam access tied to real learners.') }}</p>
                    </li>
                    <li class="group flex flex-col rounded-2xl border border-qs-soft bg-white p-6 shadow-sm transition hover:border-qs-primary/25 hover:shadow-md">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Smart exam builder') }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Structure sections, pool questions, and publish with clear delivery rules.') }}</p>
                    </li>
                    <li class="group flex flex-col rounded-2xl border border-qs-soft bg-white p-6 shadow-sm transition hover:border-qs-primary/25 hover:shadow-md">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Proctoring & review') }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Monitor sessions and review evidence when integrity checks matter.') }}</p>
                    </li>
                    <li class="group flex flex-col rounded-2xl border border-qs-soft bg-white p-6 shadow-sm transition hover:border-qs-primary/25 hover:shadow-md">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Practice quizzes') }}</h3>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Let students warm up with practice while keeping formal exams separate.') }}</p>
                    </li>
                </ul>
            </div>
        </section>

        {{-- Closing CTA --}}
        <section class="bg-white py-14 sm:py-16 md:py-20">
            <div class="mx-auto max-w-6xl px-5 text-center lg:px-8">
                <p class="text-lg font-medium text-qs-text sm:text-xl">{{ __('Ready for your next exam window?') }}</p>
                <p class="mx-auto mt-2 max-w-lg text-sm text-qs-muted sm:text-base">{{ __('Use your institution index number to sign in. Contact your class rep if you need access.') }}</p>
                <a href="{{ route('login') }}" class="qs-btn-primary mt-8 inline-flex min-h-[48px] items-center justify-center px-8 py-3 text-sm font-semibold sm:text-base">
                    {{ __('Student login') }}
                </a>
            </div>
        </section>
    </main>

    <footer class="border-t border-qs-soft bg-qs-card/30 py-10 text-center">
        <p class="text-sm text-qs-muted">
            © {{ date('Y') }} {{ config('app.name', 'QuizSnap') }}. {{ __('All rights reserved.') }}
        </p>
    </footer>
</body>
</html>
