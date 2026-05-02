<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'QUIZSNAP') }} — {{ __('Home') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-qs-bg text-qs-text">
    <div class="min-h-screen bg-qs-bg">
        <header class="border-b border-qs-soft bg-qs-bg">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
                <h1 class="text-xl font-semibold tracking-tight text-qs-text">{{ config('app.name', 'QUIZSNAP') }}</h1>
                <div class="flex flex-wrap items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="qs-btn-primary text-sm">{{ __('Go to Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="qs-btn-primary text-sm">{{ __('Login') }}</a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-16">
            <section class="grid gap-8 lg:grid-cols-2 lg:items-start">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-qs-soft">{{ __('Smart Exam Proctoring for Universities') }}</p>
                    <h2 class="mt-4 text-3xl font-semibold leading-tight text-qs-text md:text-4xl">
                        {{ __('Secure online assessments made simple for every role.') }}
                    </h2>
                    <p class="mt-6 max-w-xl text-base text-qs-text">
                        {{ __('QUIZSNAP helps Admins manage institutions, Coordinators organize students and courses, and Students take monitored exams with confidence.') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="qs-btn-primary">{{ __('Open Unified Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="qs-btn-primary">{{ __('Sign In') }}</a>
                        @endauth
                    </div>
                </div>
                <div class="qs-surface p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-qs-soft bg-qs-card p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-qs-soft">{{ __('Exam Integrity') }}</p>
                            <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Protects assessment credibility with continuous proctoring oversight.') }}</p>
                        </div>
                        <div class="rounded-lg border border-qs-soft bg-qs-card p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-qs-soft">{{ __('Real-Time Monitoring') }}</p>
                            <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Tracks exam behavior live and supports fast incident response.') }}</p>
                        </div>
                        <div class="rounded-lg border border-qs-soft bg-qs-card p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-qs-soft">{{ __('Trusted Experience') }}</p>
                            <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Creates a fair environment where genuine performance stands out.') }}</p>
                        </div>
                        <div class="rounded-lg border border-qs-soft bg-qs-card p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-qs-soft">{{ __('Smart Visibility') }}</p>
                            <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Clear alerts and exam insights help teams make confident decisions.') }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
