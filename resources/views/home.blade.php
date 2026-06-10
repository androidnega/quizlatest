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
        {{-- =============== Sticky marketing nav (Podium-inspired: airy, well-spaced) =============== --}}
        <header class="sticky top-0 z-50 border-b border-qs-soft/60 bg-white/90 backdrop-blur supports-[backdrop-filter]:bg-white/75">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-6 md:gap-6 md:px-10 md:py-5">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 text-lg font-bold tracking-tight md:text-xl" aria-label="{{ config('app.name', 'QuizSnap') }} home">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-qs-primary text-white shadow-sm">
                        <i class="fa-solid fa-graduation-cap text-sm" aria-hidden="true"></i>
                    </span>
                    <span><span class="text-qs-primary">Quiz</span><span class="text-qs-text">Snap</span></span>
                </a>
                <nav class="flex items-center gap-1 sm:gap-2 md:gap-3">
                    <a href="{{ route('about') }}" class="hidden min-h-[44px] rounded-lg px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-qs-muted transition hover:text-qs-text sm:inline-flex sm:items-center md:px-4">
                        {{ __('About') }}
                    </a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="hidden min-h-[44px] rounded-lg px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-qs-muted transition hover:text-qs-text sm:inline-flex sm:items-center md:px-4">
                            {{ __('Dashboard') }}
                        </a>
                        <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-text px-4 py-2.5 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-white shadow-sm transition hover:bg-[#1a2a2e] md:px-5">
                            {{ __('Get Started') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="hidden min-h-[44px] rounded-lg px-3 py-2 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-qs-muted transition hover:text-qs-text sm:inline-flex sm:items-center md:px-4">
                            {{ __('Sign in') }}
                        </a>
                        <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-text px-4 py-2.5 text-[0.7rem] font-bold uppercase tracking-[0.16em] text-white shadow-sm transition hover:bg-[#1a2a2e] md:px-5">
                            {{ __('Student login') }}
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{-- =============== Mobile hero — clean typographic layout, white bg =============== --}}
            <section class="md:hidden">
                <div class="mx-auto max-w-md px-5 pt-14 pb-12 text-center">
                    <span class="inline-flex items-center gap-2 rounded-full bg-qs-primary/10 px-3 py-1.5 text-[0.62rem] font-bold uppercase tracking-[0.18em] text-qs-primary ring-1 ring-qs-primary/15">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-qs-primary"></span>
                        {{ __('Built for schools') }}
                    </span>
                    <h1 class="mt-6 text-balance text-[2.1rem] font-bold leading-[1.08] tracking-tight text-qs-text">
                        <span class="block">{{ __('Secure Digital') }}</span>
                        <span class="block text-qs-primary">{{ __('Exams. Perfected.') }}</span>
                    </h1>
                    <p class="mx-auto mt-5 max-w-sm text-pretty text-base leading-relaxed text-qs-muted">
                        {{ __('Verified students. Smart assessments. Trusted results.') }}
                    </p>

                    <div class="mt-8 flex flex-col gap-2.5">
                        <a href="{{ route('login') }}" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-md bg-qs-primary px-6 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-white shadow-lg shadow-qs-primary/30 transition hover:bg-qs-primary-deep">
                            {{ __('Student login') }}
                        </a>
                        <a href="{{ route('about') }}" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-md border border-qs-soft bg-white px-6 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-qs-text transition hover:border-qs-primary hover:text-qs-primary">
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

            {{-- =============== Tablet+ hero (Podium-inspired) =============== --}}
            {{--
              Podium-inspired desktop hero:
                • Two-column split with generous whitespace
                • Eyebrow pill with leading dot
                • Big bold H1, second line in brand color
                • Pair of uppercase, letter-spaced CTAs (solid + outline)
                • Polished photo card on the right with a bottom-left caption overlay
            --}}
            <section class="hidden border-b border-qs-soft bg-qs-bg md:block">
                <div class="mx-auto min-w-0 max-w-7xl px-5 py-20 sm:py-24 md:grid md:grid-cols-2 md:items-center md:gap-12 md:px-8 md:py-24 lg:gap-16 lg:py-28">
                    {{-- Left column: text + CTAs --}}
                    <div class="min-w-0 max-w-xl">
                        <span class="inline-flex items-center gap-2 rounded-full bg-qs-primary/10 px-3 py-1.5 text-[0.66rem] font-bold uppercase tracking-[0.18em] text-qs-primary ring-1 ring-qs-primary/15">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-qs-primary"></span>
                            {{ __('Built for schools') }}
                        </span>

                        <h1 class="mt-6 text-balance text-5xl font-bold leading-[1.05] tracking-tight text-qs-text sm:text-6xl lg:text-[4.25rem] lg:leading-[1.04]">
                            <span class="block">{{ __('Secure Digital') }}</span>
                            <span class="block text-qs-primary">{{ __('Exams. Perfected.') }}</span>
                        </h1>

                        <p class="mt-6 max-w-md text-base leading-relaxed text-qs-muted sm:text-lg">
                            {{ __('Verified students. Smart assessments. Trusted results.') }}
                            <span class="block mt-1">{{ __('The exam platform schools use when results have to count.') }}</span>
                        </p>

                        <div class="mt-9 flex flex-wrap items-center gap-3 sm:gap-4">
                            <a href="{{ route('login') }}" class="inline-flex min-h-[52px] items-center justify-center rounded-md bg-qs-primary px-7 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-white shadow-lg shadow-qs-primary/30 ring-1 ring-qs-primary/30 transition hover:bg-qs-primary-deep hover:shadow-xl hover:shadow-qs-primary/35">
                                {{ __('Student login') }}
                            </a>
                            <a href="{{ route('about') }}" class="inline-flex min-h-[52px] items-center justify-center rounded-md border border-qs-soft bg-white px-7 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-qs-text shadow-sm transition hover:border-qs-primary hover:text-qs-primary">
                                {{ __('About us') }}
                            </a>
                        </div>

                        {{-- Trust strip beneath CTAs (kept understated, like Podium's spacing) --}}
                        <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-2 text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-qs-muted">
                            <span class="inline-flex items-center gap-2">
                                <i class="fa-solid fa-shield-halved text-qs-primary" aria-hidden="true"></i>
                                {{ __('Optional proctoring') }}
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i class="fa-solid fa-bolt text-qs-primary" aria-hidden="true"></i>
                                {{ __('Built in-house') }}
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <i class="fa-solid fa-chart-line text-qs-primary" aria-hidden="true"></i>
                                {{ __('Audit-ready trail') }}
                            </span>
                        </div>
                    </div>

                    {{-- Right column: hero photo card with caption overlay --}}
                    <div
                        data-online-quiz-hero="1"
                        class="group relative mx-auto mt-12 w-full min-w-0 max-w-xl md:ml-auto md:mt-0 lg:max-w-2xl"
                    >
                        <p class="sr-only">
                            {{ __('QuizSnap promotional illustration: a student on a laptop in a teal chair beside a phone showing secure digital quizzes and exams for schools.') }}
                        </p>

                        {{-- Decorative accent shapes (subtle, behind the card) --}}
                        <div class="pointer-events-none absolute -right-6 -top-6 -z-10 h-32 w-32 rounded-full bg-qs-primary/20 blur-2xl sm:h-40 sm:w-40" aria-hidden="true"></div>
                        <div class="pointer-events-none absolute -bottom-8 -left-8 -z-10 h-40 w-40 rounded-full bg-qs-primary/10 blur-3xl" aria-hidden="true"></div>

                        {{-- The card itself: white bezel + rounded photo + caption overlay --}}
                        <div class="relative overflow-hidden rounded-[28px] bg-white p-2.5 shadow-2xl shadow-qs-text/15 ring-1 ring-qs-soft sm:p-3">
                            <div class="relative overflow-hidden rounded-[20px] bg-gradient-to-br from-qs-bg to-qs-soft">
                                <img
                                    src="{{ asset('images/home/quizsnap-homepage-hero-desktop-student-laptop.jpg') }}"
                                    alt=""
                                    width="1024"
                                    height="768"
                                    decoding="async"
                                    fetchpriority="high"
                                    class="aspect-[16/11] h-auto w-full object-cover object-center transition duration-700 group-hover:scale-[1.02]"
                                />

                                {{-- Gradient legibility wash for the caption --}}
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/75 via-black/30 to-transparent"></div>

                                {{-- Bottom-left caption (Podium "Seamless Syncing" pattern) --}}
                                <div class="absolute inset-x-5 bottom-5 sm:inset-x-7 sm:bottom-7">
                                    <h2 class="text-balance text-2xl font-bold leading-tight tracking-tight text-white sm:text-[1.7rem]">
                                        {{ __('Trusted Results') }}
                                    </h2>
                                    <p class="mt-1.5 max-w-md text-pretty text-sm leading-snug text-white/85 sm:text-base">
                                        {{ __('Marks released by your examiner — same place every term, with an audit trail your QA team can defend.') }}
                                    </p>
                                </div>
                            </div>
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
