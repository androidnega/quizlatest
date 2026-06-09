@props([
    'lastName',
    'groupLabel' => null,
    'levelLabel' => null,
    'semesterLabel' => null,
])

@php
    $subline = collect([
        $groupLabel,
        $levelLabel,
    ])->filter()->implode(' · ');

    // Rotating greeting — same source of truth as the mobile wallet hero.
    $studentGreeting = \App\Support\StudentGreeting::for(auth()->user());
@endphp

<header class="mb-6 hidden lg:block">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Student dashboard') }}</p>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 sm:text-[1.65rem]">
        {{ $studentGreeting['lead'] }}{{ $studentGreeting['sep'] }} {{ $lastName }}
    </h1>
    @if ($subline !== '')
        <p class="mt-1 text-sm text-slate-600">{{ $subline }}</p>
    @endif
    {{-- $semesterLabel is intentionally not rendered here: academic-year
         hierarchy is a coordinator / super-admin concern, not student-facing. --}}
</header>
