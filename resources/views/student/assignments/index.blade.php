<x-layouts.student>
    <x-slot name="title">{{ __('Assignments') }}</x-slot>
    <x-slot name="subtitle">{{ __('Coursework for your class — due dates and submissions.') }}</x-slot>

    @php
        $tz = config('app.timezone');
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
        $activeIsAssignment = $activeSession?->exam?->isAssignment() ?? false;

        $bucketMeta = [
            'in_progress' => ['key' => 'continue', 'status' => $examSessionPaused ? __('PAUSED') : __('IN PROGRESS'), 'icon' => $examSessionPaused ? 'fa-circle-pause' : 'fa-circle-play'],
            'open' => ['key' => 'assignments_due', 'status' => __('READY'), 'icon' => 'fa-file-pen'],
            'upcoming' => ['key' => 'upcoming', 'status' => __('SOON'), 'icon' => 'fa-calendar'],
            'submitted' => ['key' => 'submitted_work', 'status' => __('SUBMITTED'), 'icon' => 'fa-check-double'],
            'missed' => ['key' => 'closed_missed', 'status' => __('MISSED'), 'icon' => 'fa-circle-xmark'],
        ];

        $cards = [];
        foreach ($assignments as $row) {
            $course = $row['course'];
            $courseLine = trim(($course->code ?? '').' · '.($course->title ?? ''), ' ·');

            foreach (['in_progress', 'open', 'upcoming', 'submitted', 'missed'] as $bucket) {
                $items = $row[$bucket] ?? [];
                $items = is_object($items) && method_exists($items, 'all') ? $items->all() : (array) $items;
                if ($items === []) {
                    continue;
                }
                foreach ($items as $item) {
                    $exam = $bucket === 'submitted' ? ($item['exam'] ?? null) : $item;
                    if (! $exam) {
                        continue;
                    }
                    $dueCountdown = $bucket === 'open'
                        ? \App\Support\AssignmentDueCountdown::resolve($exam)
                        : null;

                    $statusLabel = null;
                    $actionHref = null;
                    $actionLabel = null;
                    $secondaryInfo = null;

                    if ($bucket === 'in_progress') {
                        if ($activeSession) {
                            $actionHref = route('student.exam.take', $activeSession);
                            $actionLabel = $examSessionPaused ? __('Resume') : __('Continue');
                        }
                        if ($exam->due_at) {
                            $secondaryInfo = __('Due :date', ['date' => $exam->due_at->timezone($tz)->format('M j · H:i')]);
                        }
                    } elseif ($bucket === 'open') {
                        $actionHref = route('student.exam.prepare', $exam);
                        $actionLabel = __('Open assignment');
                        if (! $dueCountdown && $exam->due_at) {
                            $secondaryInfo = __('Due :date', ['date' => $exam->due_at->timezone($tz)->format('M j · H:i')]);
                        }
                    } elseif ($bucket === 'upcoming') {
                        $secondaryInfo = $exam->start_time
                            ? __('Opens :date', ['date' => $exam->start_time->timezone($tz)->format('M j · H:i')])
                            : ($exam->due_at
                                ? __('Due :date', ['date' => $exam->due_at->timezone($tz)->format('M j · H:i')])
                                : __('Not open yet'));
                    } elseif ($bucket === 'submitted') {
                        $session = $item['session'] ?? null;
                        $result = $item['result'] ?? null;
                        $rStatus = (string) ($result?->status ?? 'pending_manual');
                        $statusLabel = match ($rStatus) {
                            'held' => __('Held for review'),
                            'pending_manual' => __('Awaiting grading'),
                            'graded' => $exam->assignmentGradesVisibleToStudents() ? __('Graded') : __('Awaiting release'),
                            'published' => __('Released'),
                            default => __('Submitted'),
                        };
                        if ($session) {
                            $actionHref = route('student.results.show', $session);
                            $actionLabel = __('View');
                        }
                    } else { // missed
                        if ($exam->due_at) {
                            $secondaryInfo = __('Due was :date', ['date' => $exam->due_at->timezone($tz)->format('M j · H:i')]);
                        }
                    }

                    $cards[] = [
                        'meta' => $bucketMeta[$bucket],
                        'title' => $exam->title,
                        'course_line' => $courseLine,
                        'countdown_ends_at' => $dueCountdown['ends_at'] ?? null,
                        'countdown_prefix' => $dueCountdown['prefix'] ?? null,
                        'secondary_info' => $secondaryInfo,
                        'status_label' => $statusLabel,
                        'action_href' => $actionHref,
                        'action_label' => $actionLabel,
                    ];
                }
            }
        }
    @endphp

    <div class="space-y-5 pb-6">
        @if ($activeSession !== null && $activeSession->exam && ! $activeIsAssignment)
            {{-- Only show the banner for non-assignment sessions (exams/quizzes), since assignment
                 sessions appear as in-progress cards in the grid below. --}}
            <section class="qs-active-banner {{ $examSessionPaused ? 'qs-active-banner--paused' : 'qs-active-banner--live' }}" aria-live="polite">
                <span class="qs-active-banner__icon" aria-hidden="true">
                    <i class="fa-solid {{ $examSessionPaused ? 'fa-circle-pause' : 'fa-circle-play' }}"></i>
                </span>
                <div class="qs-active-banner__body">
                    <p class="qs-active-banner__eyebrow">
                        {{ $examSessionPaused ? __('Exam paused') : __('Exam in progress') }}
                    </p>
                    <p class="qs-active-banner__title">{{ $activeSession->exam->title }}</p>
                </div>
                <a href="{{ route('student.exam.take', $activeSession) }}" class="qs-active-banner__cta">
                    {{ $examSessionPaused ? __('Resume') : __('Continue') }}
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </section>
        @endif

        @if ($user->class_id !== null && $assignments->isNotEmpty())
            <section class="qs-stat-grid grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4" aria-label="{{ __('Assignments overview') }}">
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Courses'),
                    'value' => number_format($summaryCourses),
                    'icon' => 'fa-folder-open',
                    'tone' => 'notices',
                    'minimal' => true,
                ])
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Open'),
                    'value' => number_format($summaryOpen + ($summaryInProgress ?? 0)),
                    'icon' => 'fa-file-pen',
                    'tone' => 'assignments',
                    'minimal' => true,
                ])
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Scheduled'),
                    'value' => number_format($summaryUpcoming),
                    'icon' => 'fa-calendar',
                    'tone' => 'assessments',
                    'minimal' => true,
                ])
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Submitted'),
                    'value' => number_format($summarySubmitted),
                    'icon' => 'fa-check-double',
                    'tone' => 'results',
                    'minimal' => true,
                ])
            </section>
        @endif

        @if ($user->class_id === null)
            <div class="qs-notif-empty">
                <span class="qs-notif-empty__icon" aria-hidden="true">
                    <i class="fa-solid fa-user-graduate"></i>
                </span>
                <p class="qs-notif-empty__title">{{ __('No class group yet') }}</p>
                <p class="qs-notif-empty__text">{{ __('student_ui.class_group_not_assigned') }}</p>
            </div>
        @elseif ($cards === [])
            <div class="qs-notif-empty">
                <span class="qs-notif-empty__icon" aria-hidden="true">
                    <i class="fa-solid fa-folder-open"></i>
                </span>
                <p class="qs-notif-empty__title">
                    {{ ($hasLinkedCourses ?? false) ? __('No assignments yet') : __('No courses linked yet') }}
                </p>
                <p class="qs-notif-empty__text">
                    {{ ($hasLinkedCourses ?? false)
                        ? __('Published coursework for your class will appear here.')
                        : __('Your coordinator has not linked modules to your class yet.') }}
                </p>
            </div>
        @else
            <div class="qs-page-section-head">
                <h2 class="qs-page-section-head__title">{{ __('Your assignments') }}</h2>
                <p class="qs-page-section-head__sub">{{ __('Showing work for :class.', ['class' => $user->classroom?->name ?? __('your class')]) }}</p>
            </div>

            <ul class="qs-wl-list qs-wl-list--shimmer">
                @foreach ($cards as $card)
                    @php $meta = $card['meta']; @endphp
                    <li class="qs-wl-item qs-wl-item--{{ $meta['key'] }}" style="--card-i: {{ $loop->index }}">
                        <div class="qs-wl-item__head">
                            <h3 class="qs-wl-item__title">{{ $card['title'] }}</h3>
                            <span class="qs-wl-item__icon" aria-hidden="true">
                                <i class="fa-solid {{ $meta['icon'] }}"></i>
                            </span>
                        </div>

                        @if ($card['course_line'])
                            <p class="qs-wl-item__sub">{{ $card['course_line'] }}</p>
                        @endif

                        <div class="qs-wl-item__pills">
                            <span class="qs-wl-pill">
                                <span class="qs-wl-pill__dot" aria-hidden="true"></span>
                                {{ $meta['status'] }}
                            </span>

                            @if ($card['countdown_ends_at'])
                                <span
                                    class="qs-wl-pill qs-wl-pill--time qs-std-dash-countdown"
                                    data-qs-countdown
                                    data-qs-countdown-ends="{{ $card['countdown_ends_at'] }}"
                                    role="timer"
                                    aria-label="{{ $card['countdown_prefix'] }}"
                                >
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <span class="qs-std-dash-countdown__prefix">{{ $card['countdown_prefix'] }}</span>
                                    <span class="qs-std-dash-countdown__time">—</span>
                                </span>
                            @elseif ($card['secondary_info'])
                                <span class="qs-wl-pill qs-wl-pill--time">
                                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                    <span>{{ $card['secondary_info'] }}</span>
                                </span>
                            @endif
                        </div>

                        @if ($card['status_label'])
                            <p class="qs-wl-item__status">
                                <span class="qs-wl-item__status-label">{{ $card['status_label'] }}</span>
                            </p>
                        @endif

                        @if ($card['action_href'])
                            <a href="{{ $card['action_href'] }}" class="qs-wl-action qs-wl-action--primary">
                                <span>{{ $card['action_label'] ?? __('Open') }}</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>

            <p class="qs-page-footnote">
                {{ __('Assignments are managed under Course assignment in your school setup.') }}
            </p>
        @endif
    </div>
</x-layouts.student>
