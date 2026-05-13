<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('QuizSnap helps schools run serious digital exams: structured sessions, optional proctoring, and clear results — so you scale assessments without scaling chaos.') }}">
    <title>{{ __('About us') }} — {{ config('app.name', 'QuizSnap') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-qs-bg font-sans text-qs-text antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-50 border-b border-qs-soft bg-white shadow-sm">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <x-brand-logo class="text-xl sm:text-2xl" interactive :href="url('/')" />
                <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                    <a href="{{ route('home') }}" class="min-h-[44px] rounded-lg px-4 py-2.5 text-sm font-semibold text-[var(--qs-muted)] transition hover:bg-qs-soft/60 hover:text-[var(--qs-text)]">
                        {{ __('Home') }}
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
            <section class="relative overflow-hidden border-b border-qs-soft bg-[#0f1719] text-white">
                <div class="pointer-events-none absolute inset-0 opacity-[0.04]" style="background-image: radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0); background-size: 20px 20px;" aria-hidden="true"></div>
                <div class="relative mx-auto max-w-6xl px-5 py-14 text-center sm:px-8 sm:py-16 lg:px-8 lg:py-20">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.28em] text-white/55">{{ config('app.name', 'QuizSnap') }}</p>
                    <h1 class="mx-auto mt-4 max-w-3xl text-balance text-3xl font-semibold leading-[1.15] tracking-tight text-white sm:text-4xl">
                        {{ __('The exam platform schools use when results have to count') }}
                    </h1>
                    <p class="mx-auto mt-5 max-w-2xl text-pretty text-sm leading-relaxed text-white/65 sm:text-base">
                        {{ __('From cohort setup to final marks, QuizSnap keeps everyone on the same rails: coordinators organise, examiners deliver, students attempt in a controlled environment — and integrity options you can turn up when the stakes are high.') }}
                    </p>
                </div>
            </section>

            <section class="border-b border-qs-soft bg-white py-12 sm:py-14">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--qs-primary)]">{{ __('Why institutions pick QuizSnap') }}</p>
                    <div class="mt-8 grid gap-8 sm:grid-cols-3 sm:gap-6 lg:gap-10">
                        <div class="sm:pr-2">
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--qs-primary)]/[0.09] text-[var(--qs-primary)]">
                                <i class="fa-solid fa-bolt text-sm" aria-hidden="true"></i>
                            </div>
                            <h2 class="mt-3 text-sm font-semibold leading-snug text-[var(--qs-text)]">{{ __('Launch papers without the spreadsheet circus') }}</h2>
                            <p class="mt-2 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Classes, courses, and assignments stay connected so examiners spend time on pedagogy — not chasing lists and version chaos.') }}
                            </p>
                        </div>
                        <div class="sm:border-l sm:border-qs-soft sm:pl-6 lg:pl-8">
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--qs-primary)]/[0.09] text-[var(--qs-primary)]">
                                <i class="fa-solid fa-lock text-sm" aria-hidden="true"></i>
                            </div>
                            <h2 class="mt-3 text-sm font-semibold leading-snug text-[var(--qs-text)]">{{ __('Sell integrity you can stand behind') }}</h2>
                            <p class="mt-2 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Turn on strong proctoring when you need it: verification, monitoring, fullscreen, and clear escalation — so “we did everything reasonable” is true, not aspirational.') }}
                            </p>
                        </div>
                        <div class="sm:border-l sm:border-qs-soft sm:pl-6 lg:pl-8">
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-[var(--qs-primary)]/[0.09] text-[var(--qs-primary)]">
                                <i class="fa-solid fa-chart-line text-sm" aria-hidden="true"></i>
                            </div>
                            <h2 class="mt-3 text-sm font-semibold leading-snug text-[var(--qs-text)]">{{ __('Read the room from one workspace') }}</h2>
                            <p class="mt-2 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Session signals, submissions, and follow-up live where examiners already work — fewer blind spots when you need to intervene or defend a grade.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-b border-qs-soft bg-qs-bg py-14 sm:py-16">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <div class="max-w-2xl">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--qs-primary)]">{{ __('What “strict exam conditions” actually means here') }}</h2>
                        <p class="mt-3 text-xl font-semibold tracking-tight text-[var(--qs-text)] sm:text-2xl sm:leading-snug">
                            {{ __('When your school enables proctoring, students are not left alone in a browser tab — they are under surveillance your policy defines.') }}
                        </p>
                        <p class="mt-3 text-sm leading-relaxed text-[var(--qs-muted)] sm:text-base">
                            {{ __('Every control below is optional and administrator-governed — but when it is on, it is real: fewer places to hide unauthorised help, and a cleaner story for appeals and quality assurance.') }}
                        </p>
                    </div>

                    <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:gap-5">
                        <li class="rounded-xl border border-qs-soft/90 bg-white/90 px-4 py-4 transition hover:border-[var(--qs-primary)]/25 sm:px-5 sm:py-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-qs-soft/70 text-[var(--qs-primary)]">
                                <i class="fa-solid fa-video text-sm" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-[var(--qs-text)]">{{ __('Live session monitoring') }}</h3>
                            <p class="mt-1.5 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Camera monitoring can remain active for the attempt so the exam surface is watched the way a hall invigilator would — not a one-off snapshot you forget five minutes later.') }}
                            </p>
                        </li>
                        <li class="rounded-xl border border-qs-soft/90 bg-white/90 px-4 py-4 transition hover:border-[var(--qs-primary)]/25 sm:px-5 sm:py-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-qs-soft/70 text-[var(--qs-primary)]">
                                <i class="fa-solid fa-user-check text-sm" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-[var(--qs-text)]">{{ __('Start-of-exam verification') }}</h3>
                            <p class="mt-1.5 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Require a verification capture at start so the account taking the paper matches the student who was supposed to sit — a simple guardrail with outsized deterrence.') }}
                            </p>
                        </li>
                        <li class="rounded-xl border border-qs-soft/90 bg-white/90 px-4 py-4 transition hover:border-[var(--qs-primary)]/25 sm:px-5 sm:py-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-qs-soft/70 text-[var(--qs-primary)]">
                                <i class="fa-solid fa-expand text-sm" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-[var(--qs-text)]">{{ __('Fullscreen that keeps focus on the paper') }}</h3>
                            <p class="mt-1.5 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Lock the attempt to a fullscreen surface so “just checking something” in another window stops being frictionless — the exam stays in front.') }}
                            </p>
                        </li>
                        <li class="rounded-xl border border-qs-soft/90 bg-white/90 px-4 py-4 transition hover:border-[var(--qs-primary)]/25 sm:px-5 sm:py-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-qs-soft/70 text-[var(--qs-primary)]">
                                <i class="fa-solid fa-mobile-screen-button text-sm" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-[var(--qs-text)]">{{ __('Signals that expose second-device shortcuts') }}</h3>
                            <p class="mt-1.5 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Optional misuse signals help staff spot behaviour that does not match closed-book rules — feeding a structured trail examiners can review with context, not vibes.') }}
                            </p>
                        </li>
                        <li class="rounded-xl border border-qs-soft/90 bg-white/90 px-4 py-4 transition hover:border-[var(--qs-primary)]/25 sm:px-5 sm:py-5 sm:col-span-2 lg:col-span-1">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-qs-soft/70 text-[var(--qs-primary)]">
                                <i class="fa-solid fa-scale-balanced text-sm" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-[var(--qs-text)]">{{ __('Escalation that matches your risk appetite') }}</h3>
                            <p class="mt-1.5 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Violations accumulate with cooldowns; thresholds can auto-submit or flag for human review — so you choose how hard the guardrails bite.') }}
                            </p>
                        </li>
                        <li class="rounded-xl border border-qs-soft/90 bg-white/90 px-4 py-4 transition hover:border-[var(--qs-primary)]/25 sm:px-5 sm:py-5 sm:col-span-2 lg:col-span-1">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-qs-soft/70 text-[var(--qs-primary)]">
                                <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-3 text-sm font-semibold text-[var(--qs-text)]">{{ __('Visibility for the people accountable') }}</h3>
                            <p class="mt-1.5 text-xs leading-relaxed text-[var(--qs-muted)] sm:text-sm">
                                {{ __('Examiners see session health and outcomes in one place — design, deliver, and defend grades without stitching five tools together.') }}
                            </p>
                        </li>
                    </ul>
                </div>
            </section>

            @if (count($teamMembers) > 0)
                <section class="py-14 sm:py-16">
                    <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--qs-primary)]">{{ __('Team') }}</h2>
                            <p class="mt-3 text-xl font-semibold tracking-tight text-[var(--qs-text)] sm:text-2xl">
                                {{ __('Built by people who ship, support, and own the stack') }}
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[var(--qs-muted)] sm:text-base">
                                {{ __('Architecture and product development led in-house — so the roadmap stays aligned with what schools actually run in production.') }}
                            </p>
                        </div>

                        <div class="mx-auto mt-12 grid max-w-3xl gap-10 sm:grid-cols-2 sm:gap-8 lg:max-w-4xl lg:gap-12">
                            @foreach ($teamMembers as $member)
                                @php
                                    $name = (string) ($member['name'] ?? '');
                                    $field = (string) ($member['field'] ?? '');
                                    $avatar = (string) ($member['avatar'] ?? '');
                                @endphp
                                @continue($name === '' || $avatar === '')
                                <article class="text-center">
                                    <div class="mx-auto aspect-square max-w-[240px] overflow-hidden rounded-xl border border-qs-soft bg-white sm:max-w-none">
                                        <img
                                            src="{{ asset($avatar) }}"
                                            alt="{{ $name }}"
                                            width="640"
                                            height="640"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    </div>
                                    <h3 class="mt-4 text-sm font-semibold text-[var(--qs-text)] sm:text-base">{{ $name }}</h3>
                                    <p class="mt-1 text-xs leading-snug text-[var(--qs-muted)] sm:text-sm">{{ $field }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            <section class="mx-auto max-w-6xl px-5 pb-14 sm:px-8 sm:pb-16 lg:px-8">
                <div class="rounded-xl border border-qs-soft bg-white px-5 py-8 text-center sm:px-8 sm:py-10">
                    <h2 class="text-base font-semibold text-[var(--qs-text)] sm:text-lg">{{ __('Start with the access your school gave you') }}</h2>
                    <p class="mx-auto mt-2 max-w-md text-xs text-[var(--qs-muted)] sm:text-sm">
                        {{ __('Students: sign in with the index number and credentials your coordinator registered. That is the front door to formal exams and practice where your institution enables it.') }}
                    </p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-6 py-2.5 text-sm font-semibold">
                            {{ __('Student login') }}
                        </a>
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
