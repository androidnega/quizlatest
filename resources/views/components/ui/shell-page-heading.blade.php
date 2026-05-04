@props([
    'title',
    'subtitle' => null,
    'periodBadge' => null,
])

<header class="mb-6 border-b border-qs-soft pb-5">
    <h1 class="text-lg font-semibold tracking-tight text-qs-text md:text-xl">{{ $title }}</h1>
    @if ($subtitle)
        <div class="mt-1 max-w-3xl text-sm leading-relaxed text-qs-muted">{!! $subtitle !!}</div>
    @endif
    @if ($periodBadge)
        <p class="mt-2 inline-flex items-center rounded-full border border-qs-primary/20 bg-qs-soft/60 px-2.5 py-0.5 text-xs font-medium text-qs-text">
            {{ __('Active period') }}: {{ $periodBadge }}
        </p>
    @endif
</header>
