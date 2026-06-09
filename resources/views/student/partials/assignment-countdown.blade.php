@props([
    'endsAt',
    'prefix',
    'class' => 'qs-std-dash-countdown mt-1',
])

@if (filled($endsAt) && filled($prefix))
    <p
        {{ $attributes->merge(['class' => $class]) }}
        data-qs-countdown
        data-qs-countdown-ends="{{ $endsAt }}"
        data-qs-countdown-prefix="{{ $prefix }}"
    >
        <span class="qs-std-dash-countdown__prefix">{{ $prefix }}</span>
        <span class="qs-std-dash-countdown__time tabular-nums"></span>
    </p>
@endif
