<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Student') }}</title>
        @include('layouts.partials.favicon')
        @include('layouts.partials.shell-sidebar-head', ['collapseKey' => 'qs.sidebar.student'])

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full overflow-hidden font-sans antialiased bg-qs-bg text-qs-text">
        @php
            $dashActive = request()->routeIs('dashboard');
            $resultsActive = request()->routeIs('student.results.*');
            $practiceActive = request()->routeIs('student.practice.*');
            $profileActive = request()->routeIs('profile.*');
            $quizzesActive = request()->routeIs('student.practice.quizzes.*');
            $materialsActive = request()->routeIs('student.practice.materials.*');
            $revisionActive = request()->routeIs('student.practice.revision');
            $practiceHubActive = $revisionActive || request()->routeIs('student.practice.index');
            $assignmentsActive = request()->routeIs('student.assignments.*');
            $notificationsActive = request()->routeIs('student.notifications.*');
            $helpActive = request()->routeIs('student.help');
            $studentWorkHref = route('dashboard') . '#student-work';
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
                class="fixed inset-y-0 left-0 z-50 flex w-[min(20rem,calc(100vw-2rem))] max-w-full -translate-x-full flex-col border-r border-qs-soft bg-qs-bg md:hidden"
                :class="drawerOpen ? '!translate-x-0' : ''"
                id="student-mobile-nav"
                aria-label="{{ __('Student navigation') }}"
            >
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-qs-soft px-4 py-3">
                    <span class="min-w-0 truncate text-sm font-semibold text-qs-text">{{ auth()->user()->name }}</span>
                    <button
                        type="button"
                        class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-qs-soft text-qs-text hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-primary/30"
                        @click="drawerOpen = false"
                        aria-label="{{ __('Close menu') }}"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="border-b border-qs-soft px-4 py-2.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ config('app.name') }}</p>
                </div>
                <nav class="flex-1 space-y-0.5 overflow-y-auto overscroll-y-contain px-2 py-3 [-webkit-overflow-scrolling:touch]">
                    <x-ui.sidebar-link :href="route('dashboard')" :active="$dashActive" icon="house" always-show-label>{{ __('Dashboard') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="$studentWorkHref" :active="false" icon="clipboard-list" always-show-label>{{ __('Assessments') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.assignments.index')" :active="$assignmentsActive" icon="clipboard-check" always-show-label>{{ __('Assignments') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.results.index')" :active="$resultsActive" icon="square-poll-vertical" always-show-label>{{ __('Results') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.notifications.index')" :active="$notificationsActive" icon="bell" always-show-label>
                        @if (($studentNoticeCount ?? 0) > 0)
                            {{ __('Notifications (:count)', ['count' => min(20, (int) $studentNoticeCount)]) }}
                        @else
                            {{ __('Notifications') }}
                        @endif
                    </x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.help')" :active="$helpActive" icon="circle-question" always-show-label>{{ __('Help') }}</x-ui.sidebar-link>
                    @if (! empty($studentPracticeNavEnabled))
                        <x-ui.sidebar-link :href="route('student.practice.revision')" :active="$practiceHubActive" icon="book-open-reader" always-show-label>{{ __('Revision & self-check') }}</x-ui.sidebar-link>
                        <x-ui.sidebar-link :href="route('student.practice.quizzes.index')" :active="$quizzesActive" icon="clipboard-question" always-show-label>{{ __('Quizzes') }}</x-ui.sidebar-link>
                        <x-ui.sidebar-link :href="route('student.practice.materials.index')" :active="$materialsActive" icon="book" always-show-label>{{ __('Materials') }}</x-ui.sidebar-link>
                    @else
                        <x-ui.sidebar-link :href="route('student.practice.revision')" :active="$revisionActive" icon="book-open-reader" always-show-label>{{ __('Revision & self-check') }}</x-ui.sidebar-link>
                        @if (! empty($studentCourseMaterialsNavEnabled))
                            <x-ui.sidebar-link :href="route('student.practice.materials.index')" :active="$materialsActive" icon="book" always-show-label>{{ __('Course materials') }}</x-ui.sidebar-link>
                        @endif
                    @endif
                    <x-ui.sidebar-link :href="route('profile.edit')" :active="$profileActive" icon="user" always-show-label>{{ __('Profile') }}</x-ui.sidebar-link>
                </nav>
            </aside>

            <aside
                class="qs-desktop-sidebar hidden w-56 shrink-0 flex-col border-r border-qs-soft bg-qs-bg md:flex"
                aria-label="{{ __('Student navigation') }}"
            >
                <div class="qs-sidebar-brand-row flex shrink-0 items-center justify-between gap-2 border-b border-qs-soft px-2 py-3">
                    <div class="qs-shell-sidebar-brand min-w-0 px-1">
                        <p class="truncate text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ config('app.name') }}</p>
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
                    <x-ui.sidebar-link :href="route('dashboard')" :active="$dashActive" icon="house">{{ __('Dashboard') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="$studentWorkHref" :active="false" icon="clipboard-list">{{ __('Assessments') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.assignments.index')" :active="$assignmentsActive" icon="clipboard-check">{{ __('Assignments') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.results.index')" :active="$resultsActive" icon="square-poll-vertical">{{ __('Results') }}</x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.notifications.index')" :active="$notificationsActive" icon="bell">
                        @if (($studentNoticeCount ?? 0) > 0)
                            {{ __('Notifications (:count)', ['count' => min(20, (int) $studentNoticeCount)]) }}
                        @else
                            {{ __('Notifications') }}
                        @endif
                    </x-ui.sidebar-link>
                    <x-ui.sidebar-link :href="route('student.help')" :active="$helpActive" icon="circle-question">{{ __('Help') }}</x-ui.sidebar-link>
                    @if (! empty($studentPracticeNavEnabled))
                        <x-ui.sidebar-link :href="route('student.practice.revision')" :active="$practiceHubActive" icon="book-open-reader">{{ __('Revision') }}</x-ui.sidebar-link>
                        <x-ui.sidebar-link :href="route('student.practice.quizzes.index')" :active="$quizzesActive" icon="clipboard-question">{{ __('Quizzes') }}</x-ui.sidebar-link>
                        <x-ui.sidebar-link :href="route('student.practice.materials.index')" :active="$materialsActive" icon="book">{{ __('Materials') }}</x-ui.sidebar-link>
                    @else
                        <x-ui.sidebar-link :href="route('student.practice.revision')" :active="$revisionActive" icon="book-open-reader">{{ __('Revision') }}</x-ui.sidebar-link>
                        @if (! empty($studentCourseMaterialsNavEnabled))
                            <x-ui.sidebar-link :href="route('student.practice.materials.index')" :active="$materialsActive" icon="book">{{ __('Materials') }}</x-ui.sidebar-link>
                        @endif
                    @endif
                    <x-ui.sidebar-link :href="route('profile.edit')" :active="$profileActive" icon="user">{{ __('Profile') }}</x-ui.sidebar-link>
                </nav>
            </aside>

            <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-white">
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-qs-soft/90 bg-white px-3 py-2.5 shadow-[0_1px_0_0_rgba(21,52,58,0.04)] md:border-qs-soft md:px-4 md:py-2 md:shadow-none">
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
                    <x-ui.shell-profile-menu icon-only />
                </header>

                <main class="qs-app-main-scroll qs-student-main w-full min-w-0 bg-white px-3 pt-3 pb-[max(6.75rem,env(safe-area-inset-bottom,0px)+5.25rem)] antialiased md:px-7 md:pb-10 md:pt-6 lg:px-9 xl:px-10">
                    @if (session('status'))
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text shadow-sm">
                            <i class="fa-solid fa-circle-check mt-0.5 text-qs-primary" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if (! request()->routeIs('dashboard'))
                        <x-ui.shell-page-heading
                            :title="$title ?? __('Student')"
                            :subtitle="isset($subtitle) ? $subtitle : null"
                        />
                    @endif

                    {{ $slot }}
                </main>

                <nav class="qs-student-nav-dock" aria-label="{{ __('Student quick navigation') }}">
                    <div class="qs-student-nav-dock__inner">
                        <a href="{{ route('dashboard') }}" class="qs-student-nav-dock__a {{ $dashActive ? 'qs-student-nav-dock__a--active' : '' }}">
                            <i class="fa-solid fa-house qs-student-nav-dock__icon" aria-hidden="true"></i>
                            <span>{{ __('Dashboard') }}</span>
                        </a>
                        <a href="{{ $studentWorkHref }}" class="qs-student-nav-dock__a">
                            <i class="fa-solid fa-clipboard-list qs-student-nav-dock__icon" aria-hidden="true"></i>
                            <span>{{ __('Assessments') }}</span>
                        </a>
                        <a href="{{ route('student.assignments.index') }}" class="qs-student-nav-dock__a {{ $assignmentsActive ? 'qs-student-nav-dock__a--active' : '' }}">
                            <i class="fa-solid fa-file-pen qs-student-nav-dock__icon" aria-hidden="true"></i>
                            <span>{{ __('Assignments') }}</span>
                        </a>
                        <a href="{{ route('student.results.index') }}" class="qs-student-nav-dock__a {{ $resultsActive ? 'qs-student-nav-dock__a--active' : '' }}">
                            <i class="fa-solid fa-square-poll-vertical qs-student-nav-dock__icon" aria-hidden="true"></i>
                            <span>{{ __('Results') }}</span>
                        </a>
                    </div>
                </nav>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
