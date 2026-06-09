<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full overflow-hidden">
    <head>
        <meta charset="utf-8">
        @include('layouts.partials.viewport')
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }} — {{ __('Student') }}</title>
        @include('layouts.partials.favicon')

        @vite(['resources/css/app.css', 'resources/css/student-dashboard.css', 'resources/js/app.js'])
    </head>
    @php
        // When the super admin has opted the student body into the wallet
        // mobile experience, mirror the chosen color theme onto the mobile
        // header + FAB across every student page (not just the dashboard).
        // Tablet / desktop (>=lg) is unaffected.
        $walletSettings = app(\App\Services\SystemSettingsService::class);
        $walletEnabled = $walletSettings->getBool('student_dashboard_mobile_wallet', false);
        $walletTheme = $walletEnabled
            ? app(\App\Services\StudentDashboardBrandingService::class)->walletTheme()
            : null;
    @endphp
    <body
        @class([
            'qs-std h-full overflow-x-hidden overflow-y-hidden bg-white text-[#101828] antialiased',
            'qs-std--wallet' => $walletEnabled,
        ])
        @if ($walletTheme !== null) data-qs-wallet-theme="{{ $walletTheme }}" @endif
    >
        @php
            $dashActive = request()->routeIs('dashboard');
            $workActive = request()->routeIs('student.work.index');
            $resultsActive = request()->routeIs('student.results.*');
            $profileActive = request()->routeIs('profile.*');
            $materialsActive = request()->routeIs('student.practice.materials.*');
            $assignmentsActive = request()->routeIs('student.assignments.*');
            $notificationsActive = request()->routeIs('student.notifications.*');
            $helpActive = request()->routeIs('student.help');
            $studentWorkHref = route('student.work.index');
            $headerNoticeCount = (int) ($studentNoticeCount ?? 0);
            $showMaterialsNav = ! empty($studentMaterialsBrowseEnabled);
            $materialsHref = route('student.practice.materials.index');
            $navContext = compact(
                'dashActive',
                'workActive',
                'assignmentsActive',
                'resultsActive',
                'notificationsActive',
                'helpActive',
                'showMaterialsNav',
                'materialsActive',
                'materialsHref',
                'headerNoticeCount',
                'studentWorkHref',
            );
            $isListPage = request()->routeIs(
                'student.work.*',
                'student.assignments.*',
                'student.results.*',
                'student.help',
            );
        @endphp

        <div class="flex h-full min-h-0 w-full min-w-0 flex-col {{ $isListPage ? 'qs-std--list-page' : '' }}">
            <div class="sticky top-0 z-30 shrink-0">
                @include('student.partials.shell-mobile-header', ['navContext' => $navContext])
                @include('student.partials.shell-floating-header', ['navContext' => $navContext])
            </div>

            <main class="qs-app-main-scroll min-h-0 min-w-0 flex-1 overflow-y-auto overflow-x-hidden pb-[max(6.5rem,env(safe-area-inset-bottom,0px)+5.5rem)] lg:pb-8">
                @if (session('status'))
                    <div class="qs-std-page-wrap pt-4">
                        <div class="flex items-start gap-3 rounded-xl border border-slate-200/90 bg-white px-4 py-3 text-sm text-[#344054]">
                            <i class="fa-solid fa-circle-check mt-0.5 text-[#059669]" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    </div>
                @endif

                @if (request()->routeIs('dashboard'))
                    {{ $slot }}
                @elseif ($isListPage)
                    <div class="qs-std-page-wrap qs-std-page-wrap--list qs-std-list-page pb-8">
                        @include('student.partials.page-head', [
                            'title' => $title ?? __('Student'),
                            'subtitle' => $subtitle ?? null,
                        ])
                        <div class="qs-std-list-page__body">
                            {{ $slot }}
                        </div>
                    </div>
                @else
                    <div class="qs-std-page-wrap pt-4 pb-8">
                        <x-ui.shell-page-heading
                            :title="$title ?? __('Student')"
                            :subtitle="isset($subtitle) ? $subtitle : null"
                        />
                        {{ $slot }}
                    </div>
                @endif
            </main>

            <div
                class="qs-std-fab lg:hidden"
                data-qs-fab
                role="navigation"
                aria-label="{{ __('Student quick navigation') }}"
            >
                <button
                    type="button"
                    class="qs-std-fab__backdrop"
                    data-qs-fab-backdrop
                    tabindex="-1"
                    aria-hidden="true"
                ></button>

                <ul class="qs-std-fab__menu" data-qs-fab-menu>
                    <li class="qs-std-fab__menu-item" style="--qs-fab-i:0">
                        <a href="{{ route('dashboard') }}" @class(['qs-std-fab__item', 'is-active' => $dashActive])>
                            <span class="qs-std-fab__item-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                            <span class="qs-std-fab__item-label">{{ __('Home') }}</span>
                        </a>
                    </li>
                    <li class="qs-std-fab__menu-item" style="--qs-fab-i:1">
                        <a href="{{ $studentWorkHref }}" @class(['qs-std-fab__item', 'is-active' => $workActive])>
                            <span class="qs-std-fab__item-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span>
                            <span class="qs-std-fab__item-label">{{ __('Assessments') }}</span>
                        </a>
                    </li>
                    <li class="qs-std-fab__menu-item" style="--qs-fab-i:2">
                        <a href="{{ route('student.assignments.index') }}" @class(['qs-std-fab__item', 'is-active' => $assignmentsActive])>
                            <span class="qs-std-fab__item-icon"><i class="fa-solid fa-file-pen" aria-hidden="true"></i></span>
                            <span class="qs-std-fab__item-label">{{ __('Assignments') }}</span>
                        </a>
                    </li>
                    <li class="qs-std-fab__menu-item" style="--qs-fab-i:3">
                        <a href="{{ route('student.results.index') }}" @class(['qs-std-fab__item', 'is-active' => $resultsActive])>
                            <span class="qs-std-fab__item-icon"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i></span>
                            <span class="qs-std-fab__item-label">{{ __('Results') }}</span>
                        </a>
                    </li>
                </ul>

                <button
                    type="button"
                    class="qs-std-fab__toggle"
                    data-qs-fab-toggle
                    aria-expanded="false"
                    aria-controls="qs-std-fab-menu"
                    aria-label="{{ __('Open quick navigation') }}"
                >
                    <span class="qs-std-fab__plus" aria-hidden="true">
                        <span class="qs-std-fab__plus-bar"></span>
                        <span class="qs-std-fab__plus-bar"></span>
                    </span>
                </button>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
