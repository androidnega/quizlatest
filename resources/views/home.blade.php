<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('Secure digital quizzes and exams for schools — verified students, structured assessments, and trusted results.') }}">
    <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Secure digital quizzes and exams') }}</title>
    @include('layouts.partials.favicon')
    @include('marketing.partials.cdn-head')
</head>
<body class="min-h-screen bg-white font-sans text-qs-text antialiased md:bg-qs-bg">
    <div class="flex min-h-screen flex-col">
        {{-- =============== Sticky marketing nav =============== --}}
        <header class="sticky top-0 z-50 border-b border-qs-soft/80 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85 md:shadow-sm">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-4 md:gap-4 md:px-8">
                <a href="{{ url('/') }}" class="inline-flex items-baseline text-lg font-bold tracking-tight sm:text-xl md:text-2xl" aria-label="{{ config('app.name', 'QuizSnap') }} home">
                    <span class="text-qs-primary">Quiz</span><span class="text-qs-text">Snap</span>
                </a>
                <nav class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('about') }}" class="hidden min-h-[44px] rounded-lg px-3 py-2 text-sm font-semibold text-qs-muted transition hover:bg-qs-soft/60 hover:text-qs-text sm:inline-flex sm:items-center md:px-4 md:py-2.5">
                        {{ __('About us') }}
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-qs-primary-deep sm:px-5 sm:py-2.5">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-qs-primary-deep sm:px-5 sm:py-2.5">
                            {{ __('Student login') }}
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{-- =============== Mobile hero — clean typographic layout, white bg =============== --}}
            <section class="md:hidden">
                <div class="mx-auto max-w-md px-5 pt-12 pb-10 text-center">
                    <span class="inline-flex items-center gap-2 rounded-full border border-qs-soft bg-white px-3 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-qs-muted">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-qs-primary"></span>
                        {{ __('Built for schools') }}
                    </span>
                    <h1 class="mt-5 text-balance text-[1.85rem] font-semibold leading-[1.15] tracking-tight text-qs-text">
                        {{ __('Secure digital') }}
                        <span class="block text-qs-primary">{{ __('quizzes and exams') }}</span>
                        {{ __('for schools') }}
                    </h1>
                    <p class="mx-auto mt-5 max-w-sm text-pretty text-base leading-relaxed text-qs-muted">
                        {{ __('Verified students. Smart assessments. Trusted results.') }}
                    </p>

                    <div class="mt-8 flex flex-col gap-2.5">
                        <a href="{{ route('login') }}" class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-lg bg-qs-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-qs-primary-deep">
                            <i class="fa-solid fa-arrow-right-to-bracket text-xs" aria-hidden="true"></i>
                            {{ __('Student login') }}
                        </a>
                        <a href="{{ route('about') }}" class="inline-flex min-h-[48px] w-full items-center justify-center rounded-lg border border-qs-soft bg-white px-6 py-3 text-sm font-semibold text-qs-text transition hover:border-qs-primary/40 hover:text-qs-primary">
                            {{ __('About us') }}
                        </a>
                    </div>

                    <ul class="mx-auto mt-10 grid max-w-xs gap-3 text-left">
                        @foreach ([
                            ['icon' => 'fa-shield-halved', 'title' => __('Verified students'),  'body' => __('Sign in with your school-issued index and credentials.')],
                            ['icon' => 'fa-bolt',          'title' => __('Smart assessments'), 'body' => __('Quizzes, exams, and assignments — connected to your class.')],
                            ['icon' => 'fa-chart-line',    'title' => __('Trusted results'),   'body' => __('Marks released by your examiner — same place every term.')],
                        ] as $feature)
                            <li class="flex items-start gap-3 rounded-xl border border-qs-soft bg-white p-3.5">
                                <span class="mt-0.5 inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-qs-primary/10 text-qs-primary">
                                    <i class="fa-solid {{ $feature['icon'] }} text-sm" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-qs-text">{{ $feature['title'] }}</p>
                                    <p class="mt-0.5 text-xs leading-relaxed text-qs-muted">{{ $feature['body'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-10 inline-flex items-center gap-2 rounded-full border border-amber-200/80 bg-amber-50 px-3.5 py-2 text-[0.7rem] font-semibold text-amber-900">
                        <i class="fa-solid fa-desktop text-xs text-amber-700" aria-hidden="true"></i>
                        {{ __('Quizzes are taken on a desktop or laptop') }}
                    </div>
                </div>
            </section>

            {{-- =============== Tablet+ hero with inlined visual =============== --}}
            <section class="hidden border-b border-qs-soft bg-qs-bg md:block">
                <div class="mx-auto max-w-6xl min-w-0 gap-10 px-5 py-14 sm:gap-12 sm:py-16 md:grid md:grid-cols-2 md:items-center md:gap-14 md:py-20 lg:gap-16 lg:px-8 lg:py-24">
                    <div class="min-w-0 max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-qs-primary">{{ __('Built for schools') }}</p>
                        <h1 class="mt-4 text-3xl font-semibold leading-[1.12] tracking-tight text-qs-text sm:text-4xl lg:text-[2.65rem] lg:leading-tight">
                            <span class="block">{{ __('Secure digital') }}</span>
                            <span class="mt-1 block text-qs-primary">{{ __('quizzes and exams') }}</span>
                            <span class="mt-1 block">{{ __('for schools') }}</span>
                        </h1>
                        <p class="mt-5 text-base font-medium leading-snug text-qs-primary sm:text-lg">
                            {{ __('Verified students. Smart assessments. Trusted results.') }}
                        </p>
                        <p class="mt-4 max-w-xl text-base leading-relaxed text-qs-muted sm:text-lg">
                            {{ __('Plan exams with ease, support learners, keep sessions fair, read results fast, and add practice quizzes beside formal exams.') }}
                        </p>
                        <div class="mt-8 flex flex-wrap items-center gap-3 sm:gap-4">
                            <a href="{{ route('login') }}" class="inline-flex min-h-[48px] items-center gap-2 rounded-lg bg-qs-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-qs-primary-deep sm:text-base">
                                {{ __('Student login') }}
                            </a>
                        </div>
                    </div>

                    {{-- Inlined desktop hero visual (used to be <x-online-quiz-hero />). Pure Tailwind, no <style> block, no qs-* hooks. --}}
                    <div
                        data-online-quiz-hero="1"
                        class="group relative mx-auto w-full min-w-0 max-w-md sm:max-w-lg md:ml-auto md:max-w-xl lg:max-w-2xl"
                    >
                        <p class="sr-only">
                            {{ __('QuizSnap promotional illustration: a student on a laptop in a teal chair beside a phone showing secure digital quizzes and exams for schools.') }}
                        </p>

                        <div class="relative mx-auto aspect-[4/3] w-full max-h-[min(58vh,480px)] sm:max-h-[min(62vh,520px)]">
                            {{-- Soft glow, behind the image --}}
                            <div class="pointer-events-none absolute inset-[8%] -z-10 rounded-[40%] bg-qs-primary/20 blur-3xl" aria-hidden="true"></div>

                            {{-- Decorative accent rings --}}
                            <div class="pointer-events-none absolute -right-3 top-[6%] -z-10 h-28 w-28 rounded-full border-2 border-qs-primary/25 sm:-right-5 sm:h-36 sm:w-36" aria-hidden="true"></div>
                            <div class="pointer-events-none absolute -bottom-2 -left-4 -z-10 h-24 w-24 rounded-full border border-qs-soft bg-qs-primary/10 sm:-left-6 sm:h-32 sm:w-32" aria-hidden="true"></div>

                            <img
                                src="{{ asset('images/home/quizsnap-homepage-hero-desktop-student-laptop.jpg') }}"
                                alt=""
                                width="1024"
                                height="768"
                                decoding="async"
                                fetchpriority="high"
                                class="relative z-10 h-full w-full object-contain object-center drop-shadow-[0_24px_48px_rgba(26,43,48,0.12)]"
                            />
                        </div>
                    </div>
                </div>
            </section>

            {{-- =============== HOW IT WORKS =============== --}}
            <section class="border-t border-qs-soft bg-white py-12 sm:py-16 md:py-20">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="inline-flex items-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-qs-primary">
                            <span class="inline-block h-px w-5 bg-current opacity-60"></span>
                            {{ __('How it works') }}
                            <span class="inline-block h-px w-5 bg-current opacity-60"></span>
                        </span>
                        <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-qs-text sm:text-3xl lg:text-[2rem]">
                            {{ __('Three steps from "let\'s set up an exam" to "results released".') }}
                        </h2>
                        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-qs-muted sm:text-base">
                            {{ __('Coordinators, examiners, and students stay on the same rails — no more chasing spreadsheets, lost answer scripts, or "where do I sit my exam?" confusion.') }}
                        </p>
                    </div>

                    <ol class="mt-10 grid gap-4 sm:gap-5 md:grid-cols-3 lg:gap-6">
                        @foreach ([
                            ['n' => 1, 'title' => __('Coordinator sets up the cohort'),    'body' => __('Classes, courses, examiners, and students get registered once — students sign in with the index your school issues.')],
                            ['n' => 2, 'title' => __('Examiner publishes the paper'),     'body' => __('MCQ, true/false, fill-in-the-blank, essays, or coursework — set duration, marks, and proctoring strictness in minutes.')],
                            ['n' => 3, 'title' => __('Students attempt, results follow'), 'body' => __('Timed sessions, fullscreen focus, and verification on demand. Marks land in the same dashboard your school already uses.')],
                        ] as $step)
                            <li class="relative rounded-2xl border border-qs-soft bg-white p-5 sm:p-6">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-qs-primary/10 text-base font-bold text-qs-primary">{{ $step['n'] }}</span>
                                <h3 class="mt-4 text-base font-semibold tracking-tight text-qs-text sm:text-lg">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-qs-muted">{{ $step['body'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- =============== ONE PLATFORM, EVERY ROLE =============== --}}
            <section class="border-t border-qs-soft bg-qs-bg py-12 sm:py-16 md:py-20">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <div class="grid gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)] lg:items-center lg:gap-14">
                        <div>
                            <span class="inline-flex items-center gap-2 text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-qs-primary">
                                <span class="inline-block h-px w-5 bg-current opacity-60"></span>
                                {{ __('One platform, every role') }}
                            </span>
                            <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-qs-text sm:text-3xl lg:text-[2rem]">
                                {{ __('Built around how schools actually run exams.') }}
                            </h2>
                            <p class="mt-3 text-sm leading-relaxed text-qs-muted sm:text-base">
                                {{ __('No bolted-on student portal. No examiner spreadsheet. Each role gets a dashboard tuned to what they own.') }}
                            </p>
                            <a href="{{ route('about') }}" class="mt-6 inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-qs-soft bg-white px-4 py-2.5 text-sm font-semibold text-qs-text transition hover:border-qs-primary/40 hover:text-qs-primary">
                                {{ __('Read the full story') }}
                                <i class="fa-solid fa-arrow-right text-[0.7rem]" aria-hidden="true"></i>
                            </a>
                        </div>

                        <ul class="grid gap-3 sm:grid-cols-2 sm:gap-4">
                            @foreach ([
                                ['icon' => 'fa-graduation-cap', 'title' => __('For students'),       'body' => __('Sign in with your index, see scheduled exams, attempt in a controlled session, and track your results.')],
                                ['icon' => 'fa-pen-ruler',      'title' => __('For examiners'),      'body' => __('Build papers from a question bank, set proctoring strictness, mark essays, and release scores when ready.')],
                                ['icon' => 'fa-people-group',   'title' => __('For coordinators'),   'body' => __('Onboard students, manage classes and courses, and keep the academic year on a clear timetable.')],
                                ['icon' => 'fa-shield-halved',  'title' => __('Integrity that scales'),'body' => __('Optional fullscreen lock, camera monitoring, verification at start, and an audit-ready event trail.')],
                            ] as $role)
                                <li class="rounded-xl border border-qs-soft bg-white p-4 transition hover:border-qs-primary/40 sm:p-5">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-qs-primary/10 text-qs-primary">
                                        <i class="fa-solid {{ $role['icon'] }} text-sm" aria-hidden="true"></i>
                                    </span>
                                    <h3 class="mt-3 text-sm font-semibold text-qs-text sm:text-base">{{ $role['title'] }}</h3>
                                    <p class="mt-1.5 text-xs leading-relaxed text-qs-muted sm:text-sm">{{ $role['body'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>

            {{-- =============== CLOSING CTA =============== --}}
            <section class="border-t border-qs-soft bg-white py-12 sm:py-16 md:py-20">
                <div class="mx-auto max-w-3xl px-5 sm:px-8 lg:px-8">
                    <div class="relative overflow-hidden rounded-2xl border border-qs-soft bg-gradient-to-br from-white via-white to-[#eaf3f5] px-6 py-10 text-center shadow-sm sm:px-10 sm:py-12">
                        <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-qs-primary/10" aria-hidden="true"></div>
                        <div class="pointer-events-none absolute -bottom-16 -left-16 h-52 w-52 rounded-full bg-qs-primary/5" aria-hidden="true"></div>
                        <div class="relative">
                            <h2 class="text-xl font-semibold tracking-tight text-qs-text sm:text-2xl md:text-[1.65rem]">
                                {{ __('Sign in with the access your school gave you.') }}
                            </h2>
                            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-qs-muted sm:text-base">
                                {{ __('Use the index number and credentials your coordinator registered for you — that\'s the front door to your exams and any practice your institution enables.') }}
                            </p>
                            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                                <a href="{{ route('login') }}" class="inline-flex min-h-[48px] items-center gap-2 rounded-lg bg-qs-primary px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-qs-primary-deep">
                                    <i class="fa-solid fa-arrow-right-to-bracket text-xs" aria-hidden="true"></i>
                                    {{ __('Student login') }}
                                </a>
                                <a href="{{ route('about') }}" class="inline-flex min-h-[48px] items-center gap-2 rounded-lg border border-qs-soft bg-white px-5 py-2.5 text-sm font-semibold text-qs-text transition hover:border-qs-primary/40 hover:text-qs-primary">
                                    {{ __('About QuizSnap') }}
                                </a>
                            </div>
                            <p class="mt-6 text-xs text-qs-muted">
                                <i class="fa-solid fa-desktop mr-1.5 text-qs-primary" aria-hidden="true"></i>
                                {{ __('Quizzes and exams are taken on a desktop or laptop. Everything else works on mobile.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-qs-soft bg-white py-8 text-center md:py-10">
            <p class="text-sm font-medium text-qs-text">{{ config('app.name', 'QuizSnap') }}</p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Digital quizzes and exams for schools') }} · © {{ date('Y') }}</p>
        </footer>
    </div>
</body>
</html>
