<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('Secure digital quizzes and exams for schools — verified students, structured assessments, and trusted results.') }}">
    <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Secure digital quizzes and exams') }}</title>
    @include('layouts.partials.favicon')
    @include('marketing.partials.cdn-head')
</head>
{{--
    The homepage is intentionally a single-screen experience: header
    + Podium-style hero fill ~100vh on desktop. No "How it works",
    no "One platform" grid, no closing CTA, no footer beneath. The
    body is min-h-screen + flex column so <main> grows to consume
    whatever vertical space the header leaves behind, regardless of
    viewport height or zoom.
--}}
<body class="flex min-h-screen flex-col bg-white font-sans text-qs-text antialiased">

    @php
        $branding = app(\App\Services\BrandingImagesService::class);
        $heroImageUrl = $branding->homepageHeroUrl();
        $heroShowDesktop = $branding->homepageHeroShowsOnDesktop();
        $heroShowMobile = $branding->homepageHeroShowsOnMobile();
    @endphp

    {{--
      =============== Sleek navigation header ===============
        • 3-column grid (brand · centered nav · actions) so the
          center nav is truly centered regardless of side widths.
        • Sentence-case, medium-weight links with an animated
          underline on hover; the active page gets a static
          hairline underline (Stripe / Linear pattern).
        • Primary CTA is a dark pill with an arrow that translates
          on hover.
        • Hairline border (qs-text/[0.06]) instead of qs-soft so
          the divider barely registers against the cream.
        • No shadows anywhere — consistent with the flat editorial
          treatment of the hero photo below.
    --}}
    <header class="z-50 shrink-0 border-b border-qs-text/[0.06] bg-white">
        <div class="mx-auto grid h-16 max-w-7xl grid-cols-[auto_1fr_auto] items-center gap-4 px-5 sm:h-[68px] sm:px-6 md:gap-8 md:px-10">

            {{-- Brand: small teal chip + 2-tone wordmark, with a tiny rotation on hover --}}
            <a href="{{ url('/') }}" class="group inline-flex items-center gap-2.5" aria-label="{{ config('app.name', 'QuizSnap') }} home">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-qs-primary text-white transition duration-300 ease-out group-hover:-rotate-[6deg] group-hover:scale-105">
                    <i class="fa-solid fa-graduation-cap text-[0.78rem]" aria-hidden="true"></i>
                </span>
                <span class="text-base font-semibold tracking-tight leading-none md:text-[1.05rem]">
                    <span class="text-qs-primary">Quiz</span><span class="text-qs-text">Snap</span>
                </span>
            </a>

            {{-- Centered nav (md+ only). Active link has a static underline; siblings get an animated underline on hover. --}}
            <nav class="hidden items-center justify-center gap-1 md:flex" aria-label="Primary">
                <a href="{{ url('/') }}" aria-current="page"
                   class="relative inline-flex h-9 items-center rounded-full px-3.5 text-sm font-medium text-qs-text">
                    {{ __('Home') }}
                    <span class="absolute inset-x-3.5 -bottom-px h-px bg-qs-primary" aria-hidden="true"></span>
                </a>
                <a href="{{ route('about') }}"
                   class="group relative inline-flex h-9 items-center rounded-full px-3.5 text-sm font-medium text-qs-muted transition-colors duration-200 hover:text-qs-text">
                    {{ __('About') }}
                    <span class="absolute inset-x-3.5 -bottom-px h-px origin-center scale-x-0 bg-qs-primary transition-transform duration-300 ease-out group-hover:scale-x-100" aria-hidden="true"></span>
                </a>
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="group relative inline-flex h-9 items-center rounded-full px-3.5 text-sm font-medium text-qs-muted transition-colors duration-200 hover:text-qs-text">
                        {{ __('Dashboard') }}
                        <span class="absolute inset-x-3.5 -bottom-px h-px origin-center scale-x-0 bg-qs-primary transition-transform duration-300 ease-out group-hover:scale-x-100" aria-hidden="true"></span>
                    </a>
                @endauth
            </nav>

            {{-- Right actions: secondary text link (sm+) + primary pill CTA --}}
            <div class="flex items-center gap-2 sm:gap-4">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="group inline-flex items-center gap-1.5 rounded-full bg-qs-text px-4 py-2 text-sm font-semibold text-white transition-colors duration-200 hover:bg-[#1a2a2e] md:px-5 md:py-2.5">
                        {{ __('Dashboard') }}
                        <i class="fa-solid fa-arrow-right text-[0.7rem] transition-transform duration-300 ease-out group-hover:translate-x-0.5" aria-hidden="true"></i>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="group inline-flex items-center gap-1.5 rounded-full bg-qs-text px-4 py-2 text-sm font-semibold text-white transition-colors duration-200 hover:bg-[#1a2a2e] md:px-5 md:py-2.5">
                        {{ __('Student login') }}
                        <i class="fa-solid fa-arrow-right text-[0.7rem] transition-transform duration-300 ease-out group-hover:translate-x-0.5" aria-hidden="true"></i>
                    </a>
                @endauth
            </div>

        </div>
    </header>

    {{-- =============== Hero (consumes the rest of the viewport) =============== --}}
    {{-- Main inherits the cream parchment background from <body>; no explicit
         bg class so the whole page reads as one continuous warm surface. --}}
    <main class="flex flex-1">

        {{-- Mobile hero: centered single column, fills remaining height --}}
        <section class="flex w-full flex-col items-center justify-center px-5 py-10 text-center md:hidden">
            @if ($heroShowMobile)
                {{-- Mobile photo: flat editorial frame.
                     • Solid brand block peeks from behind at top-right.
                     • Outline brand frame peeks from behind at bottom-left.
                     • Photo has a crisp 1px ring instead of a shadow.
                     No drop shadows, no glow blurs. --}}
                <figure
                    data-home-hero-mobile-photo="1"
                    class="relative mb-7 w-full max-w-sm"
                >
                    <div class="pointer-events-none absolute -right-3 -top-3 -z-10 h-20 w-20 rounded-[22px] bg-qs-primary" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -bottom-3 -left-3 -z-10 h-16 w-16 rounded-[22px] border-2 border-qs-primary/40" aria-hidden="true"></div>

                    <div class="relative overflow-hidden rounded-[22px] ring-1 ring-qs-text/10">
                        <img
                            src="{{ $heroImageUrl }}"
                            alt=""
                            width="1024"
                            height="768"
                            decoding="async"
                            fetchpriority="high"
                            sizes="100vw"
                            class="block aspect-[4/3] h-auto w-full object-cover object-center"
                        />
                    </div>
                </figure>
            @endif
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
            <div class="mt-8 flex w-full max-w-xs flex-col gap-2.5">
                <a href="{{ route('login') }}" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-md bg-qs-primary px-6 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-white shadow-lg shadow-qs-primary/30 transition hover:bg-qs-primary-deep">
                    {{ __('Student login') }}
                </a>
                <a href="{{ route('about') }}" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-md border border-qs-soft bg-white px-6 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-qs-text transition hover:border-qs-primary hover:text-qs-primary">
                    {{ __('About us') }}
                </a>
            </div>
        </section>

        @if ($heroShowDesktop)
            {{-- Desktop hero: 2-column split, vertically centered, fills remaining height --}}
            <section class="mx-auto hidden w-full max-w-7xl md:grid md:grid-cols-2 md:items-center md:gap-12 md:px-10 md:py-10 lg:gap-16 lg:py-12">

                {{-- Left column: text + CTAs --}}
                <div class="min-w-0 max-w-xl md:justify-self-end">
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
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3 sm:gap-4">
                        <a href="{{ route('login') }}" class="inline-flex min-h-[52px] items-center justify-center rounded-md bg-qs-primary px-7 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-white shadow-lg shadow-qs-primary/30 ring-1 ring-qs-primary/30 transition hover:bg-qs-primary-deep hover:shadow-xl hover:shadow-qs-primary/35">
                            {{ __('Student login') }}
                        </a>
                        <a href="{{ route('about') }}" class="inline-flex min-h-[52px] items-center justify-center rounded-md border border-qs-soft bg-white px-7 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-qs-text shadow-sm transition hover:border-qs-primary hover:text-qs-primary">
                            {{ __('About us') }}
                        </a>
                    </div>
                </div>

                {{--
                  Right column: flat editorial photo treatment.
                    • Solid teal accent block peeks from behind at top-right.
                    • Outline teal frame peeks from behind at bottom-left.
                    • Photo has a crisp 1px ring instead of any drop shadow.
                    • A single FLAT "Live exams" tab on the top-left of the
                      photo (no glass, no shadow, no backdrop-blur).
                    • Caption sits BELOW the photo as a magazine-style
                      hairline-prefixed line — no overlay panel.
                  No drop shadows on the card, no glow blurs in the back,
                  no glassmorphic panels. The accent blocks do the depth.
                --}}
                <div
                    data-online-quiz-hero="1"
                    class="group relative w-full min-w-0 max-w-xl md:justify-self-start lg:max-w-2xl"
                >
                    <p class="sr-only">
                        {{ __('QuizSnap promotional illustration: a student on a laptop in a teal chair beside a phone showing secure digital quizzes and exams for schools.') }}
                    </p>

                    {{-- Bauhaus accent block, top-right, peeks behind the photo --}}
                    <div class="pointer-events-none absolute -right-4 -top-4 -z-10 h-32 w-32 rounded-[28px] bg-qs-primary sm:-right-6 sm:-top-6 sm:h-40 sm:w-40 lg:h-48 lg:w-48" aria-hidden="true"></div>
                    {{-- Outline frame, bottom-left, balances the composition --}}
                    <div class="pointer-events-none absolute -bottom-4 -left-4 -z-10 h-24 w-24 rounded-[28px] border-[2.5px] border-qs-primary/45 sm:-bottom-6 sm:-left-6 sm:h-32 sm:w-32 lg:h-40 lg:w-40" aria-hidden="true"></div>

                    {{-- Photo: clean rounded corners, crisp 1px ring, no shadow --}}
                    <div class="relative overflow-hidden rounded-[28px] ring-1 ring-qs-text/10">
                        <img
                            src="{{ $heroImageUrl }}"
                            alt=""
                            width="1024"
                            height="768"
                            decoding="async"
                            fetchpriority="high"
                            class="aspect-[4/3] h-auto w-full object-cover object-center transition duration-700 ease-out group-hover:scale-[1.02]"
                        />

                        {{-- Single flat "Live exams" tab — no shadow, no blur --}}
                        <span class="absolute left-5 top-5 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-[0.62rem] font-bold uppercase tracking-[0.18em] text-qs-text ring-1 ring-qs-text/10">
                            <span class="relative inline-flex h-1.5 w-1.5 items-center justify-center">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-50" aria-hidden="true"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            {{ __('Live exams') }}
                        </span>
                    </div>

                    {{-- Magazine-style caption BELOW the photo, no panel --}}
                    <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-block h-px w-8 bg-qs-primary"></span>
                        <span class="text-[0.7rem] font-bold uppercase tracking-[0.18em] text-qs-text">
                            {{ __('Trusted Results') }}
                        </span>
                        <span class="text-sm leading-snug text-qs-muted">
                            {{ __('Marks released by your examiner — same place every term.') }}
                        </span>
                    </div>
                </div>

            </section>
        @else
            {{-- Desktop hero (text-only fallback): admin hid the photo on desktop. --}}
            <section class="mx-auto hidden w-full max-w-3xl flex-col items-center justify-center px-10 py-10 text-center md:flex md:py-12 lg:py-16">
                <span class="inline-flex items-center gap-2 rounded-full bg-qs-primary/10 px-3 py-1.5 text-[0.66rem] font-bold uppercase tracking-[0.18em] text-qs-primary ring-1 ring-qs-primary/15">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-qs-primary"></span>
                    {{ __('Built for schools') }}
                </span>

                <h1 class="mt-6 text-balance text-5xl font-bold leading-[1.05] tracking-tight text-qs-text sm:text-6xl lg:text-[4.25rem] lg:leading-[1.04]">
                    <span class="block">{{ __('Secure Digital') }}</span>
                    <span class="block text-qs-primary">{{ __('Exams. Perfected.') }}</span>
                </h1>

                <p class="mx-auto mt-6 max-w-md text-base leading-relaxed text-qs-muted sm:text-lg">
                    {{ __('Verified students. Smart assessments. Trusted results.') }}
                </p>

                <div class="mt-9 flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                    <a href="{{ route('login') }}" class="inline-flex min-h-[52px] items-center justify-center rounded-md bg-qs-primary px-7 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-white shadow-lg shadow-qs-primary/30 ring-1 ring-qs-primary/30 transition hover:bg-qs-primary-deep hover:shadow-xl hover:shadow-qs-primary/35">
                        {{ __('Student login') }}
                    </a>
                    <a href="{{ route('about') }}" class="inline-flex min-h-[52px] items-center justify-center rounded-md border border-qs-soft bg-white px-7 py-3 text-[0.72rem] font-bold uppercase tracking-[0.18em] text-qs-text shadow-sm transition hover:border-qs-primary hover:text-qs-primary">
                        {{ __('About us') }}
                    </a>
                </div>
            </section>
        @endif

    </main>
</body>
</html>
