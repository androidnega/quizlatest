@php
    $actionLabel = match ($nextAction['kind'] ?? '') {
        'resume' => __('Resume assessment'),
        'new' => __('Next assessment'),
        'due' => __('Assignment due soon'),
        'idle' => __('Your worklist'),
        default => __('Next step'),
    };
@endphp

<a
    href="{{ $nextAction['href'] ?? route('student.work.index') }}"
    class="mb-5 block min-w-0 rounded-xl border border-slate-200/90 bg-white p-4 transition-colors hover:border-slate-300 lg:hidden"
>
    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $actionLabel }}</p>
    <p class="mt-1 truncate text-base font-semibold text-slate-900">{{ $nextAction['title'] }}</p>
    @if (($nextAction['subtitle'] ?? '') !== '')
        <p class="mt-0.5 truncate text-sm text-slate-600">{{ $nextAction['subtitle'] }}</p>
    @endif
    <span class="mt-3 inline-flex text-sm font-medium text-sky-700">{{ __('Continue') }} →</span>
</a>
