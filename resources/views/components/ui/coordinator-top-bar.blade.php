@php
    $nav = [
        [
            'href' => route('dashboard'),
            'label' => __('Dashboard'),
            'icon' => 'fa-chart-simple',
            'routeIs' => ['dashboard'],
        ],
        [
            'href' => route('coordinator.students.index'),
            'label' => __('Students'),
            'icon' => 'fa-user-graduate',
            'routeIs' => ['coordinator.students.*'],
        ],
        [
            'href' => route('coordinator.classes.index'),
            'label' => __('Classes'),
            'icon' => 'fa-chalkboard-user',
            'routeIs' => ['coordinator.classes.*'],
        ],
        [
            'href' => route('coordinator.courses.assign.edit'),
            'label' => __('Course assignment'),
            'icon' => 'fa-link',
            'routeIs' => ['coordinator.courses.assign.*'],
        ],
    ];
@endphp

<div class="flex min-w-0 flex-1 items-center gap-2 md:gap-3">
    <button
        type="button"
        class="qs-coord-header-icon md:hidden"
        @click="drawerOpen = true"
        aria-label="{{ __('Open menu') }}"
        :aria-expanded="drawerOpen ? 'true' : 'false'"
        aria-controls="coordinator-mobile-nav"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
    </button>

    <nav class="hidden min-w-0 items-center gap-1 md:flex" aria-label="{{ __('Quick navigation') }}">
        @foreach ($nav as $link)
            <a
                href="{{ $link['href'] }}"
                @class([
                    'qs-coord-header-icon qs-coord-header-icon--quiet',
                    '!border-qs-primary/35 !bg-qs-soft !text-qs-primary shadow-sm' => request()->routeIs($link['routeIs']),
                ])
                title="{{ $link['label'] }}"
            >
                <i class="fa-solid {{ $link['icon'] }} text-sm" aria-hidden="true"></i>
                <span class="sr-only">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>

<div class="flex shrink-0 items-center gap-1" role="toolbar" aria-label="{{ __('Tools') }}">
    <a
        href="{{ route('coordinator.classes.index') }}"
        class="qs-coord-header-icon"
        title="{{ __('Classes · upload roster') }}"
    >
        <i class="fa-solid fa-file-import text-sm" aria-hidden="true"></i>
        <span class="sr-only">{{ __('Classes · upload roster') }}</span>
    </a>
    <a
        href="{{ route('coordinator.classes.create') }}"
        class="qs-coord-header-icon"
        title="{{ __('Create class') }}"
    >
        <i class="fa-solid fa-plus text-sm" aria-hidden="true"></i>
        <span class="sr-only">{{ __('Create class') }}</span>
    </a>
    <a
        href="{{ route('coordinator.academic-reset.index') }}"
        class="qs-coord-header-icon"
        title="{{ __('Academic reset') }}"
    >
        <i class="fa-solid fa-arrows-rotate text-sm" aria-hidden="true"></i>
        <span class="sr-only">{{ __('Academic reset') }}</span>
    </a>

    <button
        type="button"
        class="qs-coord-header-icon"
        @click="toggleWorkspaceFocus()"
        :title="workspaceFocus ? @js(__('Show sidebar')) : @js(__('Wide dashboard'))"
    >
        <i class="fa-solid text-sm" :class="workspaceFocus ? 'fa-bars' : 'fa-window-maximize'" aria-hidden="true"></i>
        <span class="sr-only" x-text="workspaceFocus ? @js(__('Show sidebar')) : @js(__('Wide dashboard'))"></span>
    </button>

    <button
        type="button"
        class="qs-coord-header-icon"
        @click="toggleFullscreen()"
        :disabled="! fullscreenSupported"
        :title="isFullscreen ? @js(__('Exit full screen')) : @js(__('Full screen'))"
    >
        <i class="fa-solid text-sm" :class="isFullscreen ? 'fa-compress' : 'fa-expand'" aria-hidden="true"></i>
        <span class="sr-only" x-text="isFullscreen ? @js(__('Exit full screen')) : @js(__('Full screen'))"></span>
    </button>
</div>
