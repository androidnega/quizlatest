@php
    $pillNav = 'inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors min-h-[40px]';
    $pillActive = 'bg-white text-slate-900 shadow-sm';
    $pillInactive = 'text-slate-600 hover:text-slate-900';
@endphp

<nav class="qs-std-pill-nav__track" aria-label="{{ __('Student navigation') }}">
    <a href="{{ route('dashboard') }}" class="{{ $pillNav }} {{ ($dashActive ?? false) ? $pillActive : $pillInactive }}">
        {{ __('Home') }}
    </a>
    <a href="{{ $studentWorkHref ?? route('student.work.index') }}" class="{{ $pillNav }} {{ ($workActive ?? false) ? $pillActive : $pillInactive }}">
        {{ __('Assessments') }}
    </a>
    <a href="{{ route('student.assignments.index') }}" class="{{ $pillNav }} {{ ($assignmentsActive ?? false) ? $pillActive : $pillInactive }}">
        {{ __('Assignments') }}
    </a>
    <a href="{{ route('student.results.index') }}" class="{{ $pillNav }} {{ ($resultsActive ?? false) ? $pillActive : $pillInactive }}">
        {{ __('Results') }}
    </a>
    @if ($showMaterialsNav ?? false)
        <a href="{{ $materialsHref }}" class="{{ $pillNav }} {{ ($materialsActive ?? false) ? $pillActive : $pillInactive }}">
            {{ __('Materials') }}
        </a>
    @endif
    <a href="{{ route('student.help') }}" class="{{ $pillNav }} {{ ($helpActive ?? false) ? $pillActive : $pillInactive }}">
        {{ __('Help') }}
    </a>
</nav>
