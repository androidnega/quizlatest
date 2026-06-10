<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth [scroll-padding-top:6rem]">
<head>
    <meta charset="utf-8">
    @include('layouts.partials.viewport')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('QuizSnap helps schools run serious digital exams: structured sessions, optional proctoring, and clear results — so you scale assessments without scaling chaos.') }}">
    <title>{{ __('About us') }} — {{ config('app.name', 'QuizSnap') }}</title>
    @include('layouts.partials.favicon')
    @include('marketing.partials.cdn-head')
</head>
<body class="min-h-screen bg-white font-sans text-qs-text antialiased">
    <div class="flex min-h-screen flex-col">
        {{-- =============== Sticky marketing nav (vanilla Tailwind) =============== --}}
        <header class="sticky top-0 z-50 border-b border-qs-soft/70 bg-white/85 backdrop-blur supports-[backdrop-filter]:bg-white/70">
            <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6 sm:py-4 md:px-8">
                <a href="{{ route('home') }}" class="inline-flex items-baseline text-lg font-bold tracking-tight sm:text-xl" aria-label="{{ config('app.name', 'QuizSnap') }} home">
                    <span class="text-qs-primary">Quiz</span><span class="text-qs-text">Snap</span>
                </a>

                <nav class="hidden flex-1 items-center justify-center gap-1 md:flex" aria-label="Primary">
                    <a href="{{ route('home') }}" class="inline-flex min-h-[40px] items-center rounded-lg px-3 py-2 text-sm font-medium text-qs-muted transition hover:bg-qs-soft/60 hover:text-qs-text">{{ __('Home') }}</a>
                    <a href="{{ route('about') }}" aria-current="page" class="inline-flex min-h-[40px] items-center rounded-lg bg-qs-primary/10 px-3 py-2 text-sm font-semibold text-qs-text">{{ __('About') }}</a>
                    <a href="#why" class="inline-flex min-h-[40px] items-center rounded-lg px-3 py-2 text-sm font-medium text-qs-muted transition hover:bg-qs-soft/60 hover:text-qs-text">{{ __('Why us') }}</a>
                    <a href="#proctoring" class="inline-flex min-h-[40px] items-center rounded-lg px-3 py-2 text-sm font-medium text-qs-muted transition hover:bg-qs-soft/60 hover:text-qs-text">{{ __('Proctoring') }}</a>
                    <a href="#team" class="inline-flex min-h-[40px] items-center rounded-lg px-3 py-2 text-sm font-medium text-qs-muted transition hover:bg-qs-soft/60 hover:text-qs-text">{{ __('Team') }}</a>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-text px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1a2a2e]">
                            <i class="fa-solid fa-gauge-high text-[0.72rem]" aria-hidden="true"></i>
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="hidden min-h-[40px] items-center rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm font-semibold text-qs-text transition hover:border-qs-primary hover:text-qs-primary sm:inline-flex">{{ __('Sign in') }}</a>
                        <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-text px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1a2a2e]">
                            {{ __('Student portal') }}
                            <i class="fa-solid fa-arrow-right text-[0.72rem]" aria-hidden="true"></i>
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="flex-1">
            {{-- =============== HERO =============== --}}
            <section class="relative overflow-hidden bg-[#0f1719] text-white">
                {{-- Soft brand wash — single radial gradient, vanilla Tailwind arbitrary value --}}
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(120%_80%_at_80%_0%,rgba(86,174,187,0.18)_0%,rgba(86,174,187,0)_55%),radial-gradient(80%_60%_at_0%_100%,rgba(228,111,46,0.10)_0%,rgba(228,111,46,0)_60%)]" aria-hidden="true"></div>
                {{-- Faint grid texture --}}
                <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.045)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.045)_1px,transparent_1px)] bg-[size:48px_48px] [mask-image:radial-gradient(60%_70%_at_50%_30%,#000_30%,transparent_80%)]" aria-hidden="true"></div>

                {{--
                  Hero rhythm (matches the design spec):
                    Navbar  ↓  pt-24 / sm:pt-28 / lg:pt-32   (96 / 112 / 128px)
                    Heading ↓  mt-6 / sm:mt-8                 (24 / 32px)
                    Paragraph ↓ mt-8 / sm:mt-10                (32 / 40px)
                    Buttons ↓  mt-20 / sm:mt-24                (80 / 96px)
                --}}
                <div class="relative mx-auto max-w-6xl px-5 pt-24 pb-16 sm:px-8 sm:pt-28 sm:pb-20 lg:px-8 lg:pt-32 lg:pb-24">
                    <div class="mx-auto max-w-3xl text-center">
                        <h1 class="text-balance text-4xl font-semibold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl">
                            {{ __('The exam platform schools use when results have to count.') }}
                        </h1>
                        <p class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-relaxed text-white/70 sm:mt-8 sm:text-lg">
                            {{ __('From cohort setup to final marks, QuizSnap keeps everyone on the same rails — coordinators organise, examiners deliver, students attempt in a controlled environment, and integrity options that scale with how high the stakes are.') }}
                        </p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-3 sm:mt-10">
                            <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-qs-primary-deep">
                                <i class="fa-solid fa-arrow-right-to-bracket text-xs" aria-hidden="true"></i>
                                {{ __('Student login') }}
                            </a>
                            <a href="#team" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-white/15 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white/85 transition hover:border-white/30 hover:bg-white/10">
                                <i class="fa-solid fa-users text-xs" aria-hidden="true"></i>
                                {{ __('Meet the team') }}
                            </a>
                        </div>
                    </div>

                    {{-- Stats: vanilla Tailwind grid with divide utilities replacing the old border tricks --}}
                    <div class="mt-20 grid grid-cols-3 divide-x divide-white/10 border-y border-white/10 sm:mt-24">
                        <div class="px-4 py-5 text-center sm:py-6">
                            <div class="text-2xl font-bold leading-none tracking-tight text-white sm:text-4xl">100%</div>
                            <div class="mt-1.5 text-[0.65rem] uppercase tracking-[0.18em] text-white/55 sm:text-xs">{{ __('Built in-house') }}</div>
                        </div>
                        <div class="px-4 py-5 text-center sm:py-6">
                            <div class="text-2xl font-bold leading-none tracking-tight text-white sm:text-4xl">3</div>
                            <div class="mt-1.5 text-[0.65rem] uppercase tracking-[0.18em] text-white/55 sm:text-xs">{{ __('Roles, one stack') }}</div>
                        </div>
                        <div class="px-4 py-5 text-center sm:py-6">
                            <div class="text-2xl font-bold leading-none tracking-tight text-white sm:text-4xl">∞</div>
                            <div class="mt-1.5 text-[0.65rem] uppercase tracking-[0.18em] text-white/55 sm:text-xs">{{ __('Cohorts supported') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- =============== WHY INSTITUTIONS PICK QUIZSNAP =============== --}}
            <section id="why" class="border-b border-qs-soft bg-white py-16 sm:py-20">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <div class="max-w-2xl">
                        <span class="inline-flex items-center gap-2 text-[0.7rem] font-bold uppercase tracking-[0.22em] text-qs-primary">
                            <span class="inline-block h-px w-6 bg-current opacity-60"></span>
                            {{ __('Why institutions pick QuizSnap') }}
                        </span>
                        <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-qs-text sm:text-3xl lg:text-[2.25rem]">
                            {{ __('Three problems schools keep paying for. We built one platform that owns all three.') }}
                        </h2>
                    </div>

                    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                        <article class="group rounded-2xl border border-qs-soft bg-white p-6 transition hover:-translate-y-0.5 hover:border-qs-primary/40 hover:shadow-qs-card">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary">
                                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-4 text-base font-semibold tracking-tight text-qs-text">{{ __('Launch papers without the spreadsheet circus') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-qs-muted">
                                {{ __('Classes, courses, and assignments stay connected so examiners spend their time on pedagogy — not chasing lists or fighting version chaos.') }}
                            </p>
                        </article>
                        <article class="group rounded-2xl border border-qs-soft bg-white p-6 transition hover:-translate-y-0.5 hover:border-qs-primary/40 hover:shadow-qs-card">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary">
                                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-4 text-base font-semibold tracking-tight text-qs-text">{{ __('Sell integrity you can stand behind') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-qs-muted">
                                {{ __('Turn on strong proctoring when you need it: verification, monitoring, fullscreen, escalation — so “we did everything reasonable” is an audit-ready statement, not a hope.') }}
                            </p>
                        </article>
                        <article class="group rounded-2xl border border-qs-soft bg-white p-6 transition hover:-translate-y-0.5 hover:border-qs-primary/40 hover:shadow-qs-card">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-qs-primary/10 text-qs-primary">
                                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                            </div>
                            <h3 class="mt-4 text-base font-semibold tracking-tight text-qs-text">{{ __('Read the room from one workspace') }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-qs-muted">
                                {{ __('Session signals, submissions, and follow-up live where examiners already work — fewer blind spots when you need to intervene or defend a grade.') }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            {{-- =============== STRICT EXAM CONDITIONS =============== --}}
            <section id="proctoring" class="border-b border-qs-soft bg-qs-bg py-16 sm:py-20">
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] lg:gap-14">
                        <div>
                            <span class="inline-flex items-center gap-2 text-[0.7rem] font-bold uppercase tracking-[0.22em] text-qs-primary">
                                <span class="inline-block h-px w-6 bg-current opacity-60"></span>
                                {{ __('Strict exam conditions, defined') }}
                            </span>
                            <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-qs-text sm:text-3xl lg:text-[2.1rem]">
                                {{ __('When proctoring is on, students aren\'t alone in a tab — they\'re under the surveillance your policy defines.') }}
                            </h2>
                            <p class="mt-4 text-base leading-relaxed text-qs-muted">
                                {{ __('Every control is optional and administrator-governed. But when it\'s on, it\'s real: fewer places to hide unauthorised help, and a cleaner story for appeals and quality assurance.') }}
                            </p>
                        </div>

                        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                ['icon' => 'fa-video',                'title' => __('Live session monitoring'),               'body' => __('Camera stays active for the attempt — like a hall invigilator, not a one-off snapshot.')],
                                ['icon' => 'fa-user-check',           'title' => __('Start-of-exam verification'),            'body' => __('Verification capture at start so the account taking the paper matches the student who was supposed to sit.')],
                                ['icon' => 'fa-expand',               'title' => __('Fullscreen focus lock'),                  'body' => __('Lock the attempt to a fullscreen surface so “just checking another window” stops being frictionless.')],
                                ['icon' => 'fa-mobile-screen-button', 'title' => __('Second-device signals'),                  'body' => __('Optional misuse signals feed a structured trail examiners can review with context — not vibes.')],
                                ['icon' => 'fa-scale-balanced',       'title' => __('Escalation that fits your risk appetite'),'body' => __('Violations accumulate with cooldowns; thresholds can auto-submit or flag for human review.')],
                                ['icon' => 'fa-eye',                  'title' => __('Visibility for the people accountable'),  'body' => __('Examiners see session health and outcomes in one place — design, deliver, and defend grades.')],
                            ] as $control)
                                <li class="flex items-start gap-3 rounded-xl border border-qs-soft bg-white/95 p-5 transition hover:border-qs-primary/40 hover:bg-white">
                                    <span class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-qs-primary/10 text-qs-primary">
                                        <i class="fa-solid {{ $control['icon'] }} text-sm" aria-hidden="true"></i>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-qs-text">{{ $control['title'] }}</h3>
                                        <p class="mt-1 text-xs leading-relaxed text-qs-muted">{{ $control['body'] }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>

            {{-- =============== TEAM =============== --}}
            @if (count($teamMembers) > 0)
                <section id="team" class="bg-white py-16 sm:py-20">
                    <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-8">
                        <div class="mx-auto max-w-2xl text-center">
                            <span class="inline-flex items-center justify-center gap-2 text-[0.7rem] font-bold uppercase tracking-[0.22em] text-qs-primary">
                                <span class="inline-block h-px w-6 bg-current opacity-60"></span>
                                {{ __('The team') }}
                                <span class="inline-block h-px w-6 bg-current opacity-60"></span>
                            </span>
                            <h2 class="mt-4 text-2xl font-semibold leading-tight tracking-tight text-qs-text sm:text-3xl lg:text-[2.25rem]">
                                {{ __('Built by people who ship, support, and own the stack.') }}
                            </h2>
                            <p class="mt-4 text-base leading-relaxed text-qs-muted">
                                {{ __('Architecture and product development led in-house — so the roadmap stays aligned with what schools actually run in production.') }}
                            </p>
                        </div>

                        @php
                            // Compact max widths so portrait cards stay tasteful, not oversized.
                            $teamCount = count($teamMembers);
                            $teamGrid  = match (true) {
                                $teamCount === 1 => 'mx-auto mt-12 grid max-w-[260px] gap-6',
                                $teamCount === 2 => 'mx-auto mt-12 grid max-w-2xl gap-6 sm:grid-cols-2 sm:gap-7',
                                default          => 'mx-auto mt-12 grid max-w-4xl gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-7',
                            };
                        @endphp

                        <div class="{{ $teamGrid }}">
                            @foreach ($teamMembers as $index => $member)
                                @php
                                    $name   = (string) ($member['name'] ?? '');
                                    $field  = (string) ($member['field'] ?? '');
                                    $avatar = (string) ($member['avatar'] ?? '');
                                    $badge  = $index === 0 ? __('Founder') : null;
                                    // Cache-bust portraits whenever the source file changes (.htaccess
                                    // serves /images with a 1-year cache, so a stable URL would pin the
                                    // old photo in browsers indefinitely after a swap).
                                    $avatarPath = public_path($avatar);
                                    $avatarVer  = (is_file($avatarPath) ? filemtime($avatarPath) : null) ?: substr(md5($avatar), 0, 8);
                                    $avatarUrl  = asset($avatar) . '?v=' . $avatarVer;
                                @endphp
                                @continue($name === '' || $avatar === '')
                                <article class="group relative overflow-hidden rounded-2xl border border-qs-soft bg-white shadow-qs-card transition hover:-translate-y-1 hover:border-qs-primary/50 hover:shadow-qs-card-hover">
                                    <div class="relative aspect-[4/5] overflow-hidden bg-gradient-to-b from-[#eaf3f5] to-[#d5e7ea]">
                                        @if ($badge)
                                            <span class="absolute left-3 top-3 z-10 inline-flex items-center gap-1.5 rounded-full bg-black/55 px-2.5 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.18em] text-white backdrop-blur">
                                                <i class="fa-solid fa-star text-[0.6rem] text-amber-300" aria-hidden="true"></i>
                                                {{ $badge }}
                                            </span>
                                        @endif
                                        <img
                                            src="{{ $avatarUrl }}"
                                            alt="{{ $name }}"
                                            width="900"
                                            height="1125"
                                            loading="lazy"
                                            decoding="async"
                                            class="h-full w-full object-cover object-[center_18%] transition duration-500 group-hover:scale-105"
                                        />
                                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-black/45"></div>
                                    </div>
                                    <div class="px-5 py-4">
                                        <h3 class="font-brand text-xl font-semibold leading-tight tracking-[0.005em] text-qs-text sm:text-[1.35rem]">{{ $name }}</h3>
                                        <p class="mt-1.5 inline-flex items-center gap-2 text-[0.66rem] font-semibold uppercase tracking-[0.14em] text-qs-primary">
                                            <span class="inline-block h-px w-4 bg-current opacity-60"></span>
                                            {{ $field }}
                                        </p>
                                        <div class="mt-3 inline-flex items-center gap-2 text-[0.7rem] text-qs-muted">
                                            <span class="relative inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 ring-4 ring-emerald-500/20" aria-hidden="true"></span>
                                            <span>{{ __('Available for institution support') }}</span>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            {{-- =============== CLOSING CTA =============== --}}
            <section class="bg-qs-bg py-16 sm:py-20">
                <div class="mx-auto max-w-4xl px-5 sm:px-8 lg:px-8">
                    <div class="relative overflow-hidden rounded-2xl border border-qs-soft bg-gradient-to-br from-white via-white to-[#eaf3f5] px-6 py-10 text-center shadow-sm sm:px-10 sm:py-12">
                        <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-qs-primary/10" aria-hidden="true"></div>
                        <div class="pointer-events-none absolute -bottom-16 -left-16 h-52 w-52 rounded-full bg-qs-danger/5" aria-hidden="true"></div>
                        <div class="relative">
                            <h2 class="text-2xl font-semibold tracking-tight text-qs-text sm:text-3xl">{{ __('Start with the access your school gave you') }}</h2>
                            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-qs-muted sm:text-base">
                                {{ __('Students sign in with the index number and credentials your coordinator registered — that\'s the front door to formal exams and any practice your institution enables.') }}
                            </p>
                            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                                <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-qs-primary px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-qs-primary-deep">
                                    {{ __('Student login') }}
                                </a>
                                <a href="{{ route('home') }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-qs-soft bg-white px-5 py-2.5 text-sm font-semibold text-qs-text transition hover:border-qs-primary/40 hover:text-qs-primary">
                                    {{ __('Back to home') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-qs-soft bg-white py-10 text-center">
            <p class="text-sm font-semibold text-qs-text">{{ config('app.name', 'QuizSnap') }}</p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Digital quizzes and exams for schools') }} · © {{ date('Y') }}</p>
        </footer>
    </div>
</body>
</html>
