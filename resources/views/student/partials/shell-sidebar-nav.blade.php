@php
    $closeDrawer = $closeDrawer ?? false;
@endphp
<div class="flex shrink-0 items-center justify-between gap-2 border-b border-[#E8EDF3] px-4 py-4">
    <div class="flex min-w-0 items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EF3340] text-sm font-bold text-white">QS</span>
        <div class="min-w-0">
            <p class="truncate font-semibold text-[#101828]">QuizSnap</p>
            <p class="truncate text-xs text-[#667085]">{{ __('Student dashboard') }}</p>
        </div>
    </div>
    @if ($closeDrawer)
        <button
            type="button"
            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E8EDF3] text-[#344054]"
            @click="drawerOpen = false"
            aria-label="{{ __('Close menu') }}"
        >
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    @endif
</div>

<nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4">
    <a href="{{ route('dashboard') }}" class="{{ $navItem }} {{ $dashActive ? $navActive : $navInactive }}">
        <i class="fa-solid fa-house w-5 text-center" aria-hidden="true"></i>
        <span>{{ __('Dashboard') }}</span>
    </a>
    <a href="{{ $studentWorkHref ?? route('student.work.index') }}" class="{{ $navItem }} {{ $workActive ? $navActive : $navInactive }}">
        <i class="fa-solid fa-clipboard-list w-5 text-center" aria-hidden="true"></i>
        <span>{{ __('Assessments') }}</span>
    </a>
    <a href="{{ route('student.assignments.index') }}" class="{{ $navItem }} {{ $assignmentsActive ? $navActive : $navInactive }}">
        <i class="fa-solid fa-file-pen w-5 text-center" aria-hidden="true"></i>
        <span>{{ __('Assignments') }}</span>
    </a>
    <a href="{{ route('student.results.index') }}" class="{{ $navItem }} {{ $resultsActive ? $navActive : $navInactive }}">
        <i class="fa-solid fa-square-poll-vertical w-5 text-center" aria-hidden="true"></i>
        <span>{{ __('Results') }}</span>
    </a>
    <a href="{{ route('student.notifications.index') }}" class="{{ $navItem }} {{ $notificationsActive ? $navActive : $navInactive }}">
        <i class="fa-solid fa-bell w-5 text-center" aria-hidden="true"></i>
        <span class="min-w-0 flex-1 truncate">{{ __('Notifications') }}</span>
        @if (($headerNoticeCount ?? 0) > 0)
            <span class="rounded-full bg-[#EF3340]/10 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-[#EF3340]">{{ min(20, (int) $headerNoticeCount) }}</span>
        @endif
    </a>
    @if ($showMaterialsNav ?? false)
        <a href="{{ $materialsHref }}" class="{{ $navItem }} {{ ($materialsActive ?? false) ? $navActive : $navInactive }}">
            <i class="fa-solid fa-folder-open w-5 text-center" aria-hidden="true"></i>
            <span>{{ __('Course outlines') }}</span>
        </a>
    @endif
    <a href="{{ route('student.help') }}" class="{{ $navItem }} {{ $helpActive ? $navActive : $navInactive }}">
        <i class="fa-solid fa-circle-question w-5 text-center" aria-hidden="true"></i>
        <span>{{ __('Help') }}</span>
    </a>
</nav>
