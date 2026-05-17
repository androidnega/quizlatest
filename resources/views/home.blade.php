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
                    <a href="{{ route('about') }}" class="qs-btn-secondary min-h-[44px] px-4 py-2.5 text-sm font-semibold">
                        <i class="fa-solid fa-circle-info mr-2" aria-hidden="true"></i>{{ __('About us') }}
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="qs-btn-secondary min-h-[44px] px-4 py-2.5 text-sm font-semibold">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-4 py-2.5 text-sm font-semibold">
                            {{ __('Student login') }}
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{-- Hero --}}
            <section class="border-b border-qs-soft bg-qs-bg">
                {{-- Mobile: promotional banner (copy is in the artwork) --}}
                <div class="md:hidden">
                    <div class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-5 sm:py-10">
                        <x-home-hero-mobile />
                    </div>
                </div>

                {{-- Tablet and up: headline + desktop artwork --}}
                <div class="mx-auto hidden max-w-6xl min-w-0 gap-10 px-5 py-14 sm:gap-12 sm:py-16 md:grid md:grid-cols-2 md:items-center md:gap-14 md:py-20 lg:gap-16 lg:px-8 lg:py-24">
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

        <footer class="border-t border-qs-soft bg-qs-card/50 py-10 text-center">
            <p class="text-sm font-medium text-qs-text">{{ config('app.name', 'QuizSnap') }}</p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Digital quizzes and exams for schools') }} · © {{ date('Y') }}</p>
        </footer>
    </div>
</body>
</html>
