@php
    $compact = $compact ?? false;
    $navLink = $compact
        ? 'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors'
        : 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors';
    $navActive = 'bg-[#EF3340]/10 text-[#EF3340]';
    $navInactive = 'text-slate-600 hover:bg-slate-50 hover:text-slate-900';
@endphp

<nav class="{{ $compact ? 'flex gap-1' : 'flex flex-wrap items-center gap-0.5' }}" aria-label="{{ __('Student navigation') }}">
    <a href="{{ route('dashboard') }}" class="{{ $navLink }} {{ ($dashActive ?? false) ? $navActive : $navInactive }}">
        @unless ($compact)
            <i class="fa-solid fa-house w-4 text-center text-xs opacity-80" aria-hidden="true"></i>
        @endunless
        <span>{{ __('Home') }}</span>
    </a>
    <a href="{{ $studentWorkHref ?? route('student.work.index') }}" class="{{ $navLink }} {{ ($workActive ?? false) ? $navActive : $navInactive }}">
        @unless ($compact)
            <i class="fa-solid fa-clipboard-list w-4 text-center text-xs opacity-80" aria-hidden="true"></i>
        @endunless
        <span>{{ __('Assessments') }}</span>
    </a>
    <a href="{{ route('student.assignments.index') }}" class="{{ $navLink }} {{ ($assignmentsActive ?? false) ? $navActive : $navInactive }}">
        @unless ($compact)
            <i class="fa-solid fa-file-pen w-4 text-center text-xs opacity-80" aria-hidden="true"></i>
        @endunless
        <span>{{ __('Assignments') }}</span>
    </a>
    <a href="{{ route('student.results.index') }}" class="{{ $navLink }} {{ ($resultsActive ?? false) ? $navActive : $navInactive }}">
        @unless ($compact)
            <i class="fa-solid fa-square-poll-vertical w-4 text-center text-xs opacity-80" aria-hidden="true"></i>
        @endunless
        <span>{{ __('Results') }}</span>
    </a>
    <a href="{{ route('student.notifications.index') }}" class="{{ $navLink }} {{ ($notificationsActive ?? false) ? $navActive : $navInactive }}">
        @unless ($compact)
            <i class="fa-solid fa-bell w-4 text-center text-xs opacity-80" aria-hidden="true"></i>
        @endunless
        <span>{{ __('Notifications') }}</span>
        @if (($headerNoticeCount ?? 0) > 0)
            <span class="inline-flex min-h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-[#EF3340] px-1.5 text-[10px] font-bold text-white tabular-nums">
                {{ min(99, (int) $headerNoticeCount) }}
            </span>
        @endif
    </a>
    @if ($showMaterialsNav ?? false)
        <a href="{{ $materialsHref }}" class="{{ $navLink }} {{ ($materialsActive ?? false) ? $navActive : $navInactive }}">
            @unless ($compact)
                <i class="fa-solid fa-folder-open w-4 text-center text-xs opacity-80" aria-hidden="true"></i>
            @endunless
            <span>{{ __('Materials') }}</span>
        </a>
    @endif
    <a href="{{ route('student.help') }}" class="{{ $navLink }} {{ ($helpActive ?? false) ? $navActive : $navInactive }}">
        @unless ($compact)
            <i class="fa-solid fa-circle-question w-4 text-center text-xs opacity-80" aria-hidden="true"></i>
        @endunless
        <span>{{ __('Help') }}</span>
    </a>
</nav>
