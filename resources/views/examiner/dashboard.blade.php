<x-layouts.examiner>
    <x-slot name="title">{{ __('Examiner Dashboard') }}</x-slot>
    <x-slot name="subtitle">{{ __('Scoped to courses you are assigned to examine. Exam counts and results only include exams you created.') }}</x-slot>

    <x-slot name="headingActions">
        @if ($academicYears->isNotEmpty())
            <form method="get" action="{{ route('examiner.dashboard') }}" class="flex items-center gap-2">
                <label for="examiner-dashboard-year" class="sr-only">{{ __('Academic year filter') }}</label>
                <select
                    id="examiner-dashboard-year"
                    name="academic_year_id"
                    class="max-w-[14rem] rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs font-medium text-slate-800 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500/25"
                    onchange="this.form.submit()"
                >
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((int) $selectedAcademicYearId === (int) $year->id)>
                            {{ $year->name }}{{ $year->is_active ? ' · '.__('active') : '' }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </x-slot>

    @php
        $metricIcon = 'inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-sm text-slate-600';
        $metricCard = 'rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm';
    @endphp

    <div class="space-y-6">
        <section aria-labelledby="examiner-metrics-heading">
            <h2 id="examiner-metrics-heading" class="sr-only">{{ __('Dashboard metrics') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <article class="{{ $metricCard }}">
                    <div class="flex items-start gap-3">
                        <span class="{{ $metricIcon }}" aria-hidden="true"><i class="fa-solid fa-book"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-semibold tabular-nums leading-tight text-slate-900">{{ $manageableCourseCount }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Courses in scope') }}</p>
                        </div>
                    </div>
                </article>
                <article class="{{ $metricCard }}">
                    <div class="flex items-start gap-3">
                        <span class="{{ $metricIcon }}" aria-hidden="true"><i class="fa-solid fa-file-pen"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-semibold tabular-nums leading-tight text-slate-900">{{ $draftExamCount }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Draft exams') }}</p>
                        </div>
                    </div>
                </article>
                <article class="{{ $metricCard }}">
                    <div class="flex items-start gap-3">
                        <span class="{{ $metricIcon }}" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-semibold tabular-nums leading-tight text-slate-900">{{ $publishedExamCount }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Published exams') }}</p>
                        </div>
                    </div>
                </article>
                <article class="{{ $metricCard }}">
                    <div class="flex items-start gap-3">
                        <span class="{{ $metricIcon }}" aria-hidden="true"><i class="fa-solid fa-pause"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-semibold tabular-nums leading-tight text-slate-900">{{ $heldResultsCount }}</p>
                            <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Held results') }}</p>
                        </div>
                    </div>
                </article>
                <article class="{{ $metricCard }} col-span-2 sm:col-span-3 lg:col-span-1">
                    <div class="flex h-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between lg:flex-col lg:items-stretch">
                        <div class="flex items-start gap-3">
                            <span class="{{ $metricIcon }}" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-2xl font-semibold tabular-nums leading-tight text-slate-900">{{ $pendingManualGradingCount }}</p>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Pending manual grading') }}</p>
                            </div>
                        </div>
                        <a
                            href="{{ route('examiner.grading.pending') }}"
                            class="inline-flex min-h-[40px] w-full shrink-0 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-800 transition-colors hover:bg-slate-100 sm:w-auto lg:w-full"
                        >
                            {{ __('Open queue') }}
                            <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="examiner-shortcuts-heading">
                <h2 id="examiner-shortcuts-heading" class="text-sm font-semibold text-slate-900">{{ __('Workspace') }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ __('Jump to the main examiner tools.') }}</p>
                <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                    <li>
                        <a href="{{ route('examiner.exams.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-lg border border-slate-200/90 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-800 transition-colors hover:border-slate-300 hover:bg-white">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-slate-600 shadow-sm ring-1 ring-slate-200/80" aria-hidden="true"><i class="fa-solid fa-file-lines text-sm"></i></span>
                            <span class="min-w-0">{{ __('My exams') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('examiner.exams.create') }}" class="flex min-h-[44px] items-center gap-3 rounded-lg border border-slate-200/90 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-800 transition-colors hover:border-slate-300 hover:bg-white">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-slate-600 shadow-sm ring-1 ring-slate-200/80" aria-hidden="true"><i class="fa-solid fa-plus text-sm"></i></span>
                            <span class="min-w-0">{{ __('Create exam') }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('examiner.grading.pending') }}" class="flex min-h-[44px] items-center gap-3 rounded-lg border border-slate-200/90 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-800 transition-colors hover:border-slate-300 hover:bg-white">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-slate-600 shadow-sm ring-1 ring-slate-200/80" aria-hidden="true"><i class="fa-solid fa-clipboard-check text-sm"></i></span>
                            <span class="min-w-0 flex flex-1 items-center justify-between gap-2">
                                {{ __('Essay grading') }}
                                @if ($pendingManualGradingCount > 0)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-amber-900">{{ $pendingManualGradingCount }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('examiner.exams.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-lg border border-slate-200/90 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-800 transition-colors hover:border-slate-300 hover:bg-white">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-slate-600 shadow-sm ring-1 ring-slate-200/80" aria-hidden="true"><i class="fa-solid fa-chart-simple text-sm"></i></span>
                            <span class="min-w-0">{{ __('Sessions & held review') }}</span>
                        </a>
                    </li>
                    @if (! empty($practiceOverviewEnabled))
                        <li>
                            <a href="{{ route('examiner.practice-overview.index') }}" class="flex min-h-[44px] items-center gap-3 rounded-lg border border-slate-200/90 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-800 transition-colors hover:border-slate-300 hover:bg-white">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-slate-600 shadow-sm ring-1 ring-slate-200/80" aria-hidden="true"><i class="fa-solid fa-book-open-reader text-sm"></i></span>
                                <span class="min-w-0">{{ __('Practice overview') }}</span>
                            </a>
                        </li>
                    @endif
                    @if (! empty($materialUploadsEnabled) && $firstManageableCourse)
                        <li class="@if (empty($practiceOverviewEnabled)) sm:col-span-2 @endif">
                            <a href="{{ route('examiner.courses.materials.index', $firstManageableCourse) }}" class="flex min-h-[44px] items-center gap-3 rounded-lg border border-slate-200/90 bg-slate-50/80 px-3 py-2.5 text-sm font-medium text-slate-800 transition-colors hover:border-slate-300 hover:bg-white">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-slate-600 shadow-sm ring-1 ring-slate-200/80" aria-hidden="true"><i class="fa-solid fa-folder-open text-sm"></i></span>
                                <span class="min-w-0">{{ __('Course materials') }} <span class="font-normal text-slate-500">({{ $firstManageableCourse->code }})</span></span>
                            </a>
                        </li>
                    @endif
                </ul>
            </section>

            <section class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="examiner-courses-heading">
                <h2 id="examiner-courses-heading" class="text-sm font-semibold text-slate-900">{{ __('Assigned examiner courses') }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ __('Courses your coordinator linked to your examiner account, with linked classes for context.') }}</p>
                @if ($assignedCourses->isEmpty())
                    <p class="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-3 text-xs leading-relaxed text-slate-600">{{ __('No course assignments yet. Your coordinator can assign you from the coordinator dashboard.') }}</p>
                @else
                    <ul class="mt-4 max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-100">
                        @foreach ($assignedCourses as $course)
                            <li class="px-3 py-2.5 text-sm text-slate-800">
                                <div class="flex items-start gap-2">
                                    <i class="fa-solid fa-bookmark mt-0.5 text-xs text-slate-400" aria-hidden="true"></i>
                                    <span class="min-w-0"><span class="font-semibold tabular-nums text-slate-900">{{ $course->code }}</span> — {{ $course->title }}</span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1.5 ps-5">
                                    @forelse ($course->classrooms as $classroom)
                                        <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ $classroom->name }}
                                        </span>
                                    @empty
                                        <span class="text-[11px] text-slate-500">{{ __('No class linked yet') }}</span>
                                    @endforelse
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <section class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="examiner-flagged-heading">
            <h2 id="examiner-flagged-heading" class="text-sm font-semibold text-slate-900">{{ __('Recent flagged sessions') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('From your exams only, limited to the most recent six.') }}</p>
            @if ($flaggedSessions->isEmpty())
                <p class="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-3 text-xs text-slate-600">{{ __('No flagged sessions in your scope for the selected academic year.') }}</p>
            @else
                <div class="qs-table-wrap mt-4 border border-slate-200/80">
                    <table class="qs-table">
                        <thead>
                            <tr>
                                <th>{{ __('Student') }}</th>
                                <th>{{ __('Exam') }}</th>
                                <th>{{ __('Course') }}</th>
                                <th class="text-right">{{ __('Review') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($flaggedSessions as $session)
                                <tr>
                                    <td class="text-sm text-qs-text">{{ $session->student?->name ?? '—' }}</td>
                                    <td class="text-sm text-qs-text">{{ $session->exam?->title ?? '—' }}</td>
                                    <td class="text-sm text-qs-muted">{{ $session->exam?->course?->code ?? '—' }}</td>
                                    <td class="text-right">
                                        @if ($session->exam)
                                            <a href="{{ route('examiner.exams.sessions.index', $session->exam) }}" class="inline-flex items-center gap-1 text-xs font-medium text-qs-primary underline-offset-2 hover:underline sm:text-sm">
                                                <i class="fa-solid fa-chart-simple text-[10px]" aria-hidden="true"></i>
                                                {{ __('Sessions') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.examiner>
