<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
    <head>
        <meta charset="utf-8">
        @include('layouts.partials.viewport')
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Examiner') }}</title>
        @include('layouts.partials.favicon')
        @include('layouts.partials.shell-sidebar-head', ['collapseKey' => 'qs.sidebar.examiner'])

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full overflow-hidden font-sans antialiased bg-qs-bg text-qs-text">
        @php
            $examsActive = request()->routeIs('examiner.exams.*');
            $coursesActive = request()->routeIs('examiner.courses.*');
            $gradingActive = request()->routeIs('examiner.grading.*');
            $navItems = [
                ['label' => __('Dashboard'), 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'house'],
                ['label' => __('Courses'), 'href' => route('examiner.courses.index'), 'active' => $coursesActive, 'icon' => 'book'],
                ['label' => __('Classes'), 'href' => route('examiner.teaching-classes.index'), 'active' => request()->routeIs('examiner.teaching-classes.*'), 'icon' => 'user-group'],
                ['label' => __('Exams'), 'href' => route('examiner.exams.index'), 'active' => $examsActive, 'icon' => 'file-lines'],
                ['label' => __('Grading'), 'href' => route('examiner.grading.pending'), 'active' => $gradingActive, 'icon' => 'clipboard-check'],
            ];
        @endphp
        <div
            x-data="{
                drawerOpen: false,
                quickOpen: false,
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
                    this.syncShellSidebarClass();
                },
                syncShellSidebarClass() {
                    try {
                        document.documentElement.classList.toggle('qs-shell-sidebar-collapsed', this.collapsed);
                    } catch (e) {}
                },
            }"
            x-init="syncShellSidebarClass()"
            @keydown.escape.window="drawerOpen = false"
            class="qs-app-shell"
        >
            <div
                x-show="drawerOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-qs-text/40 md:hidden"
                @click="drawerOpen = false"
                aria-hidden="true"
            ></div>
            <div
                x-show="quickOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-black/20 md:hidden"
                @click="quickOpen = false"
                aria-hidden="true"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-[min(19rem,calc(100vw-2rem))] max-w-full -translate-x-full flex-col border-r border-qs-soft bg-qs-bg shadow-xl md:hidden"
                :class="drawerOpen ? '!translate-x-0' : ''"
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
                            always-show-label
                        >{{ $item['label'] }}</x-ui.sidebar-link>
                    @endforeach
                </nav>
            </aside>

            <aside
                class="qs-desktop-sidebar hidden w-56 shrink-0 flex-col border-r border-qs-soft bg-qs-bg md:flex"
                aria-label="{{ __('Examiner navigation') }}"
            >
                <div class="qs-sidebar-brand-row flex shrink-0 items-center justify-between gap-2 border-b border-qs-soft px-2 py-3">
                    <div class="qs-shell-sidebar-brand min-w-0 px-1">
                        <p class="truncate text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Examiner') }}</p>
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
                    <div class="flex items-center gap-2">
                        @php
                            $draftAlerts = is_array($examinerDraftAlerts ?? null) ? $examinerDraftAlerts : [];
                            $deletedDraftCount = (int) ($examinerDraftDeletedCount ?? 0);
                        @endphp
                        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                            <button
                                type="button"
                                class="relative inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card"
                                @click="open = !open"
                                aria-label="{{ __('Draft notifications') }}"
                            >
                                <i class="fa-regular fa-bell"></i>
                                @if (count($draftAlerts) > 0 || $deletedDraftCount > 0)
                                    <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold text-white">
                                        {{ count($draftAlerts) + ($deletedDraftCount > 0 ? 1 : 0) }}
                                    </span>
                                @endif
                            </button>
                            <div
                                x-show="open"
                                x-cloak
                                @click.outside="open = false"
                                class="absolute right-0 z-[60] mt-1 w-80 max-w-[90vw] rounded-xl border border-qs-soft bg-white p-3 shadow-lg"
                            >
                                <p class="text-xs font-semibold text-qs-text">{{ __('Draft reminders') }}</p>
                                @if ($deletedDraftCount > 0)
                                    <p class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-2 py-1.5 text-xs text-rose-700">
                                        {{ $deletedDraftCount }} draft assessment(s) older than 14 days were auto-deleted.
                                    </p>
                                @endif
                                @forelse ($draftAlerts as $alert)
                                    <a href="{{ route('examiner.quizzes.workspace', $alert['id']) }}" class="mt-2 block rounded-md border border-slate-200 px-2 py-1.5 text-xs hover:bg-slate-50">
                                        <span class="font-medium text-slate-900">{{ $alert['title'] }}</span>
                                        <span class="mt-1 block text-slate-600">
                                            @if ($alert['urgent'])
                                                {{ __('Draft is :days days old. Return now or it will auto-delete in :remaining days.', ['days' => $alert['age_days'], 'remaining' => $alert['remaining_days']]) }}
                                            @else
                                                {{ __('Draft is :days days old. Continue it soon.', ['days' => $alert['age_days']]) }}
                                            @endif
                                        </span>
                                    </a>
                                @empty
                                    <p class="mt-2 text-xs text-slate-500">{{ __('No pending draft reminders.') }}</p>
                                @endforelse
                            </div>
                        </div>
                        <x-ui.shell-profile-menu />
                    </div>
                </header>

                <main class="qs-app-main-scroll mx-auto w-full max-w-screen-2xl px-4 py-5 sm:px-6 lg:px-8 xl:px-10">
                    @if (session('status'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text shadow-sm">
                            <i class="fa-solid fa-circle-check mt-0.5 text-qs-primary" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <x-ui.shell-page-heading
                        :title="$title ?? __('Examiner')"
                        :subtitle="isset($subtitle) ? $subtitle : null"
                        :period-badge="null"
                    >
                        <x-slot name="actions">{{ $headingActions ?? '' }}</x-slot>
                    </x-ui.shell-page-heading>

                    {{ $slot }}
                </main>
            </div>

            <div class="fixed bottom-5 right-5 z-50 md:hidden">
            <div
                x-show="quickOpen"
                x-cloak
                class="mb-2 w-56 rounded-xl border border-qs-soft bg-qs-card p-2 shadow-lg"
            >
                    <a href="{{ route('examiner.exams.create') }}" class="flex min-h-[40px] items-center gap-2 rounded-lg px-3 text-sm font-medium text-qs-text hover:bg-qs-soft/50" @click="quickOpen = false">
                        <i class="fa-solid fa-plus text-xs text-qs-primary" aria-hidden="true"></i>
                        {{ __('New assessment') }}
                    </a>
                    <a href="{{ route('examiner.teaching-classes.index') }}" class="flex min-h-[40px] items-center gap-2 rounded-lg px-3 text-sm font-medium text-qs-text hover:bg-qs-soft/50" @click="quickOpen = false">
                        <i class="fa-solid fa-users text-xs text-qs-primary" aria-hidden="true"></i>
                        {{ __('Classes') }}
                    </a>
                    <a href="{{ route('examiner.grading.pending') }}" class="flex min-h-[40px] items-center gap-2 rounded-lg px-3 text-sm font-medium text-qs-text hover:bg-qs-soft/50" @click="quickOpen = false">
                        <i class="fa-solid fa-clipboard-check text-xs text-qs-primary" aria-hidden="true"></i>
                        {{ __('Grading') }}
                    </a>
                </div>
                <button
                    type="button"
                    class="inline-flex h-14 w-14 items-center justify-center rounded-full border border-qs-primary/20 bg-qs-primary text-white shadow-lg hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-qs-primary/35"
                    :class="quickOpen ? 'rotate-45' : ''"
                    @click="quickOpen = !quickOpen"
                    aria-label="{{ __('Open quick actions') }}"
                >
                    <i class="fa-solid fa-plus text-lg" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
