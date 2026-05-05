<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Examiner') }}</title>
        @include('layouts.partials.favicon')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full overflow-hidden font-sans antialiased bg-qs-bg text-qs-text">
        @php
            $sessionsActive = request()->routeIs('examiner.exams.sessions.*') || request()->routeIs('examiner.exam-sessions.*');
            $examsActive = request()->routeIs('examiner.exams.*') && ! $sessionsActive;
            $gradingActive = request()->routeIs('examiner.grading.*');
            $practiceActive = request()->routeIs('examiner.practice-overview.*');
            $navItems = [
                ['label' => __('Dashboard'), 'href' => route('examiner.dashboard'), 'active' => request()->routeIs('examiner.dashboard'), 'icon' => 'house'],
                ['label' => __('Exams'), 'href' => route('examiner.exams.index'), 'active' => $examsActive, 'icon' => 'file-lines'],
                ['label' => __('Exam builder'), 'href' => route('examiner.exams.create'), 'active' => request()->routeIs('examiner.exams.create'), 'icon' => 'pen-ruler'],
                ['label' => __('Essay grading'), 'href' => route('examiner.grading.pending'), 'active' => $gradingActive, 'icon' => 'clipboard-check'],
                ['label' => __('Sessions & held review'), 'href' => route('examiner.exams.index'), 'active' => $sessionsActive, 'icon' => 'chart-simple'],
                ['label' => __('Practice overview'), 'href' => route('examiner.practice-overview.index'), 'active' => $practiceActive, 'icon' => 'book-open-reader'],
            ];
        @endphp
        <div
            x-data="{
                drawerOpen: false,
                collapsed: (() => {
                    try {
                        return localStorage.getItem('qs.sidebar.examiner') === '1';
                    } catch (e) {
                        return false;
                    }
                })(),
                toggleCollapse() {
                    this.collapsed = !this.collapsed;
                    try {
                        localStorage.setItem('qs.sidebar.examiner', this.collapsed ? '1' : '0');
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
                id="examiner-mobile-nav"
                aria-label="{{ __('Examiner navigation') }}"
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Examiner') }}</p>
                </div>
                <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    @foreach ($navItems as $item)
                        <x-ui.sidebar-link
                            :href="$item['href']"
                            :active="$item['active']"
                            :icon="$item['icon']"
                            :close-drawer="true"
                            always-show-label
                        >{{ $item['label'] }}</x-ui.sidebar-link>
                    @endforeach
                </nav>
            </aside>

            <aside
                class="hidden shrink-0 flex-col border-r border-qs-soft bg-qs-bg transition-[width] duration-200 ease-out md:flex"
                :class="collapsed ? 'w-[4.25rem]' : 'w-56'"
                aria-label="{{ __('Examiner navigation') }}"
            >
                <div class="flex shrink-0 items-center gap-2 border-b border-qs-soft px-2 py-3" :class="collapsed ? 'flex-col' : 'justify-between'">
                    <div class="min-w-0 px-1" x-show="! collapsed" x-transition>
                        <p class="truncate text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Examiner') }}</p>
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
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-qs-soft bg-qs-bg px-3 py-2 md:px-4">
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-primary/25 md:hidden"
                            @click="drawerOpen = true"
                            aria-label="{{ __('Open menu') }}"
                            :aria-expanded="drawerOpen ? 'true' : 'false'"
                            aria-controls="examiner-mobile-nav"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </button>
                        <span class="truncate text-xs font-semibold text-qs-muted md:hidden">{{ config('app.name') }}</span>
                    </div>
                    <x-ui.shell-profile-menu />
                </header>

                <main class="qs-app-main-scroll mx-auto w-full max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text shadow-sm">
                            <i class="fa-solid fa-circle-check mt-0.5 text-qs-primary" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <x-ui.shell-page-heading
                        :title="$title ?? __('Examiner')"
                        :subtitle="isset($subtitle) ? $subtitle : null"
                        :period-badge="$staffAcademicPeriodBadge ?? null"
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
