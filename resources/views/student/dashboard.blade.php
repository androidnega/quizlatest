<x-layouts.student>
    <x-slot name="title">{{ __('Home') }}</x-slot>
    <x-slot name="subtitle">{{ __('Your quiz history and quick actions.') }}</x-slot>

    @php
        $parts = \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->explode(' ')->filter();
        $firstName = $parts->first() ?: $user->name;
        $sessionExam = $activeSession?->exam;
        $highlightExam = $availableExams->first();
        $nextUpcomingExam = $upcomingExams->first();
        $examInProgressHere = $activeSession !== null && $sessionExam !== null;
        $tz = config('app.timezone');
        $prepareExam = $sessionExam ?? $highlightExam ?? $nextUpcomingExam;
        $heroIdleNoClassWork = ! $examInProgressHere
            && $highlightExam === null
            && $nextUpcomingExam === null
            && $heroAwaitingResult === null;
        $bestPracticeScore = $practiceEnabled && $recentPracticeScores->isNotEmpty()
            ? $recentPracticeScores->pluck('percentage')->filter(fn ($value) => $value !== null)->max()
            : null;
        $upcomingCount = $upcomingExams->count();
        $submitted = max(0, (int) $submittedExamsCount);
        $graded = max(0, (int) $gradedResultsCount);
        $progressPct = $submitted > 0
            ? (int) round(min(100, ($graded / $submitted) * 100))
            : ($graded > 0 ? 100 : 0);
        $heroBadge = match (true) {
            $examInProgressHere => __('In progress'),
            $highlightExam !== null => __('Open now'),
            $nextUpcomingExam !== null => __('Up next'),
            $heroAwaitingResult !== null => $heroAwaitingResult->status === 'held'
                ? __('Under review')
                : __('Awaiting grading'),
            default => null,
        };
        $activityTotal = max(1, $submitted + $graded + ($practiceEnabled ? $practiceQuizCount : 0));
        $activityPct = (int) round(min(100, (($submitted + $graded) / $activityTotal) * 100));
        $dashboardAssignments = $availableExams->concat($upcomingExams)->unique('id')->values()->take(3);
        $dashboardCourseNewMaterials = $dashboard_course_new_materials ?? [];
        $dashboardPracticeStreakDays = (int) ($dashboard_practice_streak_days ?? 0);
        $dashboardPracticeWeekNudge = (bool) ($dashboard_practice_week_nudge ?? false);
        $dashboardTip = (string) ($dashboard_tip ?? '');
        $dashboardPolicyNotice = $dashboard_policy_notice ?? null;
    @endphp

    <div class="w-full min-w-0 space-y-5 pb-2 text-slate-950">
        @if ($errors->has('exam'))
            <div class="flex items-start gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
                <span>{{ $errors->first('exam') }}</span>
            </div>
        @endif

        @if (! $classYearOk)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ __('Class year notice') }}</p>
                <p class="mt-1 text-xs leading-relaxed text-amber-900/90">{{ __('Your class may not match the active academic year. Some exams or results filters could look empty until your coordinator updates your enrollment.') }}</p>
            </div>
        @endif

        @if ($user->class_id === null)
            <div class="rounded-2xl border border-qs-soft bg-qs-surface px-4 py-3 text-sm text-qs-text">
                <p class="leading-relaxed text-qs-muted">
                    <i class="fa-solid fa-circle-info me-1 text-[var(--qs-primary)]" aria-hidden="true"></i>
                    {{ __('student_ui.class_group_not_assigned') }}
                </p>
            </div>
        @endif

        @if (! empty($dashboardCourseNewMaterials))
            <div class="rounded-2xl border border-sky-200 bg-sky-50/90 px-4 py-3 text-sm text-sky-950 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-sky-900/80">{{ __('Since your last visit') }}</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($dashboardCourseNewMaterials as $row)
                        @php
                            $n = (int) $row['count'];
                        @endphp
                        <li class="leading-snug">
                            @if ($n === 1)
                                {{ __('1 new file in :course', ['course' => $row['name']]) }}
                            @else
                                {{ __(':count new files in :course', ['count' => number_format($n), 'course' => $row['name']]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.materials.index') }}" class="mt-2 inline-flex text-xs font-semibold text-sky-800 underline-offset-2 hover:underline">
                        {{ __('Open course materials') }}
                    </a>
                @endif
            </div>
        @endif

        @if ($practiceEnabled && ($dashboardPracticeStreakDays >= 2 || $dashboardPracticeWeekNudge))
            <div class="rounded-2xl border border-teal-200 bg-teal-50/90 px-4 py-3 text-sm text-teal-950 shadow-sm">
                @if ($dashboardPracticeStreakDays >= 2)
                    <p>
                        {{ __('You have practiced :days days in a row.', ['days' => number_format($dashboardPracticeStreakDays)]) }}
                    </p>
                @elseif ($dashboardPracticeWeekNudge)
                    <p>{{ __('No practice this week yet — want a quick refresher?') }}</p>
                    <a href="{{ route('student.practice.revision') }}" class="mt-2 inline-flex text-xs font-semibold text-teal-900 underline-offset-2 hover:underline">
                        {{ __('Open practice hub') }}
                    </a>
                @endif
            </div>
        @endif

        @if ($dashboardTip !== '')
            <div class="flex gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700" aria-hidden="true">
                    <i class="fa-solid fa-lightbulb text-sm"></i>
                </span>
                <p class="min-w-0 leading-relaxed">{{ $dashboardTip }}</p>
            </div>
        @endif

        @if ($heldResults->isNotEmpty() || $pendingManualResults->isNotEmpty())
            <div class="rounded-2xl border border-orange-200 bg-orange-50/95 px-4 py-3 text-sm text-orange-950">
                <p class="text-xs font-bold uppercase tracking-wider text-orange-900/80">{{ __('Feedback & status') }}</p>
                @if ($heldResults->isNotEmpty())
                    <p class="mt-2 text-sm font-semibold text-orange-900">{{ __('Results under review') }}: {{ $heldResults->count() }}</p>
                @endif
                @if ($pendingManualResults->isNotEmpty())
                    <p class="mt-1 text-sm font-semibold text-orange-900">{{ __('Awaiting grading') }}: {{ $pendingManualResults->count() }}</p>
                @endif
                <a href="{{ route('student.results.index') }}" class="mt-3 inline-flex items-center rounded-xl bg-orange-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-orange-700">
                    {{ __('View results') }}
                </a>
            </div>
        @endif

        {{-- Page header (greeting); shell already has global profile menu --}}
        <header class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">{{ __('Welcome back') }}</p>
                <h1 class="mt-0.5 text-2xl font-semibold tracking-tight text-slate-900">{{ __('Hi, :name', ['name' => $firstName]) }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ __('Track quizzes, results, and practice in one place.') }}</p>
            </div>
            <a
                href="{{ route('profile.edit') }}"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-[var(--qs-primary)]/35 hover:bg-qs-soft hover:text-[var(--qs-primary)] md:hidden"
                aria-label="{{ __('Profile') }}"
            >
                <i class="fa-solid fa-user text-lg" aria-hidden="true"></i>
            </a>
        </header>

        {{-- Hero --}}
        <section class="overflow-hidden rounded-[2rem] bg-slate-950 text-white shadow-lg shadow-slate-900/10 ring-1 ring-slate-900/20">
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-teal-300/95">{{ __('Current focus') }}</p>

                        @if ($examInProgressHere && $sessionExam)
                            <h2 class="mt-3 max-w-xl text-2xl font-semibold tracking-tight sm:text-3xl md:text-4xl">
                                {{ $sessionExam->title }}
                            </h2>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-300">
                                {{ __('You have an exam in progress. Continue when you are ready.') }}
                            </p>
                        @elseif ($highlightExam)
                            <h2 class="mt-3 max-w-xl text-2xl font-semibold tracking-tight sm:text-3xl md:text-4xl">
                                {{ $highlightExam->title }}
                            </h2>
                            <p class="mt-3 flex flex-wrap gap-x-2 gap-y-1 text-sm text-slate-300">
                                @if ($highlightExam->course?->code)
                                    <span class="font-semibold text-white">{{ $highlightExam->course->code }}</span>
                                @endif
                                @if ($highlightExam->duration_minutes)
                                    <span>{{ $highlightExam->duration_minutes }} {{ __('min') }}</span>
                                @endif
                                @if ($highlightExam->end_time)
                                    <span>{{ __('Until') }} {{ $highlightExam->end_time->timezone($tz)->format('M j, H:i') }}</span>
                                @endif
                            </p>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-400">
                                {{ __('This is the next quiz you can start from your class schedule.') }}
                            </p>
                        @elseif ($nextUpcomingExam)
                            <h2 class="mt-3 max-w-xl text-2xl font-semibold tracking-tight sm:text-3xl md:text-4xl">
                                {{ $nextUpcomingExam->title }}
                            </h2>
                            @if ($nextUpcomingExam->start_time)
                                <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-300">
                                    {{ __('Opens') }} {{ $nextUpcomingExam->start_time->timezone($tz)->format('l, M j · H:i') }}
                                </p>
                            @endif
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-400">
                                {{ __('No quiz is open yet. A start button will appear when the window opens.') }}
                            </p>
                        @elseif ($heroAwaitingResult)
                            <h2 class="mt-3 max-w-xl text-2xl font-semibold tracking-tight sm:text-3xl md:text-4xl">
                                {{ $heroAwaitingResult->quiz?->title ?? __('Your last quiz') }}
                            </h2>
                            <p class="mt-3 flex flex-wrap gap-x-2 gap-y-1 text-sm text-slate-300">
                                @if ($heroAwaitingResult->quiz?->course?->code)
                                    <span class="font-semibold text-white">{{ $heroAwaitingResult->quiz->course->code }}</span>
                                @endif
                            </p>
                            @if ($heroAwaitingResult->status === 'held')
                                <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-300">
                                    {{ __('Your submission is under review. You will see a score when it is released.') }}
                                </p>
                            @else
                                <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-300">
                                    {{ __('This quiz is waiting to be graded. Check results for updates.') }}
                                </p>
                            @endif
                        @elseif ($practiceEnabled && $heroIdleNoClassWork)
                            <h2 class="mt-3 max-w-xl text-2xl font-semibold tracking-tight sm:text-3xl md:text-4xl">
                                {{ __('Ready to revise?') }}
                            </h2>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-300">
                                {{ $bestPracticeScore !== null ? __('Best practice score: :score', ['score' => number_format((float) $bestPracticeScore, 1).'%' ]) : __('Open your course outline, review summaries, or start a practice quiz.') }}
                            </p>
                        @else
                            <h2 class="mt-3 max-w-xl text-2xl font-semibold tracking-tight sm:text-3xl md:text-4xl">
                                {{ __('You are all caught up') }}
                            </h2>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-300">
                                {{ __('Review your results for feedback and past scores.') }}
                            </p>
                        @endif
                    </div>

                    @if ($heroBadge)
                        <div class="hidden shrink-0 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-slate-200 sm:block">
                            {{ $heroBadge }}
                        </div>
                    @endif
                </div>

                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:mt-6">
                    @if ($examInProgressHere && $activeSession)
                        <a
                            href="{{ route('student.exam.take', $activeSession) }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[var(--qs-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[var(--qs-primary)] focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('Continue exam') }}
                        </a>
                    @elseif ($highlightExam)
                        <a
                            href="{{ route('student.exam.prepare', $highlightExam) }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[var(--qs-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[var(--qs-primary)] focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('Start quiz') }}
                        </a>
                    @elseif ($nextUpcomingExam)
                        <a
                            href="{{ route('student.results.index') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[var(--qs-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[var(--qs-primary)] focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('View results') }}
                        </a>
                        @if ($practiceEnabled)
                            <a
                                href="{{ route('student.practice.revision') }}"
                                class="inline-flex items-center justify-center rounded-2xl bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/25 focus:ring-offset-2 focus:ring-offset-slate-950"
                            >
                                {{ __('Open practice') }}
                            </a>
                        @endif
                    @elseif ($heroAwaitingResult)
                        <a
                            href="{{ route('student.results.index') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[var(--qs-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[var(--qs-primary)] focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('View results') }}
                        </a>
                        @if ($practiceEnabled)
                            <a
                                href="{{ route('student.practice.revision') }}"
                                class="inline-flex items-center justify-center rounded-2xl bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/25 focus:ring-offset-2 focus:ring-offset-slate-950"
                            >
                                {{ __('Open practice') }}
                            </a>
                        @endif
                    @elseif ($practiceEnabled && $heroIdleNoClassWork)
                        <a
                            href="{{ route('student.practice.revision') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[var(--qs-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[var(--qs-primary)] focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('Open practice') }}
                        </a>
                        <a
                            href="{{ route('student.results.index') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/25 focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('View results') }}
                        </a>
                    @else
                        <a
                            href="{{ route('student.results.index') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[var(--qs-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-[var(--qs-primary)] focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('View results') }}
                        </a>
                    @endif

                    @if ($prepareExam && ($examInProgressHere || $highlightExam))
                        <a
                            href="{{ route('student.exam.prepare', $prepareExam) }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-white/10 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/25 focus:ring-offset-2 focus:ring-offset-slate-950"
                        >
                            {{ __('View instructions') }}
                        </a>
                    @endif
                </div>
            </div>
        </section>

        {{-- Summary stats: always 3 columns; compact stack on small screens --}}
        <div class="grid grid-cols-3 gap-2 sm:gap-4">
            <article class="flex min-w-0 flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2 py-3 text-center shadow-sm sm:flex-row sm:gap-4 sm:rounded-[1.75rem] sm:p-5 sm:text-left">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600 sm:h-12 sm:w-12 sm:rounded-2xl" aria-hidden="true">
                    <i class="fa-solid fa-clipboard-check text-sm sm:text-lg"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold tabular-nums tracking-tight text-slate-900 sm:text-2xl">{{ number_format($submittedExamsCount) }}</p>
                    <p class="text-[10px] font-medium leading-tight text-slate-500 sm:mt-0.5 sm:text-xs">{{ __('Taken') }}</p>
                </div>
            </article>
            <article class="flex min-w-0 flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2 py-3 text-center shadow-sm sm:flex-row sm:gap-4 sm:rounded-[1.75rem] sm:p-5 sm:text-left">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-600 sm:h-12 sm:w-12 sm:rounded-2xl" aria-hidden="true">
                    <i class="fa-solid fa-square-poll-vertical text-sm sm:text-lg"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold tabular-nums tracking-tight text-slate-900 sm:text-2xl">{{ number_format($gradedResultsCount) }}</p>
                    <p class="text-[10px] font-medium leading-tight text-slate-500 sm:mt-0.5 sm:text-xs">{{ __('Results') }}</p>
                </div>
            </article>
            <article class="flex min-w-0 flex-col items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2 py-3 text-center shadow-sm sm:flex-row sm:gap-4 sm:rounded-[1.75rem] sm:p-5 sm:text-left">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-600 sm:h-12 sm:w-12 sm:rounded-2xl" aria-hidden="true">
                    <i class="fa-solid fa-layer-group text-sm sm:text-lg"></i>
                </div>
                <div class="min-w-0">
                    @if ($practiceEnabled)
                        <p class="text-lg font-semibold tabular-nums tracking-tight text-slate-900 sm:text-2xl">{{ number_format($practiceQuizCount) }}</p>
                    @else
                        <p class="text-lg font-semibold tabular-nums tracking-tight text-slate-400 sm:text-2xl">—</p>
                    @endif
                    <p class="text-[10px] font-medium leading-tight text-slate-500 sm:mt-0.5 sm:text-xs">{{ __('Practice') }}</p>
                </div>
            </article>
        </div>

        {{-- Progress + quick actions --}}
        <section class="grid gap-4 lg:grid-cols-[1fr_0.85fr]">
            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ __('Your progress') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            @if ($submitted > 0)
                                {{ __(':graded of :total submitted exams are graded.', ['graded' => number_format($graded), 'total' => number_format($submitted)]) }}
                            @else
                                {{ __('Activity across submissions and graded results.') }}
                            @endif
                        </p>
                    </div>
                    <p class="text-2xl font-semibold tabular-nums text-slate-900">{{ $progressPct }}%</p>
                </div>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100" role="presentation">
                    <div class="h-full rounded-full bg-[var(--qs-primary)] transition-all" style="width: {{ $progressPct }}%"></div>
                </div>
                @if ($upcomingCount > 0 || $activityPct !== $progressPct)
                    <p class="mt-3 text-xs text-slate-400">
                        {{ __('Submissions') }}: {{ number_format($submitted) }}
                        · {{ __('Graded') }}: {{ number_format($graded) }}
                        @if ($upcomingCount > 0)
                            · {{ __('Upcoming') }}: {{ $upcomingCount }}
                        @endif
                    </p>
                @endif
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-white p-2 shadow-sm">
                <a href="{{ route('student.results.index') }}" class="flex items-center justify-between rounded-2xl px-3 py-3 transition hover:bg-slate-50">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ __('Results') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Check your scores') }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-slate-400" aria-hidden="true"><i class="fa-solid fa-chevron-right text-xs"></i></span>
                </a>
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.revision') }}" class="flex items-center justify-between rounded-2xl px-3 py-3 transition hover:bg-slate-50">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">{{ __('Practice') }}</p>
                                <p class="text-xs text-slate-500">{{ __('Try more quizzes') }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 text-slate-400" aria-hidden="true"><i class="fa-solid fa-chevron-right text-xs"></i></span>
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}" class="flex items-center justify-between rounded-2xl px-3 py-3 transition hover:bg-slate-50">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ __('Profile') }}</p>
                            <p class="text-xs text-slate-500">{{ __('Manage your details') }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-slate-400" aria-hidden="true"><i class="fa-solid fa-chevron-right text-xs"></i></span>
                </a>
            </div>
        </section>

        {{-- Activity row: class work, self-study, recent attempts --}}
        <section class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-stretch">
            {{-- Course assignments (scheduled / open class quizzes) --}}
            <article class="flex flex-col rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700" aria-hidden="true">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="font-semibold leading-tight text-slate-900">{{ __('Course assignments') }}</h2>
                            <p class="mt-1 text-xs leading-snug text-slate-500">{{ __('Open and upcoming quizzes from your classes.') }}</p>
                        </div>
                    </div>
                    <a href="{{ route('student.results.index') }}" class="shrink-0 text-sm font-medium text-[var(--qs-primary)] hover:underline">
                        {{ __('View all') }}
                    </a>
                </div>
                <div class="mt-4 flex min-h-[5.5rem] flex-1 flex-col">
                    @if ($user->class_id === null)
                        <p class="text-sm text-slate-500">{{ __('When you are assigned to a class, your scheduled quizzes will show here.') }}</p>
                    @elseif ($dashboardAssignments->isEmpty())
                        <p class="text-sm text-slate-500">{{ __('No open or scheduled class quizzes right now.') }}</p>
                    @else
                        <ul class="space-y-2">
                            @foreach ($dashboardAssignments as $exam)
                                @php
                                    $openNow = $availableExams->contains(fn ($e) => (int) $e->id === (int) $exam->id);
                                @endphp
                                <li class="min-w-0">
                                    @if ($openNow)
                                        <a href="{{ route('student.exam.prepare', $exam) }}" class="group block rounded-xl px-1 py-2 transition hover:bg-slate-50">
                                            <p class="truncate text-sm font-medium text-slate-900 group-hover:text-[var(--qs-primary)]">{{ $exam->title }}</p>
                                            <p class="mt-0.5 text-xs font-medium text-emerald-600">{{ __('Open now') }}</p>
                                        </a>
                                    @else
                                        <div class="rounded-xl px-1 py-2">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $exam->title }}</p>
                                            @if ($exam->start_time)
                                                <p class="mt-0.5 text-xs text-slate-500">{{ __('Starts') }} {{ $exam->start_time->timezone($tz)->format('M j, H:i') }}</p>
                                            @else
                                                <p class="mt-0.5 text-xs text-slate-500">{{ __('Scheduled') }}</p>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>

            {{-- Revision & self-check (materials → summaries → practice quizzes) --}}
            <article class="flex flex-col rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700" aria-hidden="true">
                            <i class="fa-solid fa-book-open-reader"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="font-semibold leading-tight text-slate-900">{{ __('Revision & self-check') }}</h2>
                            <p class="mt-1 text-xs leading-snug text-slate-500">{{ __('Practice quizzes and summaries from your course outline and materials.') }}</p>
                        </div>
                    </div>
                    <a href="{{ route('student.practice.revision') }}" class="shrink-0 text-sm font-medium text-[var(--qs-primary)] hover:underline">
                        {{ __('Open hub') }}
                    </a>
                </div>
                <div class="mt-4 flex min-h-[5.5rem] flex-1 flex-col">
                    @if ($practiceEnabled)
                        <ul class="space-y-1.5 text-sm">
                            <li>
                                <a href="{{ route('student.practice.materials.index') }}" class="flex items-center justify-between gap-2 rounded-xl px-2 py-2 text-slate-800 transition hover:bg-slate-50">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <i class="fa-solid fa-folder-open w-4 shrink-0 text-center text-[var(--qs-primary)]" aria-hidden="true"></i>
                                        <span class="truncate font-medium">{{ __('Course outline & files') }}</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('student.practice.summaries.index') }}" class="flex items-center justify-between gap-2 rounded-xl px-2 py-2 text-slate-800 transition hover:bg-slate-50">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <i class="fa-solid fa-file-lines w-4 shrink-0 text-center text-[var(--qs-primary)]" aria-hidden="true"></i>
                                        <span class="truncate font-medium">{{ __('Slide & topic summaries') }}</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400" aria-hidden="true"></i>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('student.practice.quizzes.index') }}" class="flex items-center justify-between gap-2 rounded-xl px-2 py-2 text-slate-800 transition hover:bg-slate-50">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <i class="fa-solid fa-clipboard-question w-4 shrink-0 text-center text-[var(--qs-primary)]" aria-hidden="true"></i>
                                        <span class="truncate font-medium">{{ __('My practice quizzes') }}</span>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400" aria-hidden="true"></i>
                                </a>
                            </li>
                        </ul>
                    @else
                        <p class="text-sm text-slate-500">{{ __('Open the revision hub to see how materials, summaries, and practice quizzes work when your school turns them on.') }}</p>
                        <a href="{{ route('student.practice.revision') }}#materials" class="mt-3 inline-flex text-sm font-semibold text-[var(--qs-primary)] hover:underline">{{ __('What is on this page?') }}</a>
                    @endif
                </div>
            </article>

            {{-- Recent activity (latest practice attempts or results hint) --}}
            <article class="flex flex-col rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-fuchsia-100 text-fuchsia-700" aria-hidden="true">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="font-semibold leading-tight text-slate-900">{{ __('Recent activity') }}</h2>
                            <p class="mt-1 text-xs leading-snug text-slate-500">{{ __('Latest practice quiz scores.') }}</p>
                        </div>
                    </div>
                    @if ($practiceEnabled)
                        <a href="{{ route('student.practice.quizzes.index') }}" class="shrink-0 text-sm font-medium text-[var(--qs-primary)] hover:underline">
                            {{ __('View all') }}
                        </a>
                    @else
                        <a href="{{ route('student.results.index') }}" class="shrink-0 text-sm font-medium text-[var(--qs-primary)] hover:underline">
                            {{ __('View all') }}
                        </a>
                    @endif
                </div>
                <div class="mt-4 flex min-h-[5.5rem] flex-1 flex-col">
                    @if ($practiceEnabled && $recentPracticeScores->isNotEmpty())
                        <ul class="space-y-1">
                            @foreach ($recentPracticeScores as $att)
                                <li class="flex items-center justify-between gap-3 rounded-xl px-1 py-2.5">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $att->practiceQuiz?->title }}</p>
                                        @if ($att->submitted_at)
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $att->submitted_at->timezone($tz)->format('M d, Y') }}</p>
                                        @endif
                                    </div>
                                    <p class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-800">
                                        {{ $att->percentage !== null ? number_format((float) $att->percentage, 0).'%' : '—' }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500">
                            {{ $practiceEnabled ? __('No practice attempts yet.') : __('Open results to see released scores and status.') }}
                        </p>
                    @endif
                </div>
            </article>
        </section>
    </div>

    @if (is_array($dashboardPolicyNotice) && ($dashboardPolicyNotice['message'] ?? '') !== '')
        <div
            class="pointer-events-none fixed bottom-4 left-0 right-0 z-50 flex justify-center px-4 sm:justify-end sm:px-6"
            role="status"
        >
            <div
                class="pointer-events-auto w-full max-w-md rounded-2xl border border-slate-800/15 bg-slate-900 px-4 py-3 text-sm text-white shadow-xl shadow-slate-900/25"
            >
                <p class="font-medium leading-snug">{{ $dashboardPolicyNotice['message'] }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    @if (($dashboardPolicyNotice['faq_url'] ?? '') !== '')
                        <a
                            href="{{ $dashboardPolicyNotice['faq_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs font-semibold text-teal-200 underline-offset-2 hover:underline"
                        >
                            {{ __('Read FAQ') }}
                        </a>
                    @endif
                    <form method="post" action="{{ route('student.dashboard.policy-notice.dismiss') }}" class="inline">
                        @csrf
                        <button type="submit" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/20">
                            {{ __('Dismiss') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</x-layouts.student>
