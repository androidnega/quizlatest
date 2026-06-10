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
<body class="min-h-screen bg-white font-sans text-qs-text antialiased md:bg-qs-bg">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-50 border-b border-qs-soft/80 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85 md:shadow-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-4 md:gap-4 md:px-8">
                <x-brand-logo class="text-lg sm:text-xl md:text-2xl" interactive :href="url('/')" />
                <nav class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('about') }}" class="hidden min-h-[44px] rounded-lg px-3 py-2 text-sm font-semibold text-[var(--qs-muted)] transition hover:bg-qs-soft/60 hover:text-[var(--qs-text)] sm:inline-flex sm:items-center md:px-4 md:py-2.5">
                        {{ __('About us') }}
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="qs-btn-primary min-h-[44px] px-4 py-2 text-sm font-semibold sm:px-5 sm:py-2.5">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-4 py-2 text-sm font-semibold sm:px-5 sm:py-2.5">
                            {{ __('Student login') }}
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{-- Mobile hero — clean, typographic, white background, no banner image --}}
            <section class="md:hidden">
                <div class="mx-auto max-w-md px-5 pt-12 pb-10 text-center">
                    <span class="inline-flex items-center gap-2 rounded-full border border-qs-soft bg-white px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-[var(--qs-muted)]">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-[var(--qs-primary)]"></span>
                        {{ __('Built for schools') }}
                    </span>
                    <h1 class="mt-5 text-balance text-[1.85rem] font-semibold leading-[1.15] tracking-tight text-[var(--qs-text)]">
                        {{ __('Secure digital') }}
                        <span class="block text-[var(--qs-primary)]">{{ __('quizzes and exams') }}</span>
                        {{ __('for schools') }}
                    </h1>
                    <p class="mx-auto mt-5 max-w-sm text-pretty text-base leading-relaxed text-[var(--qs-muted)]">
                        {{ __('Verified students. Smart assessments. Trusted results.') }}
                    </p>

                    <div class="mt-8 flex flex-col gap-2.5">
                        <a href="{{ route('login') }}" class="qs-btn-primary min-h-[48px] w-full px-6 py-3 text-sm font-semibold">
                            <i class="fa-solid fa-arrow-right-to-bracket mr-2 text-xs" aria-hidden="true"></i>
                            {{ __('Student login') }}
                        </a>
                        <a href="{{ route('about') }}" class="inline-flex min-h-[48px] w-full items-center justify-center rounded-lg border border-qs-soft bg-white px-6 py-3 text-sm font-semibold text-[var(--qs-text)] transition hover:border-[var(--qs-primary)]/40 hover:text-[var(--qs-primary)]">
                            {{ __('About us') }}
                        </a>
                    </div>

                    <ul class="mx-auto mt-10 grid max-w-xs gap-3 text-left">
                        <li class="flex items-start gap-3 rounded-xl border border-qs-soft bg-white p-3.5">
                            <span class="mt-0.5 inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-[var(--qs-primary)]/[0.10] text-[var(--qs-primary)]">
                                <i class="fa-solid fa-shield-halved text-sm" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Verified students') }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-[var(--qs-muted)]">{{ __('Sign in with your school-issued index and credentials.') }}</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3 rounded-xl border border-qs-soft bg-white p-3.5">
                            <span class="mt-0.5 inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-[var(--qs-primary)]/[0.10] text-[var(--qs-primary)]">
                                <i class="fa-solid fa-bolt text-sm" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Smart assessments') }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-[var(--qs-muted)]">{{ __('Quizzes, exams, and assignments — connected to your class.') }}</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3 rounded-xl border border-qs-soft bg-white p-3.5">
                            <span class="mt-0.5 inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-[var(--qs-primary)]/[0.10] text-[var(--qs-primary)]">
                                <i class="fa-solid fa-chart-line text-sm" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Trusted results') }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-[var(--qs-muted)]">{{ __('Marks released by your examiner — same place every term.') }}</p>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-10 inline-flex items-center gap-2 rounded-full border border-amber-200/80 bg-amber-50 px-3.5 py-2 text-[0.7rem] font-semibold text-amber-900">
                        <i class="fa-solid fa-desktop text-xs text-amber-700" aria-hidden="true"></i>
                        {{ __('Quizzes are taken on a desktop or laptop') }}
                    </div>
                </div>
            </section>

            {{-- Tablet and up: headline + desktop artwork --}}
            <section class="hidden border-b border-qs-soft bg-qs-bg md:block">
                <div class="mx-auto max-w-6xl min-w-0 gap-10 px-5 py-14 sm:gap-12 sm:py-16 md:grid md:grid-cols-2 md:items-center md:gap-14 md:py-20 lg:gap-16 lg:px-8 lg:py-24">
                    <div class="min-w-0 max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--qs-primary)]">{{ __('Built for schools') }}</p>
                        <h1 class="mt-4 text-3xl font-semibold leading-[1.12] tracking-tight text-[var(--qs-text)] sm:text-4xl lg:text-[2.65rem] lg:leading-tight">
                            <span class="block text-[var(--qs-text)]">{{ __('Secure digital') }}</span>
                            <span class="mt-1 block text-[var(--qs-primary)]">{{ __('quizzes and exams') }}</span>
                            <span class="mt-1 block text-[var(--qs-text)]">{{ __('for schools') }}</span>
                        </h1>
                        <p class="mt-5 text-base font-medium leading-snug text-[var(--qs-primary)] sm:text-lg">
                            {{ __('Verified students. Smart assessments. Trusted results.') }}
                        </p>
                        <p class="mt-4 max-w-xl text-base leading-relaxed text-[var(--qs-muted)] sm:text-lg">
                            {{ __('Plan exams with ease, support learners, keep sessions fair, read results fast, and add practice quizzes beside formal exams.') }}
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-3 sm:gap-4">
                            <a href="{{ route('login') }}" class="qs-btn-primary min-h-[48px] px-6 py-3 text-sm font-semibold sm:text-base">
                                {{ __('Student login') }}
                            </a>
                        </div>
                    </div>

                    <div class="flex min-w-0 justify-center md:justify-end">
                        <x-online-quiz-hero class="w-full max-w-full" />
                    </div>
                </div>
            </section>

        </main>

        <footer class="border-t border-qs-soft bg-white py-8 text-center md:bg-qs-card/50 md:py-10">
            <p class="text-sm font-medium text-qs-text">{{ config('app.name', 'QuizSnap') }}</p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Digital quizzes and exams for schools') }} · © {{ date('Y') }}</p>
        </footer>
    </div>
</body>
</html>
