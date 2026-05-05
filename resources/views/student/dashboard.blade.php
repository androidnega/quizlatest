<x-layouts.student>
    <x-slot name="title">{{ __('Student dashboard') }}</x-slot>
    <x-slot name="subtitle">{{ __('Your exams, results, practice tools, and profile — organized like a course hub.') }}</x-slot>

        @php
        $parts = \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->explode(' ')->filter();
        $initials = $parts->take(2)->map(fn ($p) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $p, 0, 1)))->implode('');
        if ($initials === '') {
            $initials = '?';
        }
        $showPortraitAvatar = $user->role !== 'student' && filled($user->face_image_path ?? null);
        $faceImgSrc = $showPortraitAvatar && \Illuminate\Support\Facades\Route::has('profile.face-image') ? route('profile.face-image') : null;
        $firstCourseKey = $coursesWithExams->keys()->first();
        $openCourseId = $firstCourseKey !== null ? (string) $firstCourseKey : null;
        $firstName = $parts->first() ?: $user->name;
        $availableCount = $availableExams->count();
        $upcomingCount = $upcomingExams->count();
        $reviewCount = $heldResults->count();
        $awaitingCount = $pendingManualResults->count();
        $bestPracticeScore = $practiceEnabled && $recentPracticeScores->isNotEmpty()
            ? $recentPracticeScores->pluck('percentage')->filter(fn ($value) => $value !== null)->max()
            : null;
        $overviewLinks = [
            ['label' => __('Overview'), 'href' => '#student-overview', 'icon' => 'house'],
            ['label' => __('Exams'), 'href' => '#student-exams', 'icon' => 'file-lines'],
            ['label' => __('Calendar'), 'href' => '#student-agenda', 'icon' => 'calendar-days'],
            ['label' => __('Materials'), 'href' => $practiceEnabled ? route('student.practice.materials.index') : '#student-practice', 'icon' => 'folder-open'],
            ['label' => __('Profile'), 'href' => route('profile.edit'), 'icon' => 'user'],
            ['label' => __('Results'), 'href' => route('student.results.index'), 'icon' => 'square-poll-vertical'],
        ];
    @endphp

    <div class="qs-student-hub mx-auto max-w-7xl">
        @if ($errors->has('exam'))
            <div class="mb-4 flex items-start gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm">
                <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
                <span>{{ $errors->first('exam') }}</span>
            </div>
        @endif

        @if (! $classYearOk)
            <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 shadow-sm">
                <p class="font-semibold">{{ __('Class year notice') }}</p>
                <p class="mt-1 text-xs leading-relaxed text-amber-900/90">{{ __('Your class may not match the active academic year. Some exams or results filters could look empty until your coordinator updates your enrollment.') }}</p>
            </div>
        @endif

        <section id="student-overview" class="qs-student-shell mb-5 overflow-hidden">
            <div class="qs-student-hero">
                <div class="grid gap-6 p-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(18rem,0.9fr)] lg:p-7">
                    <div class="min-w-0">
                        <div class="mb-4 flex flex-wrap gap-2">
                            @foreach ($overviewLinks as $link)
                                <a href="{{ $link['href'] }}" class="qs-student-mini-tab">
                                    <i class="fa-solid fa-{{ $link['icon'] }} text-[11px]" aria-hidden="true"></i>
                                    <span>{{ $link['label'] }}</span>
                                </a>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 gap-4">
                                @if ($faceImgSrc)
                                    <span class="qs-student-hero__avatar !border-white/40 !bg-white !p-0">
                                        <img src="{{ $faceImgSrc }}" alt="" class="h-full w-full object-cover" width="64" height="64" loading="lazy" decoding="async" />
                                    </span>
                                @else
                                    <span class="qs-student-hero__avatar" aria-hidden="true">{{ $initials }}</span>
                                @endif
                                <div class="min-w-0">
                                    <p class="qs-student-hero__eyebrow">{{ __('Student workspace') }}</p>
                                    <h2 class="qs-student-hero__title">{{ __('Good afternoon, :name', ['name' => $firstName]) }}</h2>
                                    <p class="qs-student-hero__subtitle">{{ __('Track exams, results, practice activity, and your academic profile from one place.') }}</p>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if ($user->index_number)
                                            <span class="qs-student-badge">
                                                <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                                                {{ $user->index_number }}
                                            </span>
                                        @endif
                                        @if ($user->classroom)
                                            <span class="qs-student-badge">
                                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                                {{ $user->classroom->name }}@if ($user->classroom->section) · {{ $user->classroom->section }}@endif
                                            </span>
                                        @endif
                                        <span class="qs-student-badge">
                                            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>
                                            {{ $user->university?->name ?? __('University') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 sm:justify-end">
                                <a href="{{ route('profile.edit') }}" class="qs-student-cta qs-student-cta--soft">{{ __('Edit profile') }}</a>
                                @if ($practiceEnabled)
                                    <a href="{{ route('student.practice.index') }}" class="qs-student-cta">{{ __('Open practice') }}</a>
                                @elseif ($availableCount > 0)
                                    <a href="#student-exams" class="qs-student-cta">{{ __('Start an exam') }}</a>
                                @endif
                            </div>
                        </div>

                        @if ($activeSession)
                            <div class="qs-student-spotlight mt-5">
                                <div class="flex min-w-0 gap-3">
                                    <span class="qs-student-spotlight__icon">
                                        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="qs-student-spotlight__eyebrow">{{ __('Exam in progress') }}</p>
                                        <p class="qs-student-spotlight__title">{{ $activeSession->exam?->title }}</p>
                                        <p class="qs-student-spotlight__meta">{{ $activeSession->exam?->course?->code }} — {{ $activeSession->exam?->course?->title }}</p>
                                    </div>
                                </div>
                                <a href="{{ route('student.exam.take', $activeSession) }}" class="qs-student-cta shrink-0">
                                    <i class="fa-solid fa-play text-xs" aria-hidden="true"></i>
                                    {{ __('Continue') }}
                                </a>
                            </div>
                        @endif
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <article class="qs-student-highlight-card qs-student-highlight-card--mint">
                            <span class="qs-student-highlight-card__icon"><i class="fa-solid fa-file-signature" aria-hidden="true"></i></span>
                            <p class="qs-student-highlight-card__label">{{ __('Available now') }}</p>
                            <p class="qs-student-highlight-card__value">{{ number_format($availableCount) }}</p>
                            <p class="qs-student-highlight-card__meta">{{ $availableCount === 1 ? __('Exam ready to start') : __('Exams ready to start') }}</p>
                        </article>
                        <article class="qs-student-highlight-card qs-student-highlight-card--sky">
                            <span class="qs-student-highlight-card__icon"><i class="fa-solid fa-calendar-week" aria-hidden="true"></i></span>
                            <p class="qs-student-highlight-card__label">{{ __('Upcoming') }}</p>
                            <p class="qs-student-highlight-card__value">{{ number_format($upcomingCount) }}</p>
                            <p class="qs-student-highlight-card__meta">{{ __('Scheduled exam windows ahead') }}</p>
                        </article>
                        <article class="qs-student-highlight-card qs-student-highlight-card--amber">
                            <span class="qs-student-highlight-card__icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                            <p class="qs-student-highlight-card__label">{{ __('Profile status') }}</p>
                            <p class="qs-student-highlight-card__value text-xl sm:text-2xl">{{ $studentProfileReady ? __('Ready') : __('Pending') }}</p>
                            <p class="qs-student-highlight-card__meta">{{ $studentProfileReady ? __('Account setup complete') : __('Complete onboarding if prompted') }}</p>
                        </article>
                    </div>
                </div>

                <div class="qs-student-hero__stats">
                    <div class="qs-student-hero__stat">
                        <p class="qs-student-hero__stat-val">{{ number_format($submittedExamsCount) }}</p>
                        <p class="qs-student-hero__stat-lab">{{ __('Exams done') }}</p>
                    </div>
                    <div class="qs-student-hero__stat">
                        <p class="qs-student-hero__stat-val">{{ number_format($gradedResultsCount) }}</p>
                        <p class="qs-student-hero__stat-lab">{{ __('Results graded') }}</p>
                    </div>
                    <div class="qs-student-hero__stat">
                        <p class="qs-student-hero__stat-val">{{ $practiceEnabled ? number_format($practiceQuizCount) : number_format($availableCount) }}</p>
                        <p class="qs-student-hero__stat-lab">{{ $practiceEnabled ? __('Practice quizzes') : __('Open exams') }}</p>
                    </div>
                    <div class="qs-student-hero__stat">
                        <p class="qs-student-hero__stat-val">{{ number_format($reviewCount + $awaitingCount) }}</p>
                        <p class="qs-student-hero__stat-lab">{{ __('Pending updates') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('student.results.index') }}" class="qs-student-feature-card">
                <span class="qs-student-feature-card__icon bg-emerald-100 text-emerald-800"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i></span>
                <div class="min-w-0">
                    <p class="qs-student-feature-card__title">{{ __('Results') }}</p>
                    <p class="qs-student-feature-card__meta">{{ __('Check official scores and released submissions.') }}</p>
                </div>
                <i class="fa-solid fa-chevron-right qs-student-feature-card__chev" aria-hidden="true"></i>
            </a>

            <a href="{{ route('profile.edit') }}" class="qs-student-feature-card">
                <span class="qs-student-feature-card__icon bg-amber-100 text-amber-800"><i class="fa-solid fa-user-gear" aria-hidden="true"></i></span>
                <div class="min-w-0">
                    <p class="qs-student-feature-card__title">{{ __('Profile') }}</p>
                    <p class="qs-student-feature-card__meta">{{ __('Update your account, face setup, and identity details.') }}</p>
                </div>
                <i class="fa-solid fa-chevron-right qs-student-feature-card__chev" aria-hidden="true"></i>
            </a>

            @if ($practiceEnabled)
                <a href="{{ route('student.practice.materials.index') }}" class="qs-student-feature-card">
                    <span class="qs-student-feature-card__icon bg-teal-100 text-teal-800"><i class="fa-solid fa-folder-open" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <p class="qs-student-feature-card__title">{{ __('Materials') }}</p>
                        <p class="qs-student-feature-card__meta">{{ __('Open study files and course resources quickly.') }}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right qs-student-feature-card__chev" aria-hidden="true"></i>
                </a>

                <a href="{{ route('student.practice.index') }}" class="qs-student-feature-card">
                    <span class="qs-student-feature-card__icon bg-violet-100 text-violet-800"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <p class="qs-student-feature-card__title">{{ __('Practice') }}</p>
                        <p class="qs-student-feature-card__meta">{{ __('Generate quizzes, summaries, and self-study sessions.') }}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right qs-student-feature-card__chev" aria-hidden="true"></i>
                </a>
            @else
                <div class="qs-student-feature-card md:col-span-2 xl:col-span-2">
                    <span class="qs-student-feature-card__icon bg-slate-100 text-slate-700"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <p class="qs-student-feature-card__title">{{ __('Practice module') }}</p>
                        <p class="qs-student-feature-card__meta">{{ __('When enabled by your institution, study materials, AI summaries, and practice quizzes will appear here.') }}</p>
                    </div>
                </div>
            @endif
        </section>

        <section class="mb-5 grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.9fr)]">
            <div class="qs-student-shell p-4 sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="qs-student-section-eyebrow">{{ __('At a glance') }}</p>
                        <h3 class="text-base font-bold text-slate-950 sm:text-lg">{{ __('Your academic summary') }}</h3>
                    </div>
                    <a href="{{ route('student.results.index') }}" class="qs-student-pill">{{ __('View results') }}</a>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article class="qs-student-glance-card">
                        <span class="qs-student-glance-card__icon bg-sky-100 text-sky-700"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span>
                        <p class="qs-student-glance-card__value">{{ number_format($submittedExamsCount) }}</p>
                        <p class="qs-student-glance-card__label">{{ __('Quizzes taken') }}</p>
                    </article>

                    <article class="qs-student-glance-card">
                        <span class="qs-student-glance-card__icon bg-emerald-100 text-emerald-700"><i class="fa-solid fa-award" aria-hidden="true"></i></span>
                        <p class="qs-student-glance-card__value">
                            {{ $bestPracticeScore !== null ? number_format((float) $bestPracticeScore, 1).'%' : number_format($gradedResultsCount) }}
                        </p>
                        <p class="qs-student-glance-card__label">
                            {{ $bestPracticeScore !== null ? __('Best practice score') : __('Results graded') }}
                        </p>
                    </article>

                    <article class="qs-student-glance-card">
                        <span class="qs-student-glance-card__icon bg-amber-100 text-amber-700"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span>
                        <p class="qs-student-glance-card__value">{{ $studentProfileReady ? __('Ready') : __('Setup') }}</p>
                        <p class="qs-student-glance-card__label">{{ __('Profile verification') }}</p>
                    </article>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <a href="#student-agenda" class="qs-student-quick-link">
                        <span class="qs-student-quick-link__icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900">{{ __('Calendar') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('See upcoming exam windows and due times.') }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-slate-400" aria-hidden="true"></i>
                    </a>

                    <a href="{{ route('student.results.index') }}" class="qs-student-quick-link">
                        <span class="qs-student-quick-link__icon"><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900">{{ __('Class results') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('Review published scores and submission outcomes.') }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-right text-slate-400" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <div class="grid gap-4">
                @if ($practiceEnabled ?? false)
                    <section id="student-practice" class="qs-student-shell p-4 sm:p-5">
                        <div class="mb-3 flex items-start gap-3">
                            <span class="qs-student-side-icon bg-teal-100 text-teal-800"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                            <div>
                                <p class="qs-student-section-eyebrow">{{ __('Practice') }}</p>
                                <h3 class="text-sm font-bold text-slate-900">{{ __('Study shortcuts') }}</h3>
                            </div>
                        </div>
                        <p class="text-xs leading-relaxed text-slate-600">{{ __('Summaries, generated quizzes, and attempts live here and do not affect your official grade.') }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('student.practice.summaries.index') }}" class="qs-student-pill">{{ __('Summaries') }}</a>
                            <a href="{{ route('student.practice.quizzes.index') }}" class="qs-student-pill">{{ __('My quizzes') }}</a>
                            <a href="{{ route('student.practice.quizzes.create') }}" class="qs-student-cta">{{ __('New quiz') }}</a>
                        </div>
                    </section>
                @endif

                @if ($heldResults->isNotEmpty() || $pendingManualResults->isNotEmpty())
                    <section class="qs-student-shell p-4 sm:p-5">
                        <div class="mb-3 flex items-start gap-3">
                            <span class="qs-student-side-icon bg-amber-100 text-amber-800"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
                            <div>
                                <p class="qs-student-section-eyebrow">{{ __('Status') }}</p>
                                <h3 class="text-sm font-bold text-slate-900">{{ __('Result updates') }}</h3>
                            </div>
                        </div>

                        @if ($heldResults->isNotEmpty())
                            <div class="qs-student-status-block qs-student-status-block--warn">
                                <p class="text-sm font-semibold text-amber-950">{{ __('Results under review') }}</p>
                                <ul class="mt-2 space-y-1.5 text-xs text-amber-950/90">
                                    @foreach ($heldResults as $row)
                                        <li class="flex items-center gap-2">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-600/80" aria-hidden="true"></span>
                                            {{ $row->quiz?->title ?? __('Exam') }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($pendingManualResults->isNotEmpty())
                            <div class="qs-student-status-block qs-student-status-block--muted {{ $heldResults->isNotEmpty() ? 'mt-3' : '' }}">
                                <p class="text-sm font-semibold text-slate-900">{{ __('Awaiting grading') }}</p>
                                <ul class="mt-2 space-y-1.5 text-xs text-slate-600">
                                    @foreach ($pendingManualResults as $row)
                                        <li class="flex items-center gap-2">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400" aria-hidden="true"></span>
                                            {{ $row->quiz?->title ?? __('Exam') }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($practiceEnabled && $recentPracticeScores->isNotEmpty())
                    <section class="qs-student-shell p-4 sm:p-5">
                        <div class="mb-3 flex items-start gap-3">
                            <span class="qs-student-side-icon bg-emerald-100 text-emerald-800"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span>
                            <div>
                                <p class="qs-student-section-eyebrow">{{ __('Recent scores') }}</p>
                                <h3 class="text-sm font-bold text-slate-900">{{ __('Practice activity') }}</h3>
                            </div>
                        </div>
                        <ul class="space-y-2">
                            @foreach ($recentPracticeScores as $att)
                                <li class="qs-student-score-row">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $att->practiceQuiz?->title }}</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $att->practiceQuiz?->course?->code ?? __('Course') }}</p>
                                    </div>
                                    <span class="qs-student-score-pill">{{ $att->percentage !== null ? number_format((float) $att->percentage, 1).'%' : '—' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </section>

        <section id="student-exams" class="qs-student-shell overflow-hidden !p-0">
            <div class="flex flex-col gap-3 border-b border-slate-200/80 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <p class="qs-student-section-eyebrow">{{ __('Exam centre') }}</p>
                    <h3 class="text-base font-bold text-slate-950 sm:text-lg">{{ __('My courses and exams') }}</h3>
                </div>
                <a href="{{ route('student.results.index') }}" class="qs-student-pill !py-1.5 text-[11px]">{{ __('Manage results') }}</a>
            </div>

            @if (! $hasClass)
                <p class="px-4 py-6 text-sm leading-relaxed text-slate-600 sm:px-5">
                    <i class="fa-solid fa-circle-info mr-1 text-teal-600" aria-hidden="true"></i>
                    {{ __('student_ui.class_group_not_assigned') }}
                </p>
            @elseif ($coursesWithExams->isEmpty())
                <p class="px-4 py-6 text-sm text-slate-600 sm:px-5">{{ __('No published exams are linked to your class courses yet.') }}</p>
            @else
                <div class="grid gap-0 xl:grid-cols-[minmax(0,1.5fr)_minmax(19rem,0.85fr)]">
                    <div class="border-b border-slate-200/80 xl:border-b-0 xl:border-r" x-data="{ openCourse: @js($openCourseId) }">
                        <div class="divide-y divide-slate-100">
                            @foreach ($coursesWithExams as $courseId => $rows)
                                @php
                                    /** @var \App\Models\Quiz $headExam */
                                    $headExam = $rows->first()['exam'];
                                    $course = $headExam->course;
                                @endphp
                                <div class="bg-white">
                                    <button
                                        type="button"
                                        class="qs-student-course-head w-full !rounded-none !border-0 !shadow-none"
                                        @click="openCourse = openCourse === '{{ (string) $courseId }}' ? null : '{{ (string) $courseId }}'"
                                        :aria-expanded="openCourse === '{{ (string) $courseId }}' ? 'true' : 'false'"
                                    >
                                        <span class="flex min-w-0 items-center gap-3 text-left">
                                            <span class="qs-student-course-head__code">
                                                {{ $course?->code ? \Illuminate\Support\Str::limit($course->code, 4, '') : '—' }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-bold text-slate-900">{{ $course?->title ?? __('Course') }}</span>
                                                <span class="mt-0.5 block text-[11px] font-medium text-slate-500">{{ $rows->count() }} {{ $rows->count() === 1 ? __('exam') : __('exams') }}</span>
                                            </span>
                                        </span>
                                        <i class="fa-solid fa-chevron-down shrink-0 text-slate-400 transition" :class="openCourse === '{{ (string) $courseId }}' ? '-rotate-180' : ''" aria-hidden="true"></i>
                                    </button>
                                    <div x-show="openCourse === '{{ (string) $courseId }}'" x-cloak class="border-t border-slate-100 bg-slate-50/50 px-3 py-3 sm:px-4">
                                        <div class="qs-student-timeline space-y-2">
                                            @foreach ($rows as $row)
                                                @php
                                                    $exam = $row['exam'];
                                                    $p = (int) $row['progress'];
                                                    $state = $row['state'];
                                                    $href = $row['href'];
                                                    $ringColor = match ($state) {
                                                        'completed' => '#0d9488',
                                                        'in_progress' => '#2563eb',
                                                        'available' => '#14b8a6',
                                                        'upcoming', 'blocked' => '#94a3b8',
                                                        'closed' => '#cbd5e1',
                                                        default => '#94a3b8',
                                                    };
                                                @endphp
                                                <div class="qs-student-timeline__row">
                                                    <span class="qs-student-timeline__dot" style="background: {{ $state === 'completed' ? '#0d9488' : ($state === 'in_progress' ? '#2563eb' : '#94a3b8') }}"></span>
                                                    <div class="qs-student-ring shrink-0" style="--qs-student-ring: {{ $ringColor }}; --qs-p: {{ $p }};">
                                                        <span class="qs-student-ring__hole">{{ $p }}%</span>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-semibold text-slate-900">{{ $exam->title }}</p>
                                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $row['label'] }}@if ($exam->duration_minutes) · {{ $exam->duration_minutes }} {{ __('min') }}@endif</p>
                                                        @if ($exam->start_time && in_array($state, ['upcoming', 'available', 'blocked'], true))
                                                            <p class="mt-1 text-[10px] text-slate-400">
                                                                {{ __('Opens') }}: {{ $exam->start_time->timezone(config('app.timezone'))->format('M j, H:i') }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                    @if ($href && in_array($state, ['completed', 'in_progress', 'available', 'closed'], true))
                                                        <a href="{{ $href }}" class="qs-student-action" title="{{ __('Open') }}">
                                                            @if (in_array($state, ['completed', 'closed'], true))
                                                                <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i>
                                                            @else
                                                                <i class="fa-solid fa-play text-xs pl-0.5" aria-hidden="true"></i>
                                                            @endif
                                                        </a>
                                                    @else
                                                        <span class="qs-student-action qs-student-action--muted cursor-default" title="{{ $row['label'] }}">
                                                            <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <aside id="student-agenda" class="bg-[#fbfcfb] px-4 py-4 sm:px-5">
                        <p class="qs-student-section-eyebrow">{{ __('Agenda') }}</p>
                        <h4 class="text-sm font-bold text-slate-950">{{ __('What needs attention') }}</h4>

                        <div class="mt-4 space-y-3">
                            <div class="qs-student-agenda-card">
                                <div class="flex items-start gap-3">
                                    <span class="qs-student-side-icon bg-emerald-100 text-emerald-800"><i class="fa-solid fa-play" aria-hidden="true"></i></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ __('Ready to start') }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                            {{ $availableCount > 0 ? trans_choice('{1} You have :count exam available now.|[2,*] You have :count exams available now.', $availableCount, ['count' => number_format($availableCount)]) : __('No exam is open right now.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="qs-student-agenda-card">
                                <div class="flex items-start gap-3">
                                    <span class="qs-student-side-icon bg-sky-100 text-sky-800"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ __('Coming up') }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                            {{ $upcomingCount > 0 ? trans_choice('{1} :count exam is scheduled next.|[2,*] :count exams are scheduled next.', $upcomingCount, ['count' => number_format($upcomingCount)]) : __('No upcoming exam is scheduled yet.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="qs-student-agenda-card">
                                <div class="flex items-start gap-3">
                                    <span class="qs-student-side-icon bg-amber-100 text-amber-800"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ __('Result follow-up') }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                            {{ ($reviewCount + $awaitingCount) > 0 ? trans_choice('{1} :count result still needs attention.|[2,*] :count results still need attention.', $reviewCount + $awaitingCount, ['count' => number_format($reviewCount + $awaitingCount)]) : __('All visible result updates look settled.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="qs-student-agenda-card">
                                <div class="flex items-start gap-3">
                                    <span class="qs-student-side-icon bg-slate-100 text-slate-700"><i class="fa-solid fa-shield-check" aria-hidden="true"></i></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ __('Profile readiness') }}</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $studentProfileReady ? __('You are ready for scheduled exams.') : __('Finish onboarding if your school asks you to complete profile setup.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            @endif
        </section>
    </div>

</x-layouts.student>
