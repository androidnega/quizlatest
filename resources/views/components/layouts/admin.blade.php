<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Admin') }}</title>
        @include('layouts.partials.favicon')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="overflow-x-hidden font-sans antialiased bg-qs-bg text-qs-text">
        <div
            x-data="{ staffNavOpen: false }"
            @keydown.escape.window="staffNavOpen = false"
            class="flex min-h-screen w-full max-w-full flex-col bg-qs-bg md:flex-row"
        >
            {{-- Mobile backdrop --}}
            <div
                x-show="staffNavOpen"
                x-transition.opacity
                x-cloak
                class="fixed inset-0 z-40 bg-qs-text/40 md:hidden"
                @click="staffNavOpen = false"
                aria-hidden="true"
            ></div>

            {{-- Mobile drawer --}}
            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-[min(22rem,calc(100vw-2rem))] max-w-full flex-col border-r border-qs-soft bg-qs-bg shadow-lg transition-transform duration-200 ease-out md:hidden"
                :class="staffNavOpen ? 'translate-x-0' : '-translate-x-full'"
                id="admin-mobile-nav"
                aria-label="{{ __('Admin navigation') }}"
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
                    <div class="flex flex-wrap items-baseline gap-2">
                        <x-brand-logo class="text-lg" />
                        <span class="text-sm font-semibold text-qs-muted">{{ __('Admin') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-qs-muted">{{ auth()->user()->name }}</p>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                    <a href="{{ route('admin.dashboard') }}" @click="staffNavOpen = false" class="{{ request()->routeIs('admin.dashboard') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Dashboard') }}</a>
                    <a href="{{ route('admin.universities.index') }}" @click="staffNavOpen = false" class="{{ request()->routeIs('admin.universities.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Universities') }}</a>
                    <a href="{{ route('admin.academic-years.index') }}" @click="staffNavOpen = false" class="{{ request()->routeIs('admin.academic-years.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Academic years') }}</a>
                    <a href="{{ route('admin.coordinators.index') }}" @click="staffNavOpen = false" class="{{ request()->routeIs('admin.coordinators.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Coordinators') }}</a>
                    <a href="{{ route('admin.settings.index') }}" @click="staffNavOpen = false" class="{{ request()->routeIs('admin.settings.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Settings') }}</a>
                    <a href="{{ route('admin.academic-reset-snapshots.index') }}" @click="staffNavOpen = false" class="{{ request()->routeIs('admin.academic-reset-snapshots.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-4 py-3 text-sm font-medium">{{ __('Academic resets') }}</a>
                </nav>
            </aside>

            {{-- Desktop sidebar --}}
            <aside class="hidden min-h-screen w-64 shrink-0 flex-col border-r border-qs-soft bg-qs-bg md:flex">
                <div class="border-b border-qs-soft px-6 py-5">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <x-brand-logo class="text-lg" />
                        <span class="text-sm font-semibold text-qs-muted">{{ __('Admin') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-qs-muted">{{ auth()->user()->name }}</p>
                </div>
                <nav class="flex-1 space-y-1 px-4 py-4">
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium">{{ __('Dashboard') }}</a>
                    <a href="{{ route('admin.universities.index') }}" class="{{ request()->routeIs('admin.universities.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium">{{ __('Universities') }}</a>
                    <a href="{{ route('admin.academic-years.index') }}" class="{{ request()->routeIs('admin.academic-years.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium">{{ __('Academic years') }}</a>
                    <a href="{{ route('admin.coordinators.index') }}" class="{{ request()->routeIs('admin.coordinators.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium">{{ __('Coordinators') }}</a>
                    <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium">{{ __('Settings') }}</a>
                    <a href="{{ route('admin.academic-reset-snapshots.index') }}" class="{{ request()->routeIs('admin.academic-reset-snapshots.*') ? 'bg-qs-accent text-qs-text shadow-sm' : 'text-qs-text hover:bg-qs-card' }} flex min-h-[44px] items-center rounded-lg px-3 text-sm font-medium">{{ __('Academic resets') }}</a>
                </nav>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-1 flex-col">
                {{-- Mobile top bar --}}
                <div class="sticky top-0 z-30 flex shrink-0 items-center gap-2 border-b border-qs-soft bg-qs-bg px-3 py-2 md:hidden">
                    <button
                        type="button"
                        class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-accent focus:ring-offset-2"
                        @click="staffNavOpen = true"
                        aria-label="{{ __('Open menu') }}"
                        :aria-expanded="staffNavOpen ? 'true' : 'false'"
                        aria-controls="admin-mobile-nav"
                    >
                        <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="min-w-0 flex-1 py-1">
                        <p class="truncate text-base font-semibold leading-tight text-qs-text">{{ $title ?? __('Admin Panel') }}</p>
                        @isset($subtitle)
                            <p class="truncate text-xs text-qs-muted">{{ $subtitle }}</p>
                        @endisset
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Logout') }}</button>
                    </form>
                </div>

                <header class="hidden border-b border-qs-soft bg-qs-bg md:block">
                    <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <div class="min-w-0">
                            <h2 class="text-xl font-semibold text-qs-text">{{ $title ?? __('Admin Panel') }}</h2>
                            @isset($subtitle)
                                <p class="text-sm text-qs-muted">{{ $subtitle }}</p>
                            @endisset
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ route('dashboard') }}" class="inline-flex min-h-[44px] items-center px-3 text-sm font-medium text-qs-text underline-offset-2 hover:underline">{{ __('Student View') }}</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Logout') }}</button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="mx-auto w-full min-w-0 max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
