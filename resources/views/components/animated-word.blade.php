@props([
    'text',
    'as' => 'span',
])

@php
    /** @var list<string> $chars */
    $chars = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
@endphp

<{{ $as }} {{ $attributes->merge(['class' => 'animated-word']) }} aria-label="{{ $text }}">
    @foreach ($chars as $i => $ch)
        @if ($ch === ' ')
            <span class="animated-word__space" aria-hidden="true">&nbsp;</span>
        @else
            <span class="animated-word__char" style="--aw-i: {{ $i }}" aria-hidden="true">{{ $ch }}</span>
        @endif
    @endforeach
</{{ $as }}>
