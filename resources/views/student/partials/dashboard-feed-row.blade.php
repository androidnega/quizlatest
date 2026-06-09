@props([
    'href' => null,
    'title',
    'subtitle' => null,
    'meta' => null,
    'action' => null,
    'countdownEndsAt' => null,
    'countdownPrefix' => null,
    'countdownExpiredCta' => null,
    'countdownExpiredState' => null,
    'icon' => 'fa-clipboard-list',
    'tone' => 'assessments',
    'tag' => null,
])

@php
    $showCountdown = filled($countdownEndsAt) && filled($countdownPrefix);
    $allowedTones = ['assessments', 'assignments', 'results', 'notices', 'materials'];
    $resolvedTone = in_array($tone, $allowedTones, true) ? $tone : 'assessments';
    $rowClasses = 'qs-std-feed-card qs-std-feed-card--'.$resolvedTone.' group';

    // Per-prefix fallback so older callers that only pass a prefix still
    // get a sane post-expiry CTA when the countdown reaches 0 in-browser.
    if ($showCountdown) {
        $rowPrefixKey = strtolower((string) $countdownPrefix);
        $rowExpiredCta = $countdownExpiredCta !== null && $countdownExpiredCta !== ''
            ? (string) $countdownExpiredCta
            : match (true) {
                str_contains($rowPrefixKey, 'close') => __('Closed'),
                str_contains($rowPrefixKey, 'due') => __('Submit now'),
                default => __('Start'),
            };
        $rowExpiredState = $countdownExpiredState !== null && $countdownExpiredState !== ''
            ? (string) $countdownExpiredState
            : match (true) {
                str_contains($rowPrefixKey, 'close') => 'closed',
                str_contains($rowPrefixKey, 'due') => 'overdue',
                default => 'ready',
            };
    }
@endphp

<li class="qs-std-feed-card-cell">
    @if ($href)
        <a href="{{ $href }}" class="{{ $rowClasses }}">
    @else
        <div class="{{ $rowClasses }}">
    @endif
        <span class="qs-std-feed-card__icon" aria-hidden="true">
            <i class="fa-solid {{ $icon }}"></i>
        </span>

        <div class="qs-std-feed-card__body">
            @if (filled($tag))
                <span class="qs-std-feed-card__tag">{{ $tag }}</span>
            @endif
            <p class="qs-std-feed-card__title">{{ $title }}</p>

            @if ($subtitle)
                <p class="qs-std-feed-card__subtitle">{{ $subtitle }}</p>
            @endif

            @if ($showCountdown)
                {{-- Live clock + pre-rendered post-expiry CTA. When
                     studentDashboardCountdown.js adds `.is-expired` (timer
                     hit 00:00:00), CSS hides .qs-wl-countdown__live and
                     reveals .qs-wl-countdown__expired so the surface
                     transforms from a ticking clock into a "Start" button —
                     never removed, never left at "00:00:00". --}}
                <div
                    class="qs-wl-countdown qs-wl-countdown--compact"
                    data-qs-countdown
                    data-qs-countdown-ends="{{ $countdownEndsAt }}"
                    data-qs-countdown-expired-state="{{ $rowExpiredState }}"
                    role="timer"
                    aria-label="{{ $countdownPrefix }}"
                >
                    <div class="qs-wl-countdown__live">
                        <span class="qs-wl-countdown__label">
                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                            {{ $countdownPrefix }}
                        </span>
                        <span class="qs-wl-countdown__grid">
                            <span class="qs-wl-countdown__box">
                                <span class="qs-wl-countdown__num" data-qs-countdown-days>00</span>
                                <span class="qs-wl-countdown__cap">{{ __('days') }}</span>
                            </span>
                            <span class="qs-wl-countdown__sep" aria-hidden="true">:</span>
                            <span class="qs-wl-countdown__box">
                                <span class="qs-wl-countdown__num" data-qs-countdown-hours>00</span>
                                <span class="qs-wl-countdown__cap">{{ __('hrs') }}</span>
                            </span>
                            <span class="qs-wl-countdown__sep" aria-hidden="true">:</span>
                            <span class="qs-wl-countdown__box">
                                <span class="qs-wl-countdown__num" data-qs-countdown-minutes>00</span>
                                <span class="qs-wl-countdown__cap">{{ __('min') }}</span>
                            </span>
                            <span class="qs-wl-countdown__sep" aria-hidden="true">:</span>
                            <span class="qs-wl-countdown__box">
                                <span class="qs-wl-countdown__num" data-qs-countdown-seconds>00</span>
                                <span class="qs-wl-countdown__cap">{{ __('sec') }}</span>
                            </span>
                        </span>
                    </div>
                    <span class="qs-wl-countdown__expired qs-wl-countdown__expired--{{ $rowExpiredState }}" aria-hidden="true">
                        @if ($rowExpiredState === 'closed')
                            <i class="fa-solid fa-lock"></i>
                        @elseif ($rowExpiredState === 'overdue')
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        @else
                            <i class="fa-solid fa-circle-play"></i>
                        @endif
                        <span>{{ $rowExpiredCta }}</span>
                    </span>
                </div>
            @endif

            @if ($meta)
                <p class="qs-std-feed-card__meta">{{ $meta }}</p>
            @endif
        </div>

        @if ($action)
            <span class="qs-std-feed-card__action">
                <span>{{ $action }}</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </span>
        @endif
    @if ($href)
        </a>
    @else
        </div>
    @endif
</li>
