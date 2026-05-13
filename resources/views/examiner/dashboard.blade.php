<x-layouts.examiner>
    <x-slot name="title">{{ __('Overview') }}</x-slot>
    <x-slot name="subtitle">{{ __('Manage class groups, assessments, and session results.') }}</x-slot>

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
        $cardBase = 'relative overflow-hidden rounded-xl border p-3 shadow-sm sm:p-4';
        $cardTint = 'border-sky-200/80 bg-sky-50/90';
        $cardPlain = 'border-slate-200/90 bg-white';
        $iconWrap = 'absolute end-2.5 top-2.5 inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs ring-1 ring-slate-200/80';
        $iconTint = 'bg-white/90 text-sky-600';
        $iconMuted = 'bg-slate-50 text-slate-600';
        $qaCount = 'inline-flex min-h-[1.25rem] min-w-[1.25rem] shrink-0 items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums leading-none ring-1';
    @endphp

    <div class="space-y-5">
        <section aria-labelledby="examiner-overview-metrics">
            <h2 id="examiner-overview-metrics" class="sr-only">{{ __('Overview metrics') }}</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="{{ $cardBase }} {{ $cardTint }}">
                    <span class="{{ $iconWrap }} {{ $iconTint }}" aria-hidden="true"><i class="fa-solid fa-file-lines"></i></span>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Assessments') }}</p>
                    <p class="mt-0.5 text-2xl font-semibold tabular-nums text-slate-900">{{ $quizTotalCount }}</p>
                    <a href="{{ route('examiner.exams.index') }}" class="mt-2 inline-flex text-sm font-medium text-sky-700 underline-offset-2 hover:underline">
                        {{ __('View assessments') }}
                    </a>
                </article>

                <article class="{{ $cardBase }} {{ $cardPlain }}">
                    <span class="{{ $iconWrap }} {{ $iconMuted }}" aria-hidden="true"><i class="fa-solid fa-user-group"></i></span>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Class groups') }}</p>
                    <p class="mt-0.5 text-2xl font-semibold tabular-nums text-slate-900">{{ $classesInScopeCount }}</p>
                    <p class="mt-1.5 text-xs text-slate-500">{{ __('Manage students per group') }}</p>
                </article>

                <article class="{{ $cardBase }} {{ $cardTint }}">
                    <span class="{{ $iconWrap }} {{ $iconTint }}" aria-hidden="true"><i class="fa-solid fa-users-line"></i></span>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Sessions') }}</p>
                    <p class="mt-0.5 text-2xl font-semibold tabular-nums text-slate-900">{{ $sessionsCount }}</p>
                    <a href="{{ route('examiner.exams.index') }}" class="mt-2 inline-flex text-sm font-medium text-sky-700 underline-offset-2 hover:underline">
                        {{ __('View from assessment list') }}
                    </a>
                </article>

                <article class="{{ $cardBase }} {{ $cardPlain }}">
                    <span class="{{ $iconWrap }} {{ $iconMuted }}" aria-hidden="true"><i class="fa-solid fa-chart-simple"></i></span>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Results') }}</p>
                    <p class="mt-0.5 text-2xl font-semibold tabular-nums text-slate-900">{{ $resultsCount }}</p>
                    <p class="mt-1.5 text-xs text-slate-500">{{ __('Open from an assessment list, then sessions or results.') }}</p>
                </article>
            </div>
        </section>

        <section class="min-w-0 rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm sm:p-4" aria-labelledby="examiner-quick-actions-heading">
            <h2 id="examiner-quick-actions-heading" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Quick actions') }}</h2>
            <div class="mt-3 flex min-w-0 flex-wrap gap-2">
                <a href="{{ route('examiner.exams.create') }}" class="qs-btn-primary inline-flex min-h-[40px] items-center justify-center rounded-lg px-4 text-sm font-semibold">
                    {{ __('Create assessment') }}
                </a>
                <a href="{{ route('examiner.exams.index') }}" class="inline-flex min-h-[40px] max-w-full min-w-0 items-center gap-0.5 rounded-lg border border-slate-200 bg-slate-50 px-3.5 pe-3 text-sm font-semibold text-slate-800 hover:bg-slate-100">
                    <span class="min-w-0 truncate">{{ __('All assessments') }}</span>
                    <span class="{{ $qaCount }} bg-sky-100 text-sky-900 ring-sky-300/60">{{ $quizTotalCount }}</span>
                </a>
                <a href="{{ route('examiner.courses.index') }}" class="inline-flex min-h-[40px] max-w-full min-w-0 items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-3.5 pe-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <span class="min-w-0 truncate">{{ __('Courses') }}</span>
                    <span class="{{ $qaCount }} bg-emerald-100 text-emerald-900 ring-emerald-300/60">{{ $assignedCoursesCount }}</span>
                </a>
                <a href="{{ route('examiner.teaching-classes.index') }}" class="inline-flex min-h-[40px] max-w-full min-w-0 items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-3.5 pe-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <span class="min-w-0 truncate">{{ __('Classes') }}</span>
                    <span class="{{ $qaCount }} bg-violet-100 text-violet-900 ring-violet-300/60">{{ $classesInScopeCount }}</span>
                </a>
                <a href="{{ route('examiner.grading.pending') }}" class="inline-flex min-h-[40px] max-w-full min-w-0 items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-3.5 pe-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                    <span class="min-w-0 truncate">{{ __('Grading') }}</span>
                    <span
                        @class([
                            $qaCount,
                            'bg-amber-200 text-amber-950 ring-amber-400/50' => $pendingManualGradingCount > 0,
                            'bg-rose-100 text-rose-900 ring-rose-300/60' => $pendingManualGradingCount === 0,
                        ])
                    >{{ $pendingManualGradingCount }}</span>
                </a>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-black/[0.04]"
            aria-labelledby="examiner-status-heading"
        >
            <div class="relative border-b border-slate-100 bg-gradient-to-br from-slate-50 via-white to-sky-50/30 px-4 py-3 sm:px-5 sm:py-3.5">
                <div class="pointer-events-none absolute end-0 top-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-sky-400/[0.07] blur-2xl" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-0.5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="examiner-status-heading" class="text-sm font-bold tracking-tight text-slate-900">{{ __('Results & grading') }}</h2>
                        <p class="mt-0.5 max-w-xl text-[11px] leading-snug text-slate-500">{{ __('Held submissions and essay grading tied to your assessments for the selected academic year.') }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 p-4 sm:p-5">
                <div class="mx-auto grid w-full max-w-2xl grid-cols-2 gap-2.5 sm:gap-3">
                    <a
                        href="{{ route('examiner.exams.index') }}"
                        class="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-slate-50/60 p-3 ring-1 ring-black/[0.02] transition hover:border-amber-200/90 hover:bg-amber-50/40 hover:ring-amber-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2"
                    >
                        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-white text-amber-600 shadow-sm ring-1 ring-slate-200/80 transition group-hover:text-amber-700" aria-hidden="true">
                            <i class="fa-solid fa-pause text-xs"></i>
                        </span>
                        <span class="mt-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Held results') }}</span>
                        <span class="mt-0.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ $heldResultsCount }}</span>
                        <span class="pointer-events-none absolute bottom-2 start-3 end-3 line-clamp-2 max-w-full text-center text-[10px] font-medium leading-tight text-sky-700 opacity-0 transition group-hover:pointer-events-auto group-hover:opacity-100 break-words">{{ __('Open assessments') }} →</span>
                    </a>
                    <a
                        href="{{ route('examiner.grading.pending') }}"
                        class="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-slate-50/60 p-3 ring-1 ring-black/[0.02] transition hover:border-sky-200/90 hover:bg-sky-50/50 hover:ring-sky-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2"
                    >
                        <span class="inline-flex size-8 items-center justify-center rounded-lg bg-white text-sky-600 shadow-sm ring-1 ring-slate-200/80 transition group-hover:text-sky-700" aria-hidden="true">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </span>
                        <span class="mt-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Pending manual grading') }}</span>
                        <span class="mt-0.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ $pendingManualGradingCount }}</span>
                        <span class="pointer-events-none absolute bottom-2 start-3 end-3 line-clamp-2 max-w-full text-center text-[10px] font-medium leading-tight text-sky-700 opacity-0 transition group-hover:pointer-events-auto group-hover:opacity-100 break-words">{{ __('Open grading queue') }} →</span>
                    </a>
                </div>
            </div>
        </section>

    </div>
</x-layouts.examiner>
