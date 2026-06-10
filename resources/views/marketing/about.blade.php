<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('layouts.partials.viewport')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('QuizSnap helps schools run serious digital exams: structured sessions, optional proctoring, and clear results — so you scale assessments without scaling chaos.') }}">
    <title>{{ __('About us') }} — {{ config('app.name', 'QuizSnap') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Page-scoped polish for /about so the marketing layout stays untouched. */
        .qs-about-hero {
            background:
                radial-gradient(120% 80% at 80% 0%, rgba(86, 174, 187, 0.18) 0%, rgba(86, 174, 187, 0) 55%),
                radial-gradient(80% 60% at 0% 100%, rgba(228, 111, 46, 0.10) 0%, rgba(228, 111, 46, 0) 60%),
                #0f1719;
        }
        .qs-about-hero-grid {
            background-image: linear-gradient(rgba(255, 255, 255, 0.045) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255, 255, 255, 0.045) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(60% 70% at 50% 30%, #000 30%, transparent 80%);
        }
        .qs-about-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid rgba(86, 174, 187, 0.35);
            background: rgba(86, 174, 187, 0.10);
            color: #b8e7ee;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .qs-about-section-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--qs-primary);
        }
        .qs-about-section-eyebrow::before {
            content: '';
            display: inline-block;
            width: 1.4rem;
            height: 1px;
            background: currentColor;
            opacity: 0.6;
        }
        .qs-about-feature-card {
            position: relative;
            border-radius: 1rem;
            background: #fff;
            border: 1px solid var(--qs-soft);
            padding: 1.5rem;
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }
        .qs-about-feature-card:hover {
            transform: translateY(-2px);
            border-color: rgba(86, 174, 187, 0.45);
            box-shadow: 0 16px 32px -22px rgba(15, 52, 58, 0.20);
        }
        .qs-about-feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            background: rgba(86, 174, 187, 0.12);
            color: var(--qs-primary);
            font-size: 0.95rem;
        }
        .qs-about-control-list { display: grid; gap: 1rem; }
        @media (min-width: 640px) {
            .qs-about-control-list { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .qs-about-control-list { grid-template-columns: repeat(3, 1fr); }
        }
        .qs-about-control {
            position: relative;
            border-radius: 0.875rem;
            background: rgba(255,255,255,0.92);
            border: 1px solid var(--qs-soft);
            padding: 1.25rem 1.25rem 1.25rem 1.25rem;
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            transition: border-color .18s ease, background .18s ease;
        }
        .qs-about-control:hover {
            border-color: rgba(86, 174, 187, 0.4);
            background: #fff;
        }
        .qs-about-control__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem; height: 2rem;
            border-radius: 0.55rem;
            background: rgba(86, 174, 187, 0.10);
            color: var(--qs-primary);
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .qs-about-team-card {
            position: relative;
            background: #fff;
            border-radius: 1.25rem;
            overflow: hidden;
            border: 1px solid var(--qs-soft);
            box-shadow: 0 1px 0 rgba(15, 52, 58, 0.02), 0 12px 28px -24px rgba(15, 52, 58, 0.18);
            transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease;
        }
        .qs-about-team-card:hover {
            transform: translateY(-4px);
            border-color: rgba(86, 174, 187, 0.5);
            box-shadow: 0 30px 60px -32px rgba(15, 52, 58, 0.32);
        }
        .qs-about-team-card__photo {
            aspect-ratio: 4 / 5;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #eaf3f5 0%, #d5e7ea 100%);
        }
        .qs-about-team-card__photo::after {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(180deg, rgba(15, 23, 25, 0) 55%, rgba(15, 23, 25, 0.45) 100%);
            opacity: 0.85;
            transition: opacity .3s ease;
        }
        .qs-about-team-card:hover .qs-about-team-card__photo::after {
            opacity: 1;
        }
        .qs-about-team-card__photo::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
            z-index: 2;
        }
        .qs-about-team-card__photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 18%;
            display: block;
            filter: saturate(1.02) contrast(1.02);
            transition: transform .6s cubic-bezier(.2,.8,.2,1);
        }
        .qs-about-team-card:hover .qs-about-team-card__photo img {
            transform: scale(1.04);
        }
        .qs-about-team-card__badge {
            position: absolute;
            top: 0.85rem;
            left: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.32rem 0.7rem;
            border-radius: 9999px;
            background: rgba(15, 23, 25, 0.55);
            color: #fff;
            font-family: 'Antonio', ui-sans-serif, system-ui, sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 3;
        }
        .qs-about-team-card__body {
            padding: 1.35rem 1.4rem 1.5rem;
        }
        .qs-about-team-card__name {
            font-family: 'Antonio', ui-sans-serif, system-ui, 'Segoe UI', sans-serif;
            font-weight: 600;
            font-size: 1.5rem;
            line-height: 1.15;
            letter-spacing: 0.005em;
            color: var(--qs-text);
        }
        @media (min-width: 640px) {
            .qs-about-team-card__name { font-size: 1.65rem; }
        }
        .qs-about-team-card__role {
            margin-top: 0.55rem;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--qs-primary);
        }
        .qs-about-team-card__role::before {
            content: '';
            display: inline-block;
            width: 1.25rem;
            height: 1px;
            background: currentColor;
            opacity: 0.55;
        }
        .qs-about-team-card__status {
            margin-top: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.72rem;
            color: var(--qs-muted);
        }
        .qs-about-team-card__status-dot {
            position: relative;
            display: inline-block;
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 9999px;
            background: rgb(16, 185, 129);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
        }
        .qs-about-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0;
            border-top: 1px solid rgba(255,255,255,0.10);
            border-bottom: 1px solid rgba(255,255,255,0.10);
        }
        .qs-about-stats__cell {
            padding: 1.25rem 1rem;
            text-align: center;
            border-left: 1px solid rgba(255,255,255,0.08);
        }
        .qs-about-stats__cell:first-child { border-left: 0; }
        .qs-about-stats__num {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
            line-height: 1;
        }
        .qs-about-stats__label {
            margin-top: 0.4rem;
            font-size: 0.7rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.55);
        }
        @media (min-width: 640px) {
            .qs-about-stats__num { font-size: 2.25rem; }
        }
    </style>
</head>
<body class="min-h-screen bg-qs-bg font-sans text-qs-text antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-50 border-b border-qs-soft bg-white/95 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/80">
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
            {{-- HERO --}}
            <section class="qs-about-hero relative overflow-hidden text-white">
                <div class="qs-about-hero-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>
                <div class="relative mx-auto max-w-6xl px-5 pt-16 pb-12 sm:px-8 sm:pt-20 sm:pb-16 lg:px-8 lg:pt-24 lg:pb-20">
                    <div class="mx-auto max-w-3xl text-center">
                        <span class="qs-about-eyebrow">
                            <span class="h-1.5 w-1.5 rounded-full bg-[var(--qs-primary)]" aria-hidden="true"></span>
                            {{ __('About') }} {{ config('app.name', 'QuizSnap') }}
                        </span>
                        <h1 class="mt-6 text-balance text-4xl font-semibold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl">
                            {{ __('The exam platform schools use when results have to count.') }}
                        </h1>
                        <p class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-relaxed text-white/70 sm:text-lg">
                            {{ __('From cohort setup to final marks, QuizSnap keeps everyone on the same rails — coordinators organise, examiners deliver, students attempt in a controlled environment, and integrity options that scale with how high the stakes are.') }}
                        </p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-5 py-2.5 text-sm font-semibold">
                                <i class="fa-solid fa-arrow-right-to-bracket text-xs" aria-hidden="true"></i>
                                <span class="ml-1.5">{{ __('Student login') }}</span>
                            </a>
                            <a href="#team" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white/85 transition hover:border-white/30 hover:bg-white/10">
                                <i class="fa-solid fa-users text-xs" aria-hidden="true"></i>
                                {{ __('Meet the team') }}
                            </a>
                        </div>
                    </div>

                    <div class="qs-about-stats mt-14 sm:mt-16">
                        <div class="qs-about-stats__cell">
                            <div class="qs-about-stats__num">100%</div>
                            <div class="qs-about-stats__label">{{ __('Built in-house') }}</div>
                        </div>
                        <div class="qs-about-stats__cell">
                            <div class="qs-about-stats__num">3</div>
                            <div class="qs-about-stats__label">{{ __('Roles, one stack') }}</div>
                        </div>
                        <div class="qs-about-stats__cell">
                            <div class="qs-about-stats__num">∞</div>
                            <div class="qs-about-stats__label">{{ __('Cohorts supported') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- WHY INSTITUTIONS PICK QUIZSNAP --}}
            <section class="border-b border-qs-soft bg-white py-16 sm:py-20">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <div class="max-w-2xl">
                        <span class="qs-about-section-eyebrow">{{ __('Why institutions pick QuizSnap') }}</span>
                        <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-[var(--qs-text)] sm:text-3xl lg:text-[2.25rem]">
                            {{ __('Three problems schools keep paying for. We built one platform that owns all three.') }}
                        </h2>
                    </div>

                    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                        <div class="qs-about-feature-card">
                            <div class="qs-about-feature-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></div>
                            <h3 class="mt-4 text-base font-semibold tracking-tight text-[var(--qs-text)]">{{ __('Launch papers without the spreadsheet circus') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-[var(--qs-muted)]">
                                {{ __('Classes, courses, and assignments stay connected so examiners spend their time on pedagogy — not chasing lists or fighting version chaos.') }}
                            </p>
                        </div>
                        <div class="qs-about-feature-card">
                            <div class="qs-about-feature-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                            <h3 class="mt-4 text-base font-semibold tracking-tight text-[var(--qs-text)]">{{ __('Sell integrity you can stand behind') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-[var(--qs-muted)]">
                                {{ __('Turn on strong proctoring when you need it: verification, monitoring, fullscreen, escalation — so “we did everything reasonable” is an audit-ready statement, not a hope.') }}
                            </p>
                        </div>
                        <div class="qs-about-feature-card">
                            <div class="qs-about-feature-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                            <h3 class="mt-4 text-base font-semibold tracking-tight text-[var(--qs-text)]">{{ __('Read the room from one workspace') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-[var(--qs-muted)]">
                                {{ __('Session signals, submissions, and follow-up live where examiners already work — fewer blind spots when you need to intervene or defend a grade.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- WHAT STRICT EXAM CONDITIONS ACTUALLY MEAN --}}
            <section class="border-b border-qs-soft bg-qs-bg py-16 sm:py-20">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] lg:gap-14">
                        <div>
                            <span class="qs-about-section-eyebrow">{{ __('Strict exam conditions, defined') }}</span>
                            <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-[var(--qs-text)] sm:text-3xl lg:text-[2.1rem]">
                                {{ __('When proctoring is on, students aren\'t alone in a tab — they\'re under the surveillance your policy defines.') }}
                            </h2>
                            <p class="mt-4 text-base leading-relaxed text-[var(--qs-muted)]">
                                {{ __('Every control is optional and administrator-governed. But when it\'s on, it\'s real: fewer places to hide unauthorised help, and a cleaner story for appeals and quality assurance.') }}
                            </p>
                            <div class="mt-7 inline-flex items-center gap-3 rounded-xl border border-qs-soft bg-white px-4 py-3">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--qs-primary)]/[0.10] text-[var(--qs-primary)]">
                                    <i class="fa-solid fa-sliders text-sm" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-[var(--qs-text)]">{{ __('You set the dial.') }}</p>
                                    <p class="text-xs text-[var(--qs-muted)]">{{ __('Per-exam overrides on top of institution defaults.') }}</p>
                                </div>
                            </div>
                        </div>

                        <ul class="qs-about-control-list">
                            <li class="qs-about-control">
                                <span class="qs-about-control__icon"><i class="fa-solid fa-video" aria-hidden="true"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Live session monitoring') }}</h3>
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--qs-muted)]">
                                        {{ __('Camera stays active for the attempt — like a hall invigilator, not a one-off snapshot.') }}
                                    </p>
                                </div>
                            </li>
                            <li class="qs-about-control">
                                <span class="qs-about-control__icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Start-of-exam verification') }}</h3>
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--qs-muted)]">
                                        {{ __('Verification capture at start so the account taking the paper matches the student who was supposed to sit.') }}
                                    </p>
                                </div>
                            </li>
                            <li class="qs-about-control">
                                <span class="qs-about-control__icon"><i class="fa-solid fa-expand" aria-hidden="true"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Fullscreen focus lock') }}</h3>
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--qs-muted)]">
                                        {{ __('Lock the attempt to a fullscreen surface so “just checking another window” stops being frictionless.') }}
                                    </p>
                                </div>
                            </li>
                            <li class="qs-about-control">
                                <span class="qs-about-control__icon"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Second-device signals') }}</h3>
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--qs-muted)]">
                                        {{ __('Optional misuse signals feed a structured trail examiners can review with context — not vibes.') }}
                                    </p>
                                </div>
                            </li>
                            <li class="qs-about-control">
                                <span class="qs-about-control__icon"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Escalation that fits your risk appetite') }}</h3>
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--qs-muted)]">
                                        {{ __('Violations accumulate with cooldowns; thresholds can auto-submit or flag for human review.') }}
                                    </p>
                                </div>
                            </li>
                            <li class="qs-about-control">
                                <span class="qs-about-control__icon"><i class="fa-solid fa-eye" aria-hidden="true"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-[var(--qs-text)]">{{ __('Visibility for the people accountable') }}</h3>
                                    <p class="mt-1 text-xs leading-relaxed text-[var(--qs-muted)]">
                                        {{ __('Examiners see session health and outcomes in one place — design, deliver, and defend grades.') }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- TEAM --}}
            @if (count($teamMembers) > 0)
                <section id="team" class="bg-white py-16 sm:py-20">
                    <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="qs-about-section-eyebrow justify-center">{{ __('The team') }}</span>
                            <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-[var(--qs-text)] sm:text-3xl lg:text-[2.25rem]">
                                {{ __('Built by people who ship, support, and own the stack.') }}
                            </h2>
                            <p class="mt-4 text-base leading-relaxed text-[var(--qs-muted)]">
                                {{ __('Architecture and product development led in-house — so the roadmap stays aligned with what schools actually run in production.') }}
                            </p>
                        </div>

                        @php
                            // Reasonable max widths so 2 cards stay handsome and 3 still fit on lg.
                            $teamCount = count($teamMembers);
                            $teamGrid = match (true) {
                                $teamCount === 1 => 'mx-auto mt-12 grid max-w-sm gap-6',
                                $teamCount === 2 => 'mx-auto mt-12 grid max-w-3xl gap-6 sm:grid-cols-2 sm:gap-8',
                                default          => 'mx-auto mt-12 grid max-w-5xl gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8',
                            };
                        @endphp

                        <div class="{{ $teamGrid }}">
                            @foreach ($teamMembers as $index => $member)
                                @php
                                    $name   = (string) ($member['name'] ?? '');
                                    $field  = (string) ($member['field'] ?? '');
                                    $avatar = (string) ($member['avatar'] ?? '');
                                    $badge  = $index === 0 ? __('Founder') : null;
                                @endphp
                                @continue($name === '' || $avatar === '')
                                <article class="qs-about-team-card">
                                    <div class="qs-about-team-card__photo">
                                        @if ($badge)
                                            <span class="qs-about-team-card__badge">
                                                <i class="fa-solid fa-star text-[0.6rem] text-amber-300" aria-hidden="true"></i>
                                                {{ $badge }}
                                            </span>
                                        @endif
                                        <img
                                            src="{{ asset($avatar) }}"
                                            alt="{{ $name }}"
                                            width="900"
                                            height="1125"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    </div>
                                    <div class="qs-about-team-card__body">
                                        <h3 class="qs-about-team-card__name">{{ $name }}</h3>
                                        <p class="qs-about-team-card__role">{{ $field }}</p>
                                        <div class="qs-about-team-card__status">
                                            <span class="qs-about-team-card__status-dot" aria-hidden="true"></span>
                                            <span>{{ __('Available for institution support') }}</span>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- CTA --}}
            <section class="bg-qs-bg py-16 sm:py-20">
                <div class="mx-auto max-w-4xl px-5 sm:px-8 lg:px-8">
                    <div class="relative overflow-hidden rounded-2xl border border-qs-soft bg-gradient-to-br from-white via-white to-[#eaf3f5] px-6 py-10 text-center shadow-sm sm:px-10 sm:py-12">
                        <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-[var(--qs-primary)]/[0.12]" aria-hidden="true"></div>
                        <div class="pointer-events-none absolute -left-16 -bottom-16 h-52 w-52 rounded-full bg-[var(--qs-danger)]/[0.06]" aria-hidden="true"></div>
                        <div class="relative">
                            <h2 class="text-2xl font-semibold tracking-tight text-[var(--qs-text)] sm:text-3xl">{{ __('Start with the access your school gave you') }}</h2>
                            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-[var(--qs-muted)] sm:text-base">
                                {{ __('Students sign in with the index number and credentials your coordinator registered — that\'s the front door to formal exams and any practice your institution enables.') }}
                            </p>
                            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                                <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-6 py-2.5 text-sm font-semibold">
                                    {{ __('Student login') }}
                                </a>
                                <a href="{{ route('home') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-qs-soft bg-white px-5 py-2.5 text-sm font-semibold text-[var(--qs-text)] transition hover:border-[var(--qs-primary)]/40 hover:text-[var(--qs-primary)]">
                                    {{ __('Back to home') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-qs-soft bg-white py-10 text-center">
            <p class="text-sm font-semibold text-[var(--qs-text)]">{{ config('app.name', 'QuizSnap') }}</p>
            <p class="mt-1 text-xs text-[var(--qs-muted)]">{{ __('Digital quizzes and exams for schools') }} · © {{ date('Y') }}</p>
        </footer>
    </div>
</body>
</html>
