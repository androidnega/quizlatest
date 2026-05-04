<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} — {{ __('Examiner') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="overflow-x-hidden font-sans antialiased bg-qs-bg text-qs-text">
        @php
            $navOn = fn (bool $on): string => $on ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card';
            $sessionsActive = request()->routeIs('examiner.exams.sessions.*') || request()->routeIs('examiner.exam-sessions.*');
            $examsActive = request()->routeIs('examiner.exams.*') && ! $sessionsActive;
            $gradingActive = request()->routeIs('examiner.grading.*');
            $practiceActive = request()->routeIs('examiner.practice-overview.*');
        @endphp
        <div
            x-data="{ staffNavOpen: false }"
            @keydown.escape.window="staffNavOpen = false"
            class="flex min-h-screen w-full max-w-full flex-col bg-qs-bg md:flex-row"
        >
            <div
                x-show="staffNavOpen"
                x-transition.opacity
                x-cloak
                class="fixed inset-0 z-40 bg-qs-text/40 md:hidden"
                @click="staffNavOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-[min(22rem,calc(100vw-2rem))] max-w-full flex-col border-r border-qs-soft bg-qs-bg shadow-lg transition-transform duration-200 ease-out md:hidden"
                :class="staffNavOpen ? 'translate-x-0' : '-translate-x-full'"
                id="examiner-mobile-nav"
                aria-label="{{ __('Examiner navigation') }}"
            >
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-qs-soft px-4 py-3">
                    <span class="text-lg font-semibold text-qs-text">{{ __('Menu') }}</span>
                    <button
                        type="button"
                        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-accent focus:ring-offset-2"
                        @click="staffNavOpen = false"
                        aria-label="{{ __('Close menu') }}"
                    >
                        <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="border-b border-qs-soft px-4 py-4">
                    <p class="text-lg font-semibold text-qs-text">{{ __('Examiner') }}</p>
                    <p class="mt-1 text-sm text-qs-muted">{{ auth()->user()->name }}</p>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    <a href="{{ route('examiner.dashboard') }}" @click="staffNavOpen = false" class="{{ $navOn(request()->routeIs('examiner.dashboard')) }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Dashboard') }}</a>
                    <a href="{{ route('examiner.exams.index') }}" @click="staffNavOpen = false" class="{{ $navOn($examsActive) }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Exams') }}</a>
                    <a href="{{ route('examiner.exams.create') }}" @click="staffNavOpen = false" class="{{ $navOn(request()->routeIs('examiner.exams.create')) }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Exam builder') }}</a>
                    <a href="{{ route('examiner.grading.pending') }}" @click="staffNavOpen = false" class="{{ $navOn($gradingActive) }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Essay grading') }}</a>
                    <a href="{{ route('examiner.exams.index') }}" @click="staffNavOpen = false" class="{{ $navOn($sessionsActive) }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Sessions & held review') }}</a>
                    <a href="{{ route('examiner.practice-overview.index') }}" @click="staffNavOpen = false" class="{{ $navOn($practiceActive) }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Practice overview') }}</a>
                    @if (auth()->user()->role === 'coordinator')
                        <a href="{{ route('coordinator.dashboard') }}" @click="staffNavOpen = false" class="{{ $navOn(request()->routeIs('coordinator.dashboard')) }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Coordinator panel') }}</a>
                    @endif
                </nav>
            </aside>

            <aside class="hidden min-h-screen w-72 shrink-0 flex-col border-r border-qs-soft bg-qs-bg md:flex">
                <div class="border-b border-qs-soft px-6 py-6">
                    <h1 class="text-lg font-semibold text-qs-text">{{ __('Examiner panel') }}</h1>
                    <p class="mt-1 text-sm text-qs-muted">{{ auth()->user()->name }}</p>
                </div>
                <nav class="flex-1 space-y-1 px-4 py-5">
                    <a href="{{ route('examiner.dashboard') }}" class="{{ $navOn(request()->routeIs('examiner.dashboard')) }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition">{{ __('Dashboard') }}</a>
                    <a href="{{ route('examiner.exams.index') }}" class="{{ $navOn($examsActive) }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition">{{ __('Exams') }}</a>
                    <a href="{{ route('examiner.exams.create') }}" class="{{ $navOn(request()->routeIs('examiner.exams.create')) }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition">{{ __('Exam builder') }}</a>
                    <a href="{{ route('examiner.grading.pending') }}" class="{{ $navOn($gradingActive) }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition">{{ __('Essay grading') }}</a>
                    <a href="{{ route('examiner.exams.index') }}" class="{{ $navOn($sessionsActive) }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition">{{ __('Sessions & held review') }}</a>
                    <a href="{{ route('examiner.practice-overview.index') }}" class="{{ $navOn($practiceActive) }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition">{{ __('Practice overview') }}</a>
                    @if (auth()->user()->role === 'coordinator')
                        <a href="{{ route('coordinator.dashboard') }}" class="{{ $navOn(request()->routeIs('coordinator.dashboard')) }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium transition">{{ __('Coordinator panel') }}</a>
                    @endif
                </nav>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-1 flex-col">
                <div class="sticky top-0 z-30 flex shrink-0 items-center gap-2 border-b border-qs-soft bg-qs-bg px-3 py-2 md:hidden">
                    <button
                        type="button"
                        class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-accent focus:ring-offset-2"
                        @click="staffNavOpen = true"
                        aria-label="{{ __('Open menu') }}"
                        :aria-expanded="staffNavOpen ? 'true' : 'false'"
                        aria-controls="examiner-mobile-nav"
                    >
                        <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="min-w-0 flex-1 py-1">
                        <p class="truncate text-base font-semibold leading-tight text-qs-text">{{ $title ?? __('Examiner') }}</p>
                        @isset($subtitle)
                            <p class="truncate text-xs text-qs-muted">{{ $subtitle }}</p>
                        @endisset
                        @isset($staffAcademicPeriodBadge)
                            <p class="truncate text-[11px] font-medium text-qs-text">{{ __('Active period') }}: {{ $staffAcademicPeriodBadge }}</p>
                        @endisset
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Logout') }}</button>
                    </form>
                </div>

                <header class="hidden border-b border-qs-soft bg-qs-bg md:block">
                    <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6 lg:px-8">
                        <div class="min-w-0">
                            <h2 class="text-2xl font-semibold text-qs-text">{{ $title ?? __('Examiner') }}</h2>
                            @isset($subtitle)
                                <p class="text-sm text-qs-muted">{{ $subtitle }}</p>
                            @endisset
                            @isset($staffAcademicPeriodBadge)
                                <p class="mt-1 text-xs font-medium text-qs-text">{{ __('Active period') }}: {{ $staffAcademicPeriodBadge }}</p>
                            @endisset
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center px-3 text-sm font-medium text-qs-text underline-offset-2 hover:underline">{{ __('Home') }}</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Logout') }}</button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="mx-auto w-full min-w-0 max-w-7xl flex-1 px-5 py-8 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text shadow-sm">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
