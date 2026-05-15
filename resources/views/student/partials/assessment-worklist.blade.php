@php
    /** @var array<string, array<int, array<string, mixed>>> $studentAssessmentDeck */
    $deck = $studentAssessmentDeck ?? [];
    $sections = [
        'active_now' => ['title' => __('Active now'), 'empty' => __('Nothing open to start.')],
        'continue' => ['title' => __('Continue'), 'empty' => __('Nothing in progress.')],
        'assignments_due' => ['title' => __('Assignments due'), 'empty' => __('No assignments here.')],
        'upcoming' => ['title' => __('Upcoming'), 'empty' => __('Nothing scheduled ahead.')],
        'submitted_work' => ['title' => __('Submitted work'), 'empty' => __('Nothing waiting here.')],
        'results_released' => ['title' => __('Results released'), 'empty' => __('No released results yet.')],
        'closed_missed' => ['title' => __('Closed or missed'), 'empty' => __('Nothing to show.')],
    ];
    $printedSection = false;
@endphp

<section id="student-work" class="scroll-mt-4" aria-labelledby="student-worklist-heading">
    <h2 id="student-worklist-heading" class="sr-only">{{ __('Assessments by status') }}</h2>

    <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-emerald-50/40 via-white to-slate-50/80 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:py-3.5">
            <p class="text-sm text-slate-600">{{ __('Each card is one assessment. Your main action is on the right.') }}</p>
            <a
                href="{{ route('student.assignments.index') }}"
                class="inline-flex shrink-0 items-center justify-center self-stretch rounded-xl border border-slate-200/90 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/60 sm:self-auto"
            >
                {{ __('All assignments') }}
            </a>
        </div>

        <div class="px-4 py-4 sm:px-5 sm:py-5">
            @foreach ($sections as $key => $meta)
                @php $rows = $deck[$key] ?? []; @endphp
                @continue($rows === [])

                <div class="{{ $printedSection ? 'mt-8 border-t border-slate-100 pt-8' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-emerald-900/90">{{ $meta['title'] }}</h3>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-emerald-900 ring-1 ring-emerald-100/90">{{ count($rows) }}</span>
                    </div>

                    <div class="mt-3 grid gap-3">
                        @foreach ($rows as $row)
                            <article
                                class="flex flex-col gap-4 rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white to-slate-50/40 p-4 shadow-sm ring-1 ring-slate-900/[0.03] sm:flex-row sm:items-stretch sm:justify-between sm:gap-5 sm:p-5"
                            >
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div>
                                        <p class="text-sm font-semibold leading-snug text-slate-900">{{ $row['title'] }}</p>
                                        @if (! empty($row['course_line']))
                                            <p class="mt-0.5 truncate text-xs text-slate-600">{{ $row['course_line'] }}</p>
                                        @endif
                                    </div>
                                    <p class="text-[11px] leading-relaxed text-slate-600">
                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-800">{{ $row['type_label'] }}</span>
                                        @if (! empty($row['submission_format']))
                                            <span class="mx-1.5 text-slate-300" aria-hidden="true">·</span>
                                            <span>{{ $row['submission_format'] }}</span>
                                        @endif
                                        @if (! empty($row['paste_notice']))
                                            <span class="mx-1.5 text-slate-300" aria-hidden="true">·</span>
                                            <span class="font-medium text-amber-800">{{ $row['paste_notice'] }}</span>
                                        @endif
                                        @if (! empty($row['due_line']))
                                            <span class="mx-1.5 text-slate-300" aria-hidden="true">·</span>
                                            <span>{{ $row['due_line'] }}</span>
                                        @endif
                                        @if (! empty($row['time_limit_line']))
                                            <span class="mx-1.5 text-slate-300" aria-hidden="true">·</span>
                                            <span>{{ $row['time_limit_line'] }}</span>
                                        @endif
                                    </p>
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-xs">
                                        <span class="font-semibold text-slate-800">{{ $row['status_label'] }}</span>
                                        @if (! empty($row['score_line']))
                                            <span class="text-slate-300" aria-hidden="true">·</span>
                                            <span class="text-slate-600">{{ $row['score_line'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex w-full shrink-0 flex-col justify-center gap-2 sm:w-[11.5rem]">
                                    @if (! empty($row['secondary_href']))
                                        <a
                                            href="{{ $row['secondary_href'] }}"
                                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 transition hover:bg-slate-50"
                                        >
                                            {{ $row['secondary_label'] ?? __('More') }}
                                        </a>
                                    @endif
                                    @if (! empty($row['action_href']))
                                        <a
                                            href="{{ $row['action_href'] }}"
                                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800"
                                        >
                                            {{ $row['action_label'] }}
                                        </a>
                                    @else
                                        <span class="inline-flex min-h-[44px] w-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/90 px-3 text-center text-xs font-semibold text-slate-500">{{ $row['action_label'] }}</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
                @php $printedSection = true; @endphp
            @endforeach

            @if (! $printedSection)
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-10 text-center sm:px-6">
                    <p class="text-sm font-medium text-slate-800">{{ __('Nothing here yet') }}</p>
                    <p class="mx-auto mt-2 max-w-md text-xs leading-relaxed text-slate-600">
                        {{ __('When your class has open windows, due assignments, or released results, they will show as cards in the sections above.') }}
                    </p>
                    <a
                        href="{{ route('student.assignments.index') }}"
                        class="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-xl bg-emerald-700 px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800"
                    >
                        {{ __('Browse assignments') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
