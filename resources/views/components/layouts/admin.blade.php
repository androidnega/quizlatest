@props([
    'contentFullWidth' => false,
    'whiteWorkspace' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Admin') }}</title>
        @include('layouts.partials.favicon')
        @include('layouts.partials.shell-sidebar-head', ['collapseKey' => 'qs.sidebar.admin'])

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full overflow-hidden font-sans antialiased bg-qs-bg text-qs-text">
        @php
            $navItems = [
                ['label' => __('Dashboard'), 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard') && auth()->user()?->role === 'admin', 'icon' => 'gauge-high'],
                ['label' => __('System reporting'), 'href' => route('admin.system-reporting.index'), 'active' => request()->routeIs('admin.system-reporting.*'), 'icon' => 'chart-line'],
                ['label' => __('Universities'), 'href' => route('admin.universities.index'), 'active' => request()->routeIs('admin.universities.*'), 'icon' => 'building-columns'],
                ['label' => __('Academic years'), 'href' => route('admin.academic-years.index'), 'active' => request()->routeIs('admin.academic-years.*'), 'icon' => 'calendar-days'],
                ['label' => __('Coordinators'), 'href' => route('admin.coordinators.index'), 'active' => request()->routeIs('admin.coordinators.*'), 'icon' => 'user-group'],
            ];
            if (auth()->user()?->isSuperAdmin()) {
                $navItems[] = ['label' => __('Manage users'), 'href' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*'), 'icon' => 'users'];
            }
            $navItems = array_merge($navItems, [
                ['label' => __('Settings'), 'href' => route('admin.settings.index'), 'active' => request()->routeIs('admin.settings.*'), 'icon' => 'gear'],
                ['label' => __('Academic resets'), 'href' => route('admin.academic-reset-snapshots.index'), 'active' => request()->routeIs('admin.academic-reset-snapshots.*'), 'icon' => 'database'],
            ]);
            $adminSettingsHref = \Illuminate\Support\Facades\Route::has('admin.settings.index') ? route('admin.settings.index') : null;
        @endphp
        <div
            x-data="{
                drawerOpen: false,
                collapsed: (() => {
                    try {
                        return localStorage.getItem('qs.sidebar.admin') === '1';
                    } catch (e) {
                        return false;
                    }
                })(),
                toggleCollapse() {
                    this.collapsed = !this.collapsed;
                    try {
                        localStorage.setItem('qs.sidebar.admin', this.collapsed ? '1' : '0');
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

            <aside
                class="fixed inset-y-0 left-0 z-50 flex w-[min(19rem,calc(100vw-2rem))] max-w-full -translate-x-full flex-col border-r border-qs-soft bg-white shadow-xl md:hidden"
                :class="drawerOpen ? '!translate-x-0' : ''"
                id="admin-mobile-nav"
                aria-label="{{ __('Admin navigation') }}"
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
                    <div class="flex flex-wrap items-baseline gap-2">
                        <x-brand-logo class="text-base" />
                        <span class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Admin') }}</span>
                    </div>
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
                class="qs-desktop-sidebar hidden w-56 shrink-0 flex-col border-r border-qs-soft bg-white md:flex"
                aria-label="{{ __('Admin navigation') }}"
            >
                <div class="qs-sidebar-brand-row flex shrink-0 items-center justify-between gap-2 border-b border-qs-soft px-2 py-3">
                    <div class="qs-shell-sidebar-brand min-w-0 px-1">
                        <div class="flex flex-wrap items-baseline gap-2">
                            <x-brand-logo class="text-base" />
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Admin') }}</span>
                        </div>
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
                <header class="flex shrink-0 flex-col gap-1 border-b border-qs-soft bg-white px-3 py-2.5 md:px-4">
                    <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-3">
                        <button
                            type="button"
                            class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-primary/25 md:hidden"
                            @click="drawerOpen = true"
                            aria-label="{{ __('Open menu') }}"
                            :aria-expanded="drawerOpen ? 'true' : 'false'"
                            aria-controls="admin-mobile-nav"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </button>
                        <div class="min-w-0 flex-1">
                            <p class="hidden text-[11px] font-semibold uppercase tracking-[0.14em] text-qs-muted md:block">{{ config('app.name', 'QuizSnap') }} · {{ __('Admin') }}</p>
                            @isset($title)
                                <h1 class="truncate text-sm font-semibold leading-tight text-qs-text md:mt-0.5 md:text-base">{{ $title }}</h1>
                            @else
                                <span class="truncate text-xs font-semibold text-qs-muted md:hidden">{{ config('app.name') }}</span>
                            @endisset
                        </div>
                    </div>
                    <x-ui.shell-profile-menu :settings-href="$adminSettingsHref" />
                    </div>
                </header>

                <main @class([
                    'qs-app-main-scroll w-full px-4 py-5 sm:px-6 lg:px-8',
                    'qs-admin-workspace' => ! $whiteWorkspace,
                    'bg-white' => $whiteWorkspace,
                    'mx-auto max-w-7xl' => ! $contentFullWidth,
                ])>
                    @if (session('status'))
                        <div @class([
                            'mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                            'border-slate-200 bg-white text-slate-800 shadow-sm' => $whiteWorkspace,
                            'border-qs-soft bg-qs-card text-qs-text' => ! $whiteWorkspace,
                        ])>
                            <i @class([
                                'fa-solid fa-circle-check mt-0.5',
                                'text-emerald-600' => $whiteWorkspace,
                                'text-qs-primary' => ! $whiteWorkspace,
                            ]) aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @isset($subtitle)
                        <div @class([
                            'mb-6 text-sm leading-relaxed text-qs-muted [&_.mt-1]:mt-1 [&_span]:text-xs [&_span]:font-normal [&_span]:text-qs-muted',
                            'max-w-3xl' => ! $contentFullWidth,
                        ])>
                            {!! $subtitle !!}
                        </div>
                    @endisset

                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
