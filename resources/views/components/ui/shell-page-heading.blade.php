@props([
    'title',
    'subtitle' => null,
    'periodBadge' => null,
    'periodBadgeTitle' => null,
    /** Compact slate header (coordinator workspace). */
    'compact' => false,
])

@php
    $shellCompact = filter_var($compact, FILTER_VALIDATE_BOOLEAN);
@endphp

@if ($shellCompact)
    <header class="mb-5 border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900 md:text-2xl">{{ $title }}</h1>
                    @if ($periodBadge)
                        <span
                            class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                            @if ($periodBadgeTitle) title="{{ $periodBadgeTitle }}" @endif
                        >{{ $periodBadge }}</span>
                    @endif
                </div>
                @if ($subtitle)
                    <p class="mt-1 max-w-2xl text-sm leading-snug text-slate-600">{!! $subtitle !!}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-1">{{ $actions }}</div>
            @endisset
        </div>
    </header>
@else
    <header class="mb-6 border-b border-qs-soft pb-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-semibold tracking-tight text-qs-text md:text-xl">{{ $title }}</h1>
                @if ($subtitle)
                    <div class="mt-1 max-w-3xl text-sm leading-relaxed text-qs-muted">{!! $subtitle !!}</div>
                @endif
                @if ($periodBadge)
                    <p class="mt-2 inline-flex items-center rounded-full border border-qs-primary/20 bg-qs-soft/60 px-2.5 py-0.5 text-xs font-medium text-qs-text">
                        {{ $periodBadge }}
                    </p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-1">{{ $actions }}</div>
            @endisset
        </div>
    </header>
@endif
