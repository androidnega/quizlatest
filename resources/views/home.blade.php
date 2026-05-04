<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('Secure digital quizzes and exams for schools — verified students, structured assessments, and trusted results.') }}">
    <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Secure digital quizzes and exams') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-qs-bg font-sans text-qs-text antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-50 border-b border-qs-soft bg-white shadow-sm">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <x-brand-logo class="text-xl sm:text-2xl" interactive :href="url('/')" />
                <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                    <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-5 py-2.5 text-sm font-semibold">
                        {{ __('Student login') }}
                    </a>
                    <a href="{{ route('staff.login') }}" class="qs-btn-secondary min-h-[44px] px-4 py-2.5 text-sm font-semibold">
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

        <main class="flex-1">
            {{-- Hero --}}
            <section class="border-b border-qs-soft bg-qs-bg">
                <div class="mx-auto grid max-w-6xl min-w-0 gap-10 px-5 py-14 sm:gap-12 sm:py-16 md:grid-cols-2 md:items-center md:gap-14 md:py-20 lg:gap-16 lg:px-8 lg:py-24">
                    <div class="min-w-0 max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-qs-primary">{{ __('Built for schools') }}</p>
                        <h1 class="mt-4 text-3xl font-semibold leading-[1.12] tracking-tight text-qs-text sm:text-4xl lg:text-[2.65rem] lg:leading-tight">
                            <span class="block">{{ __('Secure digital') }}</span>
                            <span class="mt-1 block text-qs-primary">{{ __('quizzes and exams') }}</span>
                            <span class="mt-1 block text-qs-text">{{ __('for schools') }}</span>
                        </h1>
                        <p class="mt-5 text-base font-medium leading-snug text-qs-text sm:text-lg">
                            {{ __('Verified students. Smart assessments. Trusted results.') }}
                        </p>
                        <p class="mt-4 max-w-xl text-base leading-relaxed text-qs-muted sm:text-lg">
                            {{ __('Plan exams with ease, support learners, keep sessions fair, read results fast, and add practice quizzes beside formal exams.') }}
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-3 sm:gap-4">
                            <a href="{{ route('login') }}" class="qs-btn-primary min-h-[48px] px-6 py-3 text-sm font-semibold sm:text-base">
                                {{ __('Student login') }}
                            </a>
                            <a href="{{ route('staff.login') }}" class="qs-btn-secondary min-h-[48px] px-6 py-3 text-sm font-semibold sm:text-base">
                                {{ __('Staff portal') }}
                            </a>
                        </div>
                    </div>

                    <div class="flex min-w-0 justify-center md:justify-end">
                        <x-online-quiz-hero class="w-full max-w-full" heading-id="home-oq-hero-heading" />
                    </div>
                </div>
            </section>

            {{-- Features --}}
            <section class="border-b border-qs-soft bg-qs-card/40 py-14 sm:py-16 md:py-20" aria-labelledby="home-features-heading">
                <div class="mx-auto max-w-6xl px-5 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 id="home-features-heading" class="text-2xl font-semibold tracking-tight text-qs-text sm:text-3xl">
                            {{ __('Built for integrity and clarity') }}
                        </h2>
                        <p class="mt-3 text-base leading-relaxed text-qs-muted sm:text-lg">
                            {{ __('One platform for verified students, structured exams, session oversight, and optional practice.') }}
                        </p>
                    </div>

                    <ul class="mt-10 grid gap-5 sm:grid-cols-2 sm:gap-6 lg:mt-12 lg:grid-cols-4 lg:gap-8">
                        <li class="qs-surface flex flex-col p-6 shadow-sm transition hover:border-qs-primary/30 hover:shadow-md">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            </span>
                            <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Verified students') }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Index-first sign-in and onboarding keep exam access tied to real learners.') }}</p>
                        </li>
                        <li class="qs-surface flex flex-col p-6 shadow-sm transition hover:border-qs-primary/30 hover:shadow-md">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                            </span>
                            <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Smart exam builder') }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Structure sections, pool questions, and publish with clear delivery rules.') }}</p>
                        </li>
                        <li class="qs-surface flex flex-col p-6 shadow-sm transition hover:border-qs-primary/30 hover:shadow-md">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            </span>
                            <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Proctoring & review') }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Monitor sessions and review evidence when integrity checks matter.') }}</p>
                        </li>
                        <li class="qs-surface flex flex-col p-6 shadow-sm transition hover:border-qs-primary/30 hover:shadow-md">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary ring-1 ring-qs-primary/15" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                            </span>
                            <h3 class="mt-4 text-base font-semibold text-qs-text">{{ __('Practice quizzes') }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-qs-muted">{{ __('Let students warm up with practice while keeping formal exams separate.') }}</p>
                        </li>
                    </ul>
                </div>
            </section>
        </main>

        <footer class="border-t border-qs-soft bg-qs-card/50 py-10 text-center">
            <p class="text-sm font-medium text-qs-text">{{ config('app.name', 'QuizSnap') }}</p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Digital quizzes and exams for schools') }} · © {{ date('Y') }}</p>
        </footer>
    </div>
</body>
</html>
