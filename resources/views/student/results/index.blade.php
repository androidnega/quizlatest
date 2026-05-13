<x-layouts.student>
    <x-slot name="title">{{ __('Results') }}</x-slot>
    <x-slot name="subtitle">{{ __('Official class quizzes — scores and grading status.') }}</x-slot>

    @php
        $tz = config('app.timezone');
        $total = $sessions->count();
        $gradedN = $sessions->filter(fn ($s) => $s->result?->status === 'graded')->count();
        $awaitingN = $total - $gradedN;
    @endphp

    <div class="w-full min-w-0 space-y-5 pb-4 text-slate-950 md:space-y-6">
        {{-- Academic year context (active year by default; optional “all years” or pick) --}}
        @if ($academicYears->isNotEmpty())
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-4 sm:px-5 sm:py-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-sky-900/75">{{ __('Academic year') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ __('Showing: :label', ['label' => $resultsFilterLabel]) }}
                        </p>
                        @if ($defaultsToActiveYear)
                            <p class="mt-1 text-xs text-sky-900/80">{{ __('Using your school’s active year automatically. Switch below if you need another year.') }}</p>
                        @elseif ($resultsShowingAllYears)
                            <p class="mt-1 text-xs text-sky-900/80">{{ __('Every submitted exam for your account is included.') }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                        @if ($resultsShowingAllYears)
                            <a
                                href="{{ route('student.results.index') }}"
                                class="inline-flex items-center justify-center rounded-xl border border-sky-300/90 bg-white px-3 py-2 text-xs font-semibold text-sky-950 transition hover:bg-sky-100 sm:text-sm"
                            >{{ __('Active year only') }}</a>
                        @else
                            <a
                                href="{{ route('student.results.index', ['all_years' => 1]) }}"
                                class="inline-flex items-center justify-center rounded-xl border border-sky-300/90 bg-white px-3 py-2 text-xs font-semibold text-sky-950 transition hover:bg-sky-100 sm:text-sm"
                            >{{ __('All years') }}</a>
                        @endif
                        @if ($academicYears->count() > 1 && ! $resultsShowingAllYears)
                            <form method="get" action="{{ route('student.results.index') }}" class="flex items-center gap-2">
                                <label for="results-year-jump" class="sr-only">{{ __('Jump to year') }}</label>
                                <select
                                    id="results-year-jump"
                                    name="academic_year_id"
                                    class="rounded-xl border border-sky-200 bg-white px-3 py-2 text-xs font-medium text-slate-900 focus:border-[var(--qs-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--qs-primary)]/20 sm:text-sm"
                                    onchange="this.form.submit()"
                                >
                                    @foreach ($academicYears as $y)
                                        <option value="{{ $y->id }}" @selected($resultsFocusedYearId === (int) $y->id)>
                                            {{ $y->name }}@if ($y->is_active) — {{ __('Active') }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Summary --}}
        <section class="grid grid-cols-3 gap-2 sm:gap-4">
            <article class="flex min-w-0 flex-col items-center gap-1 rounded-xl border border-slate-200 bg-slate-100 px-2 py-3 text-center sm:rounded-[1.25rem] sm:px-4 sm:py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-slate-600 ring-1 ring-slate-200/80 sm:h-10 sm:w-10" aria-hidden="true">
                    <i class="fa-solid fa-file-lines text-sm sm:text-base"></i>
                </div>
                <p class="text-lg font-semibold tabular-nums text-slate-900 sm:text-xl">{{ number_format($total) }}</p>
                <p class="text-[10px] font-medium uppercase tracking-wide text-slate-600 sm:text-xs">{{ __('Submissions') }}</p>
            </article>
            <article class="flex min-w-0 flex-col items-center gap-1 rounded-xl border border-emerald-200/90 bg-emerald-50 px-2 py-3 text-center sm:rounded-[1.25rem] sm:px-4 sm:py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-emerald-700 ring-1 ring-emerald-200/80 sm:h-10 sm:w-10" aria-hidden="true">
                    <i class="fa-solid fa-circle-check text-sm sm:text-base"></i>
                </div>
                <p class="text-lg font-semibold tabular-nums text-slate-900 sm:text-xl">{{ number_format($gradedN) }}</p>
                <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-900/80 sm:text-xs">{{ __('Released') }}</p>
            </article>
            <article class="flex min-w-0 flex-col items-center gap-1 rounded-xl border border-amber-200/90 bg-amber-50 px-2 py-3 text-center sm:rounded-[1.25rem] sm:px-4 sm:py-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-amber-800 ring-1 ring-amber-200/80 sm:h-10 sm:w-10" aria-hidden="true">
                    <i class="fa-solid fa-hourglass-half text-sm sm:text-base"></i>
                </div>
                <p class="text-lg font-semibold tabular-nums text-slate-900 sm:text-xl">{{ number_format($awaitingN) }}</p>
                <p class="text-[10px] font-medium uppercase tracking-wide text-amber-900/80 sm:text-xs">{{ __('Awaiting') }}</p>
            </article>
        </section>

        {{-- List --}}
        <section class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">{{ __('Your exams') }}</h2>
                <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-[var(--qs-primary)] hover:underline sm:text-sm">{{ __('Back to home') }}</a>
            </div>

            <div class="space-y-3">
                @forelse ($sessions as $s)
                    @php
                        $r = $s->result;
                        $label = match ($r?->status) {
                            'held' => __('Under review'),
                            'pending_manual' => __('Pending grading'),
                            'graded' => __('Graded'),
                            default => __('Processing'),
                        };
                        $badgeClass = match ($r?->status) {
                            'held' => 'bg-amber-100 text-amber-900 ring-amber-200/80',
                            'pending_manual' => 'bg-sky-100 text-sky-900 ring-sky-200/80',
                            'graded' => 'bg-emerald-100 text-emerald-900 ring-emerald-200/80',
                            default => 'bg-slate-100 text-slate-700 ring-slate-200/80',
                        };
                    @endphp
                    <a
                        href="{{ route('student.results.show', $s) }}"
                        class="group flex flex-col gap-3 rounded-[1.25rem] border border-slate-200 bg-white p-4 transition hover:border-[var(--qs-primary)]/40 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:p-5"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-slate-900 group-hover:text-[var(--qs-primary)] sm:text-lg">{{ $s->exam?->title ?? __('Exam') }}</h3>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 sm:text-xs {{ $badgeClass }}">{{ $label }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 sm:text-sm">
                                @if ($s->exam?->course?->code)
                                    <span class="font-medium text-slate-600">{{ $s->exam->course->code }}</span>
                                @endif
                                @if ($s->end_time)
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-regular fa-calendar text-slate-400" aria-hidden="true"></i>
                                        {{ $s->end_time->timezone($tz)->format('M j, Y · H:i') }}
                                    </span>
                                @endif
                            </div>
                            @if ($r && $r->status === 'graded')
                                <p class="mt-2 text-sm font-semibold text-slate-800">
                                    {{ __('Score') }}:
                                    <span class="tabular-nums text-[var(--qs-primary)]">{{ $r->score }}</span>
                                    <span class="font-medium text-slate-500">/ {{ $s->exam?->total_marks ?? '—' }}</span>
                                </p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center justify-end gap-2 sm:flex-col sm:items-end">
                            @if ($r && $r->status === 'graded' && $s->exam && (float) ($s->exam->total_marks ?? 0) > 0)
                                @php $pct = round(((float) $r->score) / (float) $s->exam->total_marks * 100, 1); @endphp
                                <span class="inline-flex min-w-[3.25rem] justify-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold tabular-nums text-emerald-900 ring-1 ring-emerald-200/90">{{ $pct }}%</span>
                            @endif
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-400 transition group-hover:border-[var(--qs-primary)]/30 group-hover:bg-[var(--qs-primary)]/10 group-hover:text-[var(--qs-primary)]" aria-hidden="true">
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 px-6 py-14 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500">
                            <i class="fa-solid fa-square-poll-vertical text-2xl" aria-hidden="true"></i>
                        </div>
                        <p class="mt-4 text-base font-semibold text-slate-900">{{ __('No submitted exams yet.') }}</p>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">{{ __('When you finish a class quiz, it will appear here with grading status and your score once released.') }}</p>
                        <a href="{{ route('dashboard') }}" class="mt-6 inline-flex items-center justify-center rounded-xl bg-[var(--qs-primary)] px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-95">
                            {{ __('Go to dashboard') }}
                        </a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.student>
