@php
    /** @var array<string, array<int, array<string, mixed>>> $studentAssessmentDeck */
    $deck = $studentAssessmentDeck ?? [];

    /* Worklist is the "what to do" view. Submitted + graded items belong on
       the dedicated Results page (/dashboard/results) so the two surfaces
       don't show the same cards. We still surface "closed/missed" as a
       reminder, since those aren't results either.                       */
    $sectionMeta = [
        'active_now' => ['icon' => 'fa-bolt', 'status' => __('LIVE')],
        'continue' => ['icon' => 'fa-rotate-right', 'status' => __('ONGOING')],
        'assignments_due' => ['icon' => 'fa-file-pen', 'status' => __('DUE')],
        'upcoming' => ['icon' => 'fa-calendar', 'status' => __('SOON')],
        'closed_missed' => ['icon' => 'fa-circle-xmark', 'status' => __('MISSED')],
    ];

    $flatRows = [];
    foreach ($sectionMeta as $key => $meta) {
        foreach (($deck[$key] ?? []) as $row) {
            $flatRows[] = ['key' => $key, 'meta' => $meta, 'row' => $row];
        }
    }

    $submittedHistoryCount = count($deck['submitted_work'] ?? []) + count($deck['results_released'] ?? []);
@endphp

<section id="student-work" class="scroll-mt-4" aria-labelledby="student-worklist-heading">
    <h2 id="student-worklist-heading" class="sr-only">{{ __('Assessments') }}</h2>

    <div class="qs-wl-panel">
        <header class="qs-wl-panel__head">
            <span class="qs-wl-panel__avatar" aria-hidden="true">
                <i class="fa-solid fa-list-check"></i>
            </span>
            <div class="qs-wl-panel__heading">
                <p class="qs-wl-panel__eyebrow">{{ __('Worklist') }}</p>
                <h2 class="qs-wl-panel__title">{{ __('What to do next') }}</h2>
            </div>
            <a href="{{ route('student.assignments.index') }}" class="qs-wl-panel__cta">
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                <span>{{ __('All assignments') }}</span>
            </a>
        </header>

        <div class="qs-wl-panel__body">
            @if ($flatRows !== [])
                <ul class="qs-wl-list">
                    @foreach ($flatRows as $entry)
                        @php
                            $key = $entry['key'];
                            $meta = $entry['meta'];
                            $row = $entry['row'];
                            $courseLine = trim((string) ($row['course_line'] ?? ''));
                            $hasCountdown = ! empty($row['countdown_ends_at']) && ! empty($row['countdown_prefix']);
                            $statusLabel = trim((string) ($row['status_label'] ?? ''));
                            $scoreLine = trim((string) ($row['score_line'] ?? ''));
                            $submissionFormat = trim((string) ($row['submission_format'] ?? ''));
                            $secondaryInfo = $hasCountdown
                                ? null
                                : trim((string) ($row['due_line'] ?? ''));
                        @endphp

                        <li class="qs-wl-item qs-wl-item--{{ $key }}">
                            <div class="qs-wl-item__head">
                                <h3 class="qs-wl-item__title">{{ $row['title'] }}</h3>
                                <span class="qs-wl-item__icon" aria-hidden="true">
                                    <i class="fa-solid {{ $meta['icon'] }}"></i>
                                </span>
                            </div>

                            @if ($courseLine !== '')
                                <p class="qs-wl-item__sub">{{ $courseLine }}</p>
                            @endif

                            <div class="qs-wl-item__pills">
                                <span class="qs-wl-pill">
                                    <span class="qs-wl-pill__dot" aria-hidden="true"></span>
                                    {{ $meta['status'] }}
                                </span>

                                @if ($hasCountdown)
                                    @php
                                        $wlPrefixKey = strtolower((string) $row['countdown_prefix']);
                                        $wlExpiredCta = (string) ($row['countdown_expired_cta']
                                            ?? match (true) {
                                                str_contains($wlPrefixKey, 'close') => __('Closed'),
                                                str_contains($wlPrefixKey, 'due') => __('Submit now'),
                                                default => __('Start'),
                                            });
                                        $wlExpiredState = (string) ($row['countdown_expired_state']
                                            ?? match (true) {
                                                str_contains($wlPrefixKey, 'close') => 'closed',
                                                str_contains($wlPrefixKey, 'due') => 'overdue',
                                                default => 'ready',
                                            });
                                    @endphp
                                    {{-- Same dynamic-swap pattern as the
                                         dashboard feed rows: live clock +
                                         pre-rendered Start CTA. CSS reveals
                                         the CTA when the JS adds .is-expired. --}}
                                    <span
                                        class="qs-wl-pill qs-wl-pill--time qs-std-dash-countdown"
                                        data-qs-countdown
                                        data-qs-countdown-ends="{{ $row['countdown_ends_at'] }}"
                                        data-qs-countdown-expired-state="{{ $wlExpiredState }}"
                                        data-qs-countdown-keep-visible
                                        role="timer"
                                        aria-label="{{ $row['countdown_prefix'] }}"
                                    >
                                        <span class="qs-std-dash-countdown__live">
                                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                            <span class="qs-std-dash-countdown__prefix">{{ $row['countdown_prefix'] }}</span>
                                            <span class="qs-std-dash-countdown__time">—</span>
                                        </span>
                                        <span class="qs-std-dash-countdown__expired qs-std-dash-countdown__expired--{{ $wlExpiredState }}" aria-hidden="true">
                                            @if ($wlExpiredState === 'closed')
                                                <i class="fa-solid fa-lock"></i>
                                            @elseif ($wlExpiredState === 'overdue')
                                                <i class="fa-solid fa-clock-rotate-left"></i>
                                            @else
                                                <i class="fa-solid fa-circle-play"></i>
                                            @endif
                                            <span>{{ $wlExpiredCta }}</span>
                                        </span>
                                    </span>
                                @elseif ($secondaryInfo !== null && $secondaryInfo !== '')
                                    <span class="qs-wl-pill qs-wl-pill--time">
                                        <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                        <span>{{ $secondaryInfo }}</span>
                                    </span>
                                @endif
                            </div>

                            @if ($statusLabel !== '' || $scoreLine !== '')
                                <p class="qs-wl-item__status">
                                    @if ($statusLabel !== '')
                                        <span class="qs-wl-item__status-label">{{ $statusLabel }}</span>
                                    @endif
                                    @if ($scoreLine !== '')
                                        <span class="qs-wl-item__status-score">{{ $scoreLine }}</span>
                                    @endif
                                </p>
                            @endif

                            @if ($submissionFormat !== '')
                                <p class="qs-wl-item__format">{{ $submissionFormat }}</p>
                            @endif

                            @if (! empty($row['action_href']))
                                <a href="{{ $row['action_href'] }}" class="qs-wl-action qs-wl-action--primary">
                                    <span>{{ $row['action_label'] ?? __('Open') }}</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="qs-wl-empty">
                    <span class="qs-wl-empty__icon" aria-hidden="true"><i class="fa-solid fa-mug-saucer"></i></span>
                    <p class="qs-wl-empty__title">{{ __('You are all caught up') }}</p>
                    <p class="qs-wl-empty__sub">
                        @if ($submittedHistoryCount > 0)
                            {{ __('No open assessments right now. Past submissions and scores live on the Results page.') }}
                        @else
                            {{ __('Nothing open or due right now. New assessments and assignments will show up here when your class publishes them.') }}
                        @endif
                    </p>
                    @if ($submittedHistoryCount > 0)
                        <a href="{{ route('student.results.index') }}" class="qs-wl-panel__cta qs-wl-panel__cta--solo">
                            <i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i>
                            <span>{{ __('View results') }}</span>
                        </a>
                    @else
                        <a href="{{ route('student.assignments.index') }}" class="qs-wl-panel__cta qs-wl-panel__cta--solo">
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            <span>{{ __('Browse assignments') }}</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
