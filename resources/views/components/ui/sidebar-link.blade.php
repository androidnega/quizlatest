@props([
    'href',
    'active' => false,
    /** Icon name for ui.nav-icon (matches icon keys used in nav arrays). */
    'icon' => 'circle',
    /** When true, label is always visible (e.g. mobile drawer; ignores desktop collapse). */
    'alwaysShowLabel' => false,
])

@php
    $state = $active
        ? 'relative border border-transparent bg-qs-primary/[0.07] text-qs-primary before:absolute before:inset-y-2 before:left-0 before:w-[3px] before:rounded-r-full before:bg-qs-primary'
        : 'border border-transparent text-qs-text hover:bg-qs-card/90';
    $base = [
        'qs-sidebar-nav-link group relative flex min-h-[44px] w-full select-none items-center rounded-lg text-sm font-medium',
        $state,
    ];
    if ($alwaysShowLabel) {
        $base[] = 'gap-3 px-3';
    } else {
        /** Desktop default expanded layout; collapsed uses html.qs-shell-sidebar-collapsed (see app.css) so layout is correct before Alpine. */
        $base[] = 'gap-3 px-3';
    }
@endphp

<a href="{{ $href }}" {{ $attributes->class($base) }}>
    <span
        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-qs-primary {{ $active ? 'bg-qs-primary/12 text-qs-primary' : 'bg-qs-soft/60 text-qs-primary' }}"
    >
        <x-ui.nav-icon :name="$icon" />
    </span>
    @if ($alwaysShowLabel)
        <span class="min-w-0 flex-1 truncate text-left">{{ $slot }}</span>
    @else
        <span class="qs-sidebar-nav-label min-w-0 flex-1 truncate text-left">{{ $slot }}</span>
    @endif
</a>
