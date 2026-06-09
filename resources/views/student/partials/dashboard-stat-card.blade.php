@props([
    'label',
    'value',
    'icon',
    'tone' => 'slate',
    'href' => null,
    'linkLabel' => null,
    'hint' => null,
    'eyebrow' => null,
    'minimal' => false,
])

@php
    $tones = [
        'assessments' => ['class' => 'qs-stat-card--assessments', 'accent' => __('Assessments')],
        'assignments' => ['class' => 'qs-stat-card--assignments', 'accent' => __('Assignments')],
        'results' => ['class' => 'qs-stat-card--results', 'accent' => __('Results')],
        'notices' => ['class' => 'qs-stat-card--notices', 'accent' => __('Notices')],
        'slate' => ['class' => 'qs-stat-card--slate', 'accent' => __('Overview')],
    ];
    $palette = $tones[$tone] ?? $tones['slate'];
    $cardClass = 'qs-stat-card '.$palette['class'];
    if ($minimal) {
        $cardClass .= ' qs-stat-card--minimal';
    }
    $valueIsZero = (string) $value === '0';
    $showEyebrow = ! $minimal && $eyebrow !== false;
    $eyebrowText = $eyebrow !== null && $eyebrow !== false ? $eyebrow : $palette['accent'];
@endphp

@if ($href)
    <a href="{{ $href }}" class="{{ $cardClass }} qs-stat-card--link">
@else
    <article class="{{ $cardClass }}">
@endif
    @unless ($minimal)
        <span class="qs-stat-card__stripe" aria-hidden="true"></span>
    @endunless

    <div class="qs-stat-card__top">
        @if ($showEyebrow)
            <span class="qs-stat-card__eyebrow">{{ $eyebrowText }}</span>
        @endif
        <span class="qs-stat-card__icon" aria-hidden="true">
            <i class="fa-solid {{ $icon }}"></i>
        </span>
    </div>

    <div class="qs-stat-card__main">
        <span class="qs-stat-card__value" data-qs-stat-zero="{{ $valueIsZero ? '1' : '0' }}">{{ $value }}</span>
        <span class="qs-stat-card__label">{{ $label }}</span>
    </div>

    @if ($hint)
        <p class="qs-stat-card__hint">{{ $hint }}</p>
    @endif

    @if ($href && $linkLabel)
        <span class="qs-stat-card__cta">
            <span class="qs-stat-card__cta-label">{{ $linkLabel }}</span>
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </span>
    @endif
@if ($href)
    </a>
@else
    </article>
@endif
