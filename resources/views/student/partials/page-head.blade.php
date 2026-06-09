@props([
    'title',
    'subtitle' => null,
])

<header class="qs-std-page-head">
    <h1 class="qs-std-page-head__title">{{ $title }}</h1>
    @if ($subtitle)
        <p class="qs-std-page-head__sub">{{ $subtitle }}</p>
    @endif
</header>
