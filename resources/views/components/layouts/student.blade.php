<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Student') }}</title>
        @include('layouts.partials.favicon')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full overflow-hidden font-sans antialiased bg-qs-bg text-qs-text">
        @php
            $dashActive = request()->routeIs('dashboard');
            $resultsActive = request()->routeIs('student.results.*');
            $practiceActive = request()->routeIs('student.practice.*');
            $profileActive = request()->routeIs('profile.*');
        @endphp
        <div
            x-data="{
                drawerOpen: false,
                collapsed: (() => {
                    try {
                        return localStorage.getItem('qs.sidebar.student') === '1';
                    } catch (e) {
                        return false;
                    }
                })(),
                toggleCollapse() {
                    this.collapsed = !this.collapsed;
                    try {
                        localStorage.setItem('qs.sidebar.student', this.collapsed ? '1' : '0');
                    } catch (e) {}
                },
            }"
            @keydown.escape.window="drawerOpen = false"
            class="qs-app-shell"
        >
            <div
                x-show="drawerOpen"
                x-transition.opacity
                x-cloak
                class="fixed inset-0 z-40 bg-qs-text/40 md:hidden"
                @click="drawerOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-[min(19rem,calc(100vw-2rem))] max-w-full flex-col border-r border-qs-soft bg-qs-bg shadow-xl transition-transform duration-200 ease-out md:hidden"
                :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
                id="student-mobile-nav"
                aria-label="{{ __('Student navigation') }}"
            >
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-qs-soft px-4 py-3">
                    <span class="text-sm font-semibold text-qs-text">{{ __('Menu') }}</span>
                    <button
                        type="button"
                        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-primary/30"
                        @click="drawerOpen = false"
                        aria-label="{{ __('Close menu') }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="border-b border-qs-soft px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ config('app.name') }}</p>
                </div>
                <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    <x-ui.sidebar-link :href="route('dashboard')" :active="$dashActive" icon="house" :close-drawer="true" always-show-label>{{ __('Dashboard') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.results.index')" :active="$resultsActive" icon="square-poll-vertical" :close-drawer="true" always-show-label>{{ __('Results') }}</x-ui.sidebar-link>
                    @if (! empty($studentPracticeNavEnabled))
                        <x-ui.sidebar-link :href="route('student.practice.index')" :active="$practiceActive" icon="clipboard-question" :close-drawer="true" always-show-label>{{ __('Practice') }}</x-ui.sidebar-link>
                    @endif
                    <x-ui.sidebar-link :href="route('profile.edit')" :active="$profileActive" icon="user" :close-drawer="true" always-show-label>{{ __('Profile') }}</x-ui.sidebar-link>
                </nav>
            </aside>

            <aside
                class="hidden shrink-0 flex-col border-r border-qs-soft bg-qs-bg transition-[width] duration-200 ease-out md:flex"
                :class="collapsed ? 'w-[4.25rem]' : 'w-56'"
                aria-label="{{ __('Student navigation') }}"
            >
                <div class="flex shrink-0 items-center gap-2 border-b border-qs-soft px-2 py-3" :class="collapsed ? 'flex-col' : 'justify-between'">
                    <div class="min-w-0 px-1" x-show="! collapsed" x-transition>
                        <p class="truncate text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ config('app.name') }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex min-h-[40px] min-w-[40px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-muted hover:bg-qs-card hover:text-qs-text focus:outline-none focus:ring-2 focus:ring-qs-primary/25"
                        @click="toggleCollapse()"
                        :title="collapsed ? '{{ __('Expand sidebar') }}' : '{{ __('Collapse sidebar') }}'"
                    >
                        <i class="fa-solid text-xs" :class="collapsed ? 'fa-angles-right' : 'fa-angles-left'" aria-hidden="true"></i>
                    </button>
                </div>
                <nav class="flex flex-1 flex-col space-y-0.5 overflow-y-auto p-2">
                    <x-ui.sidebar-link :href="route('dashboard')" :active="$dashActive" icon="house">{{ __('Dashboard') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.results.index')" :active="$resultsActive" icon="square-poll-vertical">{{ __('Results') }}</x-ui.sidebar-link>
                    @if (! empty($studentPracticeNavEnabled))
                        <x-ui.sidebar-link :href="route('student.practice.index')" :active="$practiceActive" icon="clipboard-question">{{ __('Practice') }}</x-ui.sidebar-link>
                    @endif
                    <x-ui.sidebar-link :href="route('profile.edit')" :active="$profileActive" icon="user">{{ __('Profile') }}</x-ui.sidebar-link>
                </nav>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-qs-soft bg-qs-bg px-3 py-2 md:px-4">
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-primary/25 md:hidden"
                            @click="drawerOpen = true"
                            aria-label="{{ __('Open menu') }}"
                            :aria-expanded="drawerOpen ? 'true' : 'false'"
                            aria-controls="student-mobile-nav"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </button>
                        <span class="truncate text-xs font-semibold text-qs-muted md:hidden">{{ config('app.name') }}</span>
                    </div>
                    <x-ui.shell-profile-menu />
                </header>

                <main class="qs-app-main-scroll px-4 pb-10 pt-4 sm:px-6 md:px-8 md:pt-5">
                    @if (session('status'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text shadow-sm">
                            <i class="fa-solid fa-circle-check mt-0.5 text-qs-primary" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <x-ui.shell-page-heading
                        :title="$title ?? __('Student')"
                        :subtitle="isset($subtitle) ? $subtitle : null"
                    />

                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
