@php
    /**
     * Wallet-style mobile dashboard variant (super-admin toggleable).
     * Renders only at mobile widths (<lg). Mirrors the layout of a typical
     * fintech wallet app: themed hero, action ring, recent activity, floating
     * bottom bar.
     *
     * Inputs from parent dashboard.blade.php:
     *   $user, $lastName, $initials, $statOpen, $statDue, $statPending,
     *   $statNotices, $newList, $studentNoticeCount, $semesterLabel,
     *   $mobileProfileSubline, $activeSession, $sessionExam
     */

    $walletAssessmentsHref = route('student.work.index');
    $walletAssignmentsHref = route('student.assignments.index');
    $walletResultsHref = route('student.results.index');
    $walletNoticesHref = route('student.notifications.index');
    $walletProfileHref = route('profile.edit');
    $walletHomeHref = route('dashboard');
    $walletHelpHref = route('student.help');
    $walletMaterialsEnabled = ! empty($studentMaterialsBrowseEnabled ?? false);
    $walletMaterialsHref = $walletMaterialsEnabled
        ? route('student.practice.materials.index')
        : null;

    // Read the count straight from the shared view composer so the bell
    // badge always matches the rest of the student shell, regardless of
    // whichever per-page variables happen to be available here.
    $walletUnreadNotices = (int) ($studentNoticeCount ?? $headerNoticeCount ?? $statNotices ?? 0);

    // Rotating, personable greeting — stable within an hour-bucket for
    // the same student so taps don't reroll the phrase on every render.
    $walletGreeting = \App\Support\StudentGreeting::for($user ?? auth()->user());

    // Pick the next upcoming item (the dashboardOpenAssessments digest already
    // produces ISO countdown_ends_at + countdown_prefix per row, sorted by
    // soonest countdown). Reusing that here means the hero countdown stays in
    // sync with the feed rows below.
    $walletCountdownItem = null;
    foreach ($newList as $candidate) {
        if (! empty($candidate['countdown_ends_at'])) {
            $walletCountdownItem = $candidate;
            break;
        }
    }
    $walletCountdownEndsAt = $walletCountdownItem['countdown_ends_at'] ?? null;
    $walletCountdownPrefix = $walletCountdownItem['countdown_prefix'] ?? null;
    $walletCountdownTitle = $walletCountdownItem['title'] ?? null;

    // When NO countdown is pending but there IS a quiz the student can start
    // right now, surface THAT exam in the hero with a Start button instead
    // of the generic "You are all caught up" copy. The digest sets cta_label
    // to "Start quiz" / "Start exam" / "Open assignment" for items the
    // student can act on; we skip "Instructions"-only rows (still upcoming).
    $walletStartableItem = null;
    if ($walletCountdownItem === null) {
        foreach ($newList as $candidate) {
            $cta = trim((string) ($candidate['cta_label'] ?? ''));
            if ($cta === '') {
                continue;
            }
            if (stripos($cta, 'instruction') !== false) {
                continue;
            }
            $walletStartableItem = $candidate;
            break;
        }
    }

    // Post-expiry CTA + state: digest-driven so the hero can flip from a
    // ticking clock to "Start now" / "Closed" / "Submit now" the moment the
    // timer reaches 00:00:00, without a page reload. Falls back per prefix
    // for older feeds.
    $walletCountdownPrefixKey = strtolower((string) ($walletCountdownPrefix ?? ''));
    $walletCountdownExpiredCta = (string) ($walletCountdownItem['countdown_expired_cta']
        ?? match (true) {
            str_contains($walletCountdownPrefixKey, 'close') => __('Closed'),
            str_contains($walletCountdownPrefixKey, 'due') => __('Submit now'),
            default => __('Start now'),
        });
    $walletCountdownExpiredState = (string) ($walletCountdownItem['countdown_expired_state']
        ?? match (true) {
            str_contains($walletCountdownPrefixKey, 'close') => 'closed',
            str_contains($walletCountdownPrefixKey, 'due') => 'overdue',
            default => 'ready',
        });

    // Resolve the theme slug; falls back to the safe default ('teal') so we
    // never render an empty data-theme attribute and the CSS always matches.
    $walletThemeSlug = app(\App\Services\StudentDashboardBrandingService::class)->walletTheme();
@endphp

@push('scripts')
    <style>
        /* When the wallet mobile dashboard is on, hide the shared shell
           chrome (mobile header + FAB) on the dashboard route at phone
           widths only. Tablet/desktop are unaffected.                   */
        @media (max-width: 1023px) {
            body:has(.qs-std-wallet) .qs-std-mobile-header,
            body:has(.qs-std-wallet) .qs-std-fab {
                display: none !important;
            }
            body:has(.qs-std-wallet) .qs-app-main-scroll {
                padding-bottom: max(7.5rem, env(safe-area-inset-bottom, 0px) + 6.25rem);
            }
            body:has(.qs-std-wallet) .qs-std-page-wrap {
                padding-left: 0;
                padding-right: 0;
                padding-top: 0;
            }
        }
    </style>
@endpush

<div class="qs-std-wallet lg:hidden" data-qs-wallet data-theme="{{ $walletThemeSlug }}">
    {{-- ============================== HERO ============================== --}}
    <header class="qs-std-wallet__hero" role="banner">
        <div class="qs-std-wallet__hero-top">
            <a href="{{ $walletProfileHref }}" class="qs-std-wallet__greet" aria-label="{{ __('View profile') }}">
                <span class="qs-std-wallet__avatar" aria-hidden="true">
                    @if (filled($user->face_image_path ?? null))
                        <img src="{{ route('profile.face-image') }}" alt="" />
                    @else
                        <span>{{ $initials }}</span>
                    @endif
                </span>
                <span class="qs-std-wallet__greet-text">
                    <span class="qs-std-wallet__greet-lead">{{ $walletGreeting['lead'] }}{{ $walletGreeting['sep'] }}</span>
                    <span class="qs-std-wallet__greet-name">{{ $lastName }}</span>
                </span>
            </a>

            <a href="{{ $walletNoticesHref }}" class="qs-std-wallet__bell" aria-label="{{ __('View notifications (:n unread)', ['n' => $walletUnreadNotices]) }}">
                <i class="fa-solid fa-bell" aria-hidden="true"></i>
                @if ($walletUnreadNotices > 0)
                    <span class="qs-std-wallet__bell-dot" aria-hidden="true">{{ $walletUnreadNotices > 9 ? '9+' : $walletUnreadNotices }}</span>
                @endif
            </a>
        </div>

        {{-- ============================== HERO COUNTDOWN ==============================
             The hero is now a single focused surface: the next upcoming item
             (or active session) — title first, live countdown right under it,
             and a tiny "opens in / due in" caption below. No background card,
             no static worklist counter, no year hierarchy, no extra CTAs.
             Everything is derived from the soonest item in the digest data.
        --}}
        @php
            $heroSession = $activeSession ?? null;
            $heroSessionExam = $heroSession?->exam;

            $walletHeroShowClock = false;
            $walletHeroShowStart = false;
            $walletHeroStartLabel = null;
            $walletHeroStartIcon = 'fa-circle-play';
            // Idle = nothing to do right now ("You are all caught up").
            // The whole hero stays as a plain <div> so a stray tap on
            // empty hero copy does NOT navigate the student away.
            $walletHeroIdle = false;
            $walletHeroHref = null;

            if ($heroSession !== null && $heroSessionExam !== null) {
                // Active session takes priority — only the Resume pill is
                // clickable, the rest of the hero stays informational so a
                // stray tap doesn't navigate the student away.
                $walletHeroHref = route('student.exam.take', $heroSession);
                $walletHeroTitle = (string) $heroSessionExam->title;
                $walletHeroCaption = $heroSession->status === 'paused'
                    ? __('Timer paused')
                    : __('In progress');
                $walletHeroShowStart = true;
                $walletHeroStartLabel = $heroSession->status === 'paused'
                    ? __('Resume')
                    : __('Continue');
                $walletHeroStartIcon = 'fa-circle-play';
            } elseif ($walletCountdownItem !== null && $walletCountdownEndsAt) {
                // Upcoming with countdown — the JS-driven live clock will
                // automatically swap to a Start CTA on expire (qs-std-wallet
                // __hero-expired markup is pre-rendered below).
                $walletHeroHref = (string) ($walletCountdownItem['href'] ?? $walletAssessmentsHref);
                $walletHeroTitle = (string) $walletCountdownTitle;
                $walletHeroCaption = (string) $walletCountdownPrefix;
                $walletHeroShowClock = true;
            } elseif ($walletStartableItem !== null) {
                // No countdown, but the digest has a quiz the student can
                // start RIGHT NOW — show that exam with a Start button so
                // the hero is always actionable. This is the case the user
                // saw fall back to "You are all caught up" by mistake.
                $walletHeroHref = (string) ($walletStartableItem['href'] ?? $walletAssessmentsHref);
                $walletHeroTitle = (string) $walletStartableItem['title'];
                $walletHeroCaption = trim((string) ($walletStartableItem['course_line'] ?? ''))
                    ?: __('Ready to start');
                $walletHeroShowStart = true;
                $walletHeroStartLabel = (string) ($walletStartableItem['cta_label'] ?? __('Start'));
                $walletHeroStartIcon = (string) ($walletStartableItem['type_label'] ?? '') === (string) __('Assignment')
                    ? 'fa-file-pen'
                    : 'fa-circle-play';
            } else {
                $walletHeroIdle = true;
                $walletHeroTitle = __('You are all caught up');
                $walletHeroCaption = __('No upcoming items right now');
            }
        @endphp

        {{-- The hero is ALWAYS rendered as a plain <div> now. A countdown
             ticking on the surface doesn't take the student anywhere on
             tap — only the inline Start / Resume / Submit pill is a real
             link. This keeps stray taps on the timer copy from sending the
             student off to the worklist or exam page by mistake. --}}
        <div
            class="qs-std-wallet__hero-focus {{ ($walletHeroIdle || (! $walletHeroShowClock && ! $walletHeroShowStart)) ? 'qs-std-wallet__hero-focus--idle' : '' }}"
            data-qs-wallet-focus
            @if ($walletHeroIdle) aria-live="polite" @endif
        >
            <span class="qs-std-wallet__hero-title">{{ $walletHeroTitle }}</span>

            @if ($walletHeroShowStart && ! $walletHeroIdle)
                {{-- The ONLY tappable element in the hero. Resume an active
                     session OR start a quiz that's already open — both
                     surfaces share the same pill style so the affordance
                     is consistent. --}}
                <a
                    href="{{ $walletHeroHref }}"
                    class="qs-std-wallet__hero-start qs-std-wallet__hero-start--ready"
                    data-qs-hero-cta
                >
                    <i class="fa-solid {{ $walletHeroStartIcon }}" aria-hidden="true"></i>
                    <span>{{ $walletHeroStartLabel }}</span>
                </a>
            @endif

            @if ($walletHeroShowClock)
                {{-- Mobile clock is intentionally hh:mm:ss only (no days).
                     studentDashboardCountdown.js detects the missing
                     [data-qs-countdown-days] element and rolls overflow
                     into hours so a 30h timer renders as 30:00:00.

                     The wrapping <span> is the countdown root: when the JS
                     adds `.is-expired`, CSS hides .is-live and shows
                     the inline action pill so the surface dynamically swaps
                     from a clock to a "Start now" / "Closed" / "Submit now"
                     button — no page reload. --}}
                <span
                    class="qs-std-wallet__hero-clock"
                    data-qs-countdown
                    data-qs-countdown-ends="{{ $walletCountdownEndsAt }}"
                    data-qs-countdown-prefix="{{ $walletCountdownPrefix }}"
                    data-qs-countdown-expired-state="{{ $walletCountdownExpiredState }}"
                    data-qs-countdown-keep-visible
                    role="timer"
                    aria-live="polite"
                >
                    <span class="qs-std-wallet__hero-clock-live" aria-hidden="false">
                        <span class="qs-std-wallet__hero-clock-seg" data-qs-countdown-hours>00</span>
                        <span class="qs-std-wallet__hero-clock-sep" aria-hidden="true">:</span>
                        <span class="qs-std-wallet__hero-clock-seg" data-qs-countdown-minutes>00</span>
                        <span class="qs-std-wallet__hero-clock-sep" aria-hidden="true">:</span>
                        <span class="qs-std-wallet__hero-clock-seg" data-qs-countdown-seconds>00</span>
                    </span>
                    @if ($walletCountdownExpiredState === 'closed')
                        {{-- "Closed" is informational, not actionable — keep
                             it as a non-link span so the student can't tap
                             into a dead path. --}}
                        <span class="qs-std-wallet__hero-expired qs-std-wallet__hero-expired--closed" aria-hidden="true">
                            <i class="fa-solid fa-lock"></i>
                            <span>{{ $walletCountdownExpiredCta }}</span>
                        </span>
                    @else
                        {{-- "Start now" / "Submit now" — actionable, so it
                             IS the only tappable surface in the hero once
                             the JS reveals it on expiry. --}}
                        <a
                            href="{{ $walletHeroHref }}"
                            class="qs-std-wallet__hero-expired qs-std-wallet__hero-expired--{{ $walletCountdownExpiredState }}"
                            data-qs-hero-cta
                        >
                            @if ($walletCountdownExpiredState === 'overdue')
                                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            @else
                                <i class="fa-solid fa-circle-play" aria-hidden="true"></i>
                            @endif
                            <span>{{ $walletCountdownExpiredCta }}</span>
                        </a>
                    @endif
                </span>
            @endif

            <span class="qs-std-wallet__hero-caption" data-qs-hero-caption>{{ $walletHeroCaption }}</span>
        </div>

        {{-- Worklist filter chips removed: the dedicated /work page owns the
             filtered worklist surface, and the quick-action ring below already
             surfaces Worklist + Assignments directly. Keeping a filter row
             here just duplicated those entry points. --}}
    </header>

    {{-- ============================== QUICK ACTIONS RING ==============================
         Each icon is a DISTINCT destination — Results / Notifications already live in
         the bottom nav + the top bell, so duplicating them here would just waste tap
         targets. We surface Worklist, Assignments, Materials (if enabled), and Help.
    --}}
    <section class="qs-std-wallet__actions-card" aria-label="{{ __('Quick actions') }}">
        <a href="{{ $walletAssessmentsHref }}" class="qs-std-wallet__action">
            <span class="qs-std-wallet__action-circle qs-std-wallet__action-circle--assessments" aria-hidden="true">
                <i class="fa-solid fa-clipboard-list"></i>
            </span>
            <span class="qs-std-wallet__action-label">{{ __('Worklist') }}</span>
        </a>
        <a href="{{ $walletAssignmentsHref }}" class="qs-std-wallet__action">
            <span class="qs-std-wallet__action-circle qs-std-wallet__action-circle--assignments" aria-hidden="true">
                <i class="fa-solid fa-file-pen"></i>
            </span>
            <span class="qs-std-wallet__action-label">{{ __('Assignments') }}</span>
        </a>
        {{-- Materials always renders on the wallet ring, sitting just before
             Help on the right. We point at the materials index when browse is
             enabled and fall back to the help page otherwise so the tile is
             never a dead-end. --}}
        <a href="{{ $walletMaterialsHref ?? $walletHelpHref }}" class="qs-std-wallet__action">
            <span class="qs-std-wallet__action-circle qs-std-wallet__action-circle--results" aria-hidden="true">
                <i class="fa-solid fa-folder-open"></i>
            </span>
            <span class="qs-std-wallet__action-label">{{ __('Materials') }}</span>
        </a>
        <a href="{{ $walletHelpHref }}" class="qs-std-wallet__action">
            <span class="qs-std-wallet__action-circle qs-std-wallet__action-circle--notices" aria-hidden="true">
                <i class="fa-solid fa-circle-question"></i>
            </span>
            <span class="qs-std-wallet__action-label">{{ __('Help') }}</span>
        </a>
    </section>

    {{-- ============================== RECENT ACTIVITY ============================== --}}
    <section class="qs-std-wallet__activity-card" aria-labelledby="qs-wallet-activity-heading">
        <header class="qs-std-wallet__activity-head">
            <h2 id="qs-wallet-activity-heading" class="qs-std-wallet__activity-title">{{ __('Recent activity') }}</h2>
            <a href="{{ $walletAssessmentsHref }}" class="qs-std-wallet__activity-more">{{ __('See all') }}</a>
        </header>

        @if ($newList === [])
            <p class="qs-std-wallet__activity-empty">
                {{ __('No new assessments right now. You are all caught up.') }}
            </p>
        @else
            <ul class="qs-std-wallet__activity-list" role="list">
                @foreach (array_slice($newList, 0, 5) as $qa)
                    @php
                        $rowTypeLabel = (string) ($qa['type_label'] ?? '');
                        $rowTypeKey = strtolower($rowTypeLabel);
                        [$rowIcon, $rowTone] = match (true) {
                            str_contains($rowTypeKey, 'assignment') => ['fa-file-pen', 'assignments'],
                            str_contains($rowTypeKey, 'mid') => ['fa-flask', 'results'],
                            str_contains($rowTypeKey, 'exam') => ['fa-shield-halved', 'assessments'],
                            str_contains($rowTypeKey, 'quiz') => ['fa-clipboard-question', 'assessments'],
                            default => ['fa-clipboard-list', 'assessments'],
                        };
                        $rowMeta = $qa['published_at'] ?? null;
                        $rowAction = $qa['cta_label'] ?? null;
                        // Treat any "Start" / "Open" CTA as an actionable
                        // pill (theme accent); otherwise (e.g. "Instructions")
                        // fall back to a quieter ghost label.
                        $rowActionKey = strtolower((string) $rowAction);
                        $rowActionIsStart = $rowAction !== null && (
                            str_contains($rowActionKey, 'start')
                            || str_contains($rowActionKey, 'open')
                            || str_contains($rowActionKey, 'resume')
                            || str_contains($rowActionKey, 'submit')
                        );
                    @endphp
                    <li class="qs-std-wallet__activity-item">
                        <a href="{{ $qa['href'] }}" class="qs-std-wallet__activity-link">
                            <span class="qs-std-wallet__activity-icon qs-std-wallet__activity-icon--{{ $rowTone }}" aria-hidden="true">
                                <i class="fa-solid {{ $rowIcon }}"></i>
                            </span>
                            <span class="qs-std-wallet__activity-body">
                                <span class="qs-std-wallet__activity-name">{{ $qa['title'] }}</span>
                                @if (filled($qa['course_line'] ?? null))
                                    <span class="qs-std-wallet__activity-sub">{{ $qa['course_line'] }}</span>
                                @endif
                                @if ($rowMeta)
                                    <span class="qs-std-wallet__activity-date">{{ $rowMeta }}</span>
                                @endif
                            </span>
                            @if ($rowAction)
                                <span @class([
                                    'qs-std-wallet__activity-cta',
                                    'qs-std-wallet__activity-cta--primary' => $rowActionIsStart,
                                    'qs-std-wallet__activity-cta--ghost' => ! $rowActionIsStart,
                                ])>
                                    @if ($rowActionIsStart)
                                        <i class="fa-solid fa-circle-play" aria-hidden="true"></i>
                                    @endif
                                    <span>{{ $rowAction }}</span>
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ============================== FLOATING BOTTOM NAV ==============================
         Five total destinations: Home, Materials, [center FAB = Worklist/Resume],
         Results, Profile. The FAB IS the primary worklist surface (it resumes
         an active session, jumps to the next upcoming item, or opens the
         worklist when idle), so we do NOT also include a "Work" tab — that
         would visually duplicate the FAB's icon on the left side of the bar.
    --}}
    @php
        $walletFabHref = $activeSession !== null
            ? route('student.exam.take', $activeSession)
            : ($walletCountdownItem['href'] ?? $walletAssessmentsHref);
        $walletFabIcon = $activeSession !== null
            ? 'fa-play'
            : ($walletCountdownItem !== null ? 'fa-bolt' : 'fa-clipboard-list');
        $walletFabLabel = $activeSession !== null
            ? __('Resume active session')
            : ($walletCountdownItem !== null ? __('Open next item') : __('Open worklist'));

        // Materials tab href + tooltip. When the materials browse feature is
        // disabled, point at the help page so the tab is never a dead end.
        $walletNavMaterialsHref = $walletMaterialsHref ?? $walletHelpHref;
        $walletNavMaterialsLabel = $walletMaterialsHref !== null
            ? __('Materials')
            : __('Help');
        $walletNavMaterialsIcon = $walletMaterialsHref !== null
            ? 'fa-folder-open'
            : 'fa-circle-question';
    @endphp
    <nav class="qs-std-wallet__nav" aria-label="{{ __('Student quick navigation') }}">
        <div class="qs-std-wallet__nav-inner">
            <a href="{{ $walletHomeHref }}" class="qs-std-wallet__nav-item is-active">
                <span class="qs-std-wallet__nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>
                <span class="qs-std-wallet__nav-label">{{ __('Home') }}</span>
            </a>
            <a href="{{ $walletNavMaterialsHref }}" class="qs-std-wallet__nav-item">
                <span class="qs-std-wallet__nav-icon"><i class="fa-solid {{ $walletNavMaterialsIcon }}" aria-hidden="true"></i></span>
                <span class="qs-std-wallet__nav-label">{{ $walletNavMaterialsLabel }}</span>
            </a>

            <div class="qs-std-wallet__nav-spacer" aria-hidden="true"></div>

            <a href="{{ $walletResultsHref }}" class="qs-std-wallet__nav-item">
                <span class="qs-std-wallet__nav-icon"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i></span>
                <span class="qs-std-wallet__nav-label">{{ __('Results') }}</span>
            </a>
            <a href="{{ $walletProfileHref }}" class="qs-std-wallet__nav-item">
                <span class="qs-std-wallet__nav-icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
                <span class="qs-std-wallet__nav-label">{{ __('Profile') }}</span>
            </a>
        </div>

        <a href="{{ $walletFabHref }}" class="qs-std-wallet__nav-fab" aria-label="{{ $walletFabLabel }}">
            <i class="fa-solid {{ $walletFabIcon }}" aria-hidden="true"></i>
        </a>
    </nav>
</div>
