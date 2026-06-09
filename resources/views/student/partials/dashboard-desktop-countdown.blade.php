@php
    /**
     * Desktop hero countdown card — the cream/dark sleek timer that lives
     * at the top of the student desktop dashboard. Mirrors the wallet hero
     * countdown but in the editorial cream-card style (matches the image
     * the user requested as the desktop reference).
     *
     * All inputs are dynamic and pulled from the dashboard view:
     *   $countdownItem   array|null  the soonest upcoming/open assessment
     *                                from $newList (countdown_* keys etc.)
     *   $activeSession   ?ExamSession  if set, takes priority as "Resume"
     */

    // Decide what the card should display in priority order:
    //   a) active in-progress session -> resume CTA (no countdown)
    //   b) next upcoming item with a real ISO target -> live countdown
    //   c) startable item (no countdown but can start now) -> Start CTA
    //   d) nothing -> render nothing at all (no empty card)
    $heroSession = $activeSession ?? null;
    $heroItem = $countdownItem ?? null;
    $heroStartable = $startableItem ?? null;

    // Flags read by the markup below. The default-null block sits last.
    $heroShowStart = false;
    $heroStartLabel = null;
    $heroStartIcon = 'fa-circle-play';

    $heroExam = $heroSession?->exam;

    if ($heroSession !== null && $heroExam !== null) {
        $heroHref = route('student.exam.take', $heroSession);
        $heroTitle = (string) $heroExam->title;
        $heroCourseCode = (string) ($heroExam->course?->code ?? '');
        $heroCourseName = (string) ($heroExam->course?->title ?? '');
        $heroTypeLabel = match ((string) ($heroExam->assessment_type ?? '')) {
            'assignment' => __('Assignment'),
            'quiz' => __('Quiz'),
            'mid' => __('Mid-semester'),
            'exam' => __('Exam'),
            default => __('Assessment'),
        };
        $heroStatusLabel = $heroSession->status === 'paused' ? __('Paused') : __('In progress');
        $heroStatusTone = $heroSession->status === 'paused' ? 'warning' : 'active';
        $heroCountdownEnds = null;
        $heroCountdownPrefix = null;
        $heroCtaLabel = __('Resume now');
        // Resume becomes the lone tappable pill so the rest of the card
        // (course chip, title, status row) stays informational.
        $heroShowStart = true;
        $heroStartLabel = $heroSession->status === 'paused' ? __('Resume') : __('Continue');
        $heroStartIcon = 'fa-circle-play';
    } elseif ($heroItem !== null && ! empty($heroItem['countdown_ends_at'])) {
        $heroHref = (string) ($heroItem['href'] ?? '#');
        $heroTitle = (string) ($heroItem['title'] ?? __('Next assessment'));
        $heroCourseLine = (string) ($heroItem['course_line'] ?? '');

        // The digest joins code + title with " — "; split so we can show
        // them in distinct slots (left chip vs. centre line).
        $courseParts = $heroCourseLine !== '' ? explode(' — ', $heroCourseLine, 2) : [];
        $heroCourseCode = $courseParts[0] ?? '';
        $heroCourseName = $courseParts[1] ?? $courseParts[0] ?? '';
        if ($heroCourseCode !== '' && $heroCourseName === $heroCourseCode) {
            $heroCourseName = '';
        }

        $heroTypeLabel = (string) ($heroItem['type_label'] ?? __('Assessment'));
        $heroCountdownEnds = (string) $heroItem['countdown_ends_at'];
        $heroCountdownPrefix = (string) ($heroItem['countdown_prefix'] ?? __('Opens in'));
        $heroCtaLabel = (string) ($heroItem['cta_label'] ?? __('Open'));

        // Post-expiry CTA + state come straight from the digest so the same
        // mapping powers every surface (mobile wallet + desktop card +
        // future widgets). Fallback: derive locally for backwards-compat.
        $prefixKey = strtolower($heroCountdownPrefix);
        $heroExpiredCta = (string) ($heroItem['countdown_expired_cta']
            ?? match (true) {
                str_contains($prefixKey, 'close') => __('Closed'),
                str_contains($prefixKey, 'due') => __('Submit now'),
                default => __('Start now'),
            });
        $heroExpiredState = (string) ($heroItem['countdown_expired_state']
            ?? match (true) {
                str_contains($prefixKey, 'close') => 'closed',
                str_contains($prefixKey, 'due') => 'overdue',
                default => 'ready',
            });

        $heroStatusLabel = match (true) {
            str_contains($prefixKey, 'due'), str_contains($prefixKey, 'close') => __('Due soon'),
            str_contains($prefixKey, 'open') => __('Verified'),
            default => __('Upcoming'),
        };
        $heroStatusTone = match (true) {
            str_contains($prefixKey, 'due'), str_contains($prefixKey, 'close') => 'urgent',
            default => 'active',
        };
    } elseif ($heroStartable !== null) {
        // No countdown, but a quiz is startable right now → render the
        // ticket with a Start CTA in the dark right block (same place the
        // clock would have been). Keeps the desktop hero always actionable.
        $heroHref = (string) ($heroStartable['href'] ?? '#');
        $heroTitle = (string) ($heroStartable['title'] ?? __('Next assessment'));
        $heroCourseLine = (string) ($heroStartable['course_line'] ?? '');
        $courseParts = $heroCourseLine !== '' ? explode(' — ', $heroCourseLine, 2) : [];
        $heroCourseCode = $courseParts[0] ?? '';
        $heroCourseName = $courseParts[1] ?? $courseParts[0] ?? '';
        if ($heroCourseCode !== '' && $heroCourseName === $heroCourseCode) {
            $heroCourseName = '';
        }
        $heroTypeLabel = (string) ($heroStartable['type_label'] ?? __('Assessment'));
        $heroCountdownEnds = null;
        $heroCountdownPrefix = null;
        $heroCtaLabel = (string) ($heroStartable['cta_label'] ?? __('Start'));
        $heroStatusLabel = __('Ready');
        $heroStatusTone = 'ready';

        $heroShowStart = true;
        $heroStartLabel = $heroCtaLabel;
        $heroStartIcon = stripos($heroTypeLabel, (string) __('Assignment')) !== false
            ? 'fa-file-pen'
            : 'fa-circle-play';
    } else {
        return;
    }

    // Compact code chip — first 2 alphanumeric chars of the course code,
    // or the first two of the title if no course code is set.
    $codeSource = $heroCourseCode !== '' ? $heroCourseCode : $heroTitle;
    $heroChip = \Illuminate\Support\Str::of($codeSource)
        ->upper()
        ->replaceMatches('/[^A-Z0-9]/', '')
        ->substr(0, 2)
        ->toString();
    if ($heroChip === '') {
        $heroChip = '·';
    }
@endphp

<section class="qs-std-hero-countdown qs-std-hero-countdown--tone-{{ $heroStatusTone }} mb-6 hidden lg:block" aria-label="{{ __('Next assessment countdown') }}">
    {{-- The desktop ticket card no longer wraps everything in an <a>. The
         course chip / title / type / clock are informational only; only the
         inline Start / Resume / Submit pill is a real link. Avoids the user
         accidentally clicking the whole card and being navigated away while
         the timer is still counting down. --}}
    <div class="qs-std-hero-countdown__card" data-qs-hero-card>
        <span class="qs-std-hero-countdown__chip" aria-hidden="true">{{ $heroChip }}</span>

        <div class="qs-std-hero-countdown__slot">
            <span class="qs-std-hero-countdown__slot-label">{{ __('Course') }}</span>
            <span class="qs-std-hero-countdown__slot-value">
                {{ $heroCourseName !== '' ? $heroCourseName : ($heroCourseCode !== '' ? $heroCourseCode : __('—')) }}
            </span>
        </div>

        <div class="qs-std-hero-countdown__slot qs-std-hero-countdown__slot--type">
            <span class="qs-std-hero-countdown__slot-label">{{ __('Exam type') }}</span>
            <span class="qs-std-hero-countdown__slot-value">{{ $heroTypeLabel }}</span>
            @if ($heroTitle !== ($heroCourseName ?: '') && $heroTitle !== '')
                <span class="qs-std-hero-countdown__slot-sub">{{ $heroTitle }}</span>
            @endif
        </div>

        <div
            class="qs-std-hero-countdown__timer"
            @if ($heroCountdownEnds)
                data-qs-countdown
                data-qs-countdown-ends="{{ $heroCountdownEnds }}"
                data-qs-countdown-prefix="{{ $heroCountdownPrefix }}"
                data-qs-countdown-expired-state="{{ $heroExpiredState }}"
                data-qs-countdown-keep-visible
            @endif
        >
            {{-- LIVE state: status pill + ticking clock + optional days overflow.
                 The CSS sibling rules use `.is-expired` (set by the JS when
                 remaining hits 0) to hide this block and reveal the expired
                 CTA block below. No reload required.                       --}}
            <span class="qs-std-hero-countdown__status qs-std-hero-countdown__live">
                <span class="qs-std-hero-countdown__status-dot" aria-hidden="true"></span>
                {{ $heroStatusLabel }}
            </span>
            @if ($heroCountdownEnds)
                <span class="qs-std-hero-countdown__clock tabular-nums" role="timer" aria-live="off">
                    <span data-qs-countdown-hours>00</span><span class="qs-std-hero-countdown__clock-sep">:</span><span data-qs-countdown-minutes>00</span><span class="qs-std-hero-countdown__clock-sep">:</span><span data-qs-countdown-seconds>00</span>
                </span>
                <span class="qs-std-hero-countdown__clock-days tabular-nums" aria-hidden="true">
                    <span data-qs-countdown-days>00</span>{{ __('d') }}
                </span>

                {{-- EXPIRED state: revealed when JS adds .is-expired. The CTA
                     label + state attribute are server-rendered from the
                     digest so the entire transition is data-driven. Only the
                     actionable states (ready / overdue) become a real <a>;
                     "closed" is just informational so we keep it as a span. --}}
                @if ($heroExpiredState === 'closed')
                    <span class="qs-std-hero-countdown__expired qs-std-hero-countdown__expired--closed" aria-hidden="true">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span>{{ $heroExpiredCta }}</span>
                    </span>
                @else
                    <a
                        href="{{ $heroHref }}"
                        class="qs-std-hero-countdown__expired qs-std-hero-countdown__expired--{{ $heroExpiredState }}"
                        data-qs-hero-cta
                    >
                        @if ($heroExpiredState === 'overdue')
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        @else
                            <i class="fa-solid fa-circle-play" aria-hidden="true"></i>
                        @endif
                        <span>{{ $heroExpiredCta }}</span>
                    </a>
                @endif
            @elseif ($heroShowStart)
                {{-- Permanent Start / Resume pill — server-rendered when the
                     digest has no live countdown but the assessment is
                     actionable right now (or there's an active session to
                     resume). This is the ONLY tappable element in the card. --}}
                <a
                    href="{{ $heroHref }}"
                    class="qs-std-hero-countdown__start"
                    data-qs-hero-cta
                >
                    <i class="fa-solid {{ $heroStartIcon }}" aria-hidden="true"></i>
                    <span>{{ $heroStartLabel }}</span>
                </a>
            @else
                <span class="qs-std-hero-countdown__clock qs-std-hero-countdown__clock--cta">{{ $heroCtaLabel }}</span>
            @endif
        </div>
    </div>
</section>
