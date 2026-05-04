@props([
    'interactive' => false,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'span';
    $base = 'brand-logo inline-flex items-baseline gap-0 font-bold tracking-tight';
    $classes = $interactive ? $base.' brand-logo--interactive' : $base;
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
    aria-label="QuizSnap"
>
    @if ($interactive)
        @foreach (mb_str_split('QuizSnap') as $i => $ch)
            <span
                class="brand-logo__char {{ $i < 4 ? 'brand-logo__char--quiz' : 'brand-logo__char--snap' }}"
                style="--brand-i: {{ $i }}"
                aria-hidden="true"
            >{{ $ch }}</span>
        @endforeach
    @else
        <span class="text-qs-primary">Quiz</span><span class="text-[#1e5a3a]">Snap</span>
    @endif
</{{ $tag }}>
