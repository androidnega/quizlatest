@props([
    'href',
    'active' => false,
    /** Icon name for ui.nav-icon (matches icon keys used in nav arrays). */
    'icon' => 'circle',
    'closeDrawer' => false,
    /** When true, label is always visible (e.g. mobile drawer; ignores desktop collapse). */
    'alwaysShowLabel' => false,
])

@php
    $state = $active
        ? 'border border-qs-primary/25 bg-qs-card text-qs-text shadow-sm ring-1 ring-qs-primary/15'
        : 'border border-transparent text-qs-text hover:border-qs-soft hover:bg-qs-card';
    $base = [
        'group relative flex min-h-[44px] w-full items-center rounded-lg text-sm font-medium transition',
        $state,
    ];
    if ($alwaysShowLabel) {
        $base[] = 'gap-3 px-3';
    }
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->class($base) }}
    @unless ($alwaysShowLabel)
        x-bind:class="typeof collapsed !== 'undefined' && collapsed ? 'justify-center px-0' : 'gap-3 px-3'"
    @endunless
    @if ($closeDrawer)
        @click="drawerOpen = false"
    @endif
>
    <span
        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-qs-primary transition {{ $active ? 'bg-qs-primary/10 text-qs-primary' : 'bg-qs-soft/50 text-qs-primary' }}"
        @unless ($alwaysShowLabel)
            x-bind:class="typeof collapsed !== 'undefined' && collapsed ? 'mx-auto' : ''"
        @endunless
    >
        <x-ui.nav-icon :name="$icon" />
    </span>
    @if ($alwaysShowLabel)
        <span class="min-w-0 flex-1 truncate text-left">{{ $slot }}</span>
    @else
        <span
            class="min-w-0 flex-1 truncate text-left"
            x-show="typeof collapsed === 'undefined' || ! collapsed"
            x-cloak
        >{{ $slot }}</span>
    @endif
</a>
