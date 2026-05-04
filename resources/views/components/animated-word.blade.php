@props([
    'text',
    'as' => 'span',
    'variant' => null,
])

@php
    /** @var list<string> $chars */
    $chars = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $wordClasses = trim('animated-word '.($variant === 'hero' ? 'animated-word--hero' : ''));
    $tag = in_array($as, ['span', 'strong', 'em', 'b', 'i'], true) ? $as : 'span';
    $inner = '';
    foreach ($chars as $i => $ch) {
        if ($ch === ' ') {
            $inner .= '<span class="animated-word__space" aria-hidden="true">&nbsp;</span>';
        } else {
            $inner .= '<span class="animated-word__char" style="--aw-i: '.$i.'" aria-hidden="true">'.e($ch).'</span>';
        }
    }
    $attrBag = $attributes->merge(['class' => $wordClasses]);
@endphp
{!! '<'.$tag.$attrBag.' aria-label="'.e($text).'">'.$inner.'</'.$tag.'>' !!}
