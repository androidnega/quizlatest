<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
    <head>
        <meta charset="utf-8">
        @include('layouts.partials.viewport')
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Coordinator') }}</title>
        @include('layouts.partials.favicon')
        @include('layouts.partials.shell-sidebar-head', [
            'collapseKey' => 'qs.sidebar.coordinator',
            'workspaceFocusKey' => 'qs.coordinator.workspaceFocus',
        ])

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full overflow-hidden font-sans antialiased bg-white text-slate-900">
        @php
            $coursesOnlyActive = request()->routeIs('coordinator.courses.*') && ! request()->routeIs('coordinator.courses.assign.*');
            $navItems = [
                ['label' => __('Dashboard'), 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'house'],
                ['label' => __('Command Center'), 'href' => route('coordinator.command-center.index'), 'active' => request()->routeIs('coordinator.command-center.*'), 'icon' => 'gauge-high'],
                ['label' => __('Reporting'), 'href' => route('coordinator.reporting.index'), 'active' => request()->routeIs('coordinator.reporting.*'), 'icon' => 'chart-line'],
                ['label' => __('Students'), 'href' => route('coordinator.students.index'), 'active' => request()->routeIs('coordinator.students.*'), 'icon' => 'users'],
                ['label' => __('Programs'), 'href' => route('coordinator.programs.index'), 'active' => request()->routeIs('coordinator.programs.*'), 'icon' => 'diagram-project'],
                ['label' => __('Levels'), 'href' => route('coordinator.levels.index'), 'active' => request()->routeIs('coordinator.levels.*'), 'icon' => 'layer-group'],
                ['label' => __('Classes'), 'href' => route('coordinator.classes.index'), 'active' => request()->routeIs('coordinator.classes.*'), 'icon' => 'chalkboard'],
                ['label' => __('Courses'), 'href' => route('coordinator.courses.index'), 'active' => $coursesOnlyActive, 'icon' => 'book'],
                ['label' => __('Course assignment'), 'href' => route('coordinator.courses.assign.edit'), 'active' => request()->routeIs('coordinator.courses.assign.*'), 'icon' => 'link'],
                ['label' => __('Academic reset'), 'href' => route('coordinator.academic-reset.index'), 'active' => request()->routeIs('coordinator.academic-reset.*'), 'icon' => 'arrows-rotate'],
            ];
        @endphp
        <div
            id="qs-coordinator-root"
            x-data="qsCoordinatorShell()"
            x-init="init()"
            @keydown.escape.window="drawerOpen = false"
            class="qs-app-shell qs-app-shell--white"
        >
            <div
                x-show="drawerOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-qs-text/40 md:hidden"
                @click="drawerOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-[min(19rem,calc(100vw-2rem))] max-w-full -translate-x-full flex-col border-r border-qs-soft bg-qs-bg shadow-xl md:hidden"
                :class="drawerOpen ? '!translate-x-0' : ''"
                id="coordinator-mobile-nav"
                aria-label="{{ __('Staff navigation') }}"
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Coordinator') }}</p>
                </div>
                <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    @foreach ($navItems as $item)
                        <x-ui.sidebar-link
                            :href="$item['href']"
                            :active="$item['active']"
                            :icon="$item['icon']"
                            always-show-label
                        >{{ $item['label'] }}</x-ui.sidebar-link>
                    @endforeach
                </nav>
            </aside>

            <aside
                class="qs-desktop-sidebar hidden w-56 shrink-0 flex-col border-r border-qs-soft bg-qs-bg md:flex"
                aria-label="{{ __('Coordinator navigation') }}"
            >
                <div class="qs-sidebar-brand-row flex shrink-0 items-center justify-between gap-2 border-b border-qs-soft px-2 py-3">
                    <div class="qs-shell-sidebar-brand min-w-0 px-1">
                        <p class="truncate text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Coordinator') }}</p>
                    </div>
                    <button
                        type="button"
                        class="qs-sidebar-collapse-btn inline-flex min-h-[40px] min-w-[40px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-muted hover:bg-qs-card hover:text-qs-text focus:outline-none focus:ring-2 focus:ring-qs-primary/25"
                        @click="toggleCollapse()"
                        title="{{ __('Toggle sidebar') }}"
                    >
                        <i class="fa-solid fa-angles-left text-xs qs-sidebar-collapse-icon-expanded" aria-hidden="true"></i>
                        <i class="fa-solid fa-angles-right text-xs qs-sidebar-collapse-icon-collapsed" aria-hidden="true"></i>
                    </button>
                </div>
                <nav class="flex flex-1 flex-col space-y-0.5 overflow-y-auto p-2">
                    @foreach ($navItems as $item)
                        <x-ui.sidebar-link
                            :href="$item['href']"
                            :active="$item['active']"
                            :icon="$item['icon']"
                        >{{ $item['label'] }}</x-ui.sidebar-link>
                    @endforeach
                </nav>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
                <header class="flex shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3 py-2 shadow-sm md:gap-3 md:px-4">
                    <x-ui.coordinator-top-bar />
                    <x-ui.shell-profile-menu />
                </header>

                <main
                    class="qs-coordinator-main qs-app-main-scroll mx-auto w-full max-w-7xl bg-slate-50 px-4 py-5 sm:px-6 lg:px-8"
                    :class="workspaceFocus ? '!max-w-none mx-0 px-5 sm:px-8 lg:px-12' : ''"
                >
                    @if (session('status'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text shadow-sm">
                            <i class="fa-solid fa-circle-check mt-0.5 text-qs-primary" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <x-ui.shell-page-heading
                        compact
                        :title="$title ?? __('Coordinator dashboard')"
                        :subtitle="isset($subtitle) ? $subtitle : null"
                        :period-badge="$staffAcademicPeriodBadge ?? null"
                        :period-badge-title="__('Based on your university\'s active academic year and term. Class totals still include classes with no year set.')"
                    >
                        <x-slot name="actions">{{ $headingActions ?? '' }}</x-slot>
                    </x-ui.shell-page-heading>

                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
