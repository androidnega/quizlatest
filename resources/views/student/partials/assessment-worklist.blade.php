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
@endphp

<section id="student-work" class="scroll-mt-4" aria-labelledby="student-assessment-worklist-heading">
    <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04]">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50/90 via-white to-emerald-50/20 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5 sm:py-4">
            <div class="min-w-0">
                <h2 id="student-assessment-worklist-heading" class="text-base font-semibold tracking-tight text-slate-900">{{ __('Your work') }}</h2>
                <p class="mt-1 max-w-xl text-xs leading-relaxed text-slate-600 sm:text-sm">{{ __('Assessments grouped by what to do next. Primary action on the right.') }}</p>
            </div>
            <a
                href="{{ route('student.assignments.index') }}"
                class="inline-flex shrink-0 items-center justify-center self-start rounded-xl border border-slate-200/90 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/50 sm:min-h-0"
            >
                {{ __('All assignments') }}
            </a>
        </div>

        <div class="divide-y divide-slate-100">
            @foreach ($sections as $key => $meta)
                @php $rows = $deck[$key] ?? []; $count = count($rows); @endphp
                <div class="bg-white">
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $meta['title'] }}</h3>
                        @if ($count > 0)
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-emerald-800 ring-1 ring-emerald-100">{{ $count }}</span>
                        @endif
                    </div>

                    @if ($rows === [])
                        <p class="border-t border-slate-50 bg-slate-50/40 px-4 py-2.5 text-xs text-slate-500 sm:px-5">{{ $meta['empty'] }}</p>
                    @else
                        <ul class="border-t border-slate-100">
                            @foreach ($rows as $row)
                                <li class="border-b border-slate-100 last:border-b-0">
                                    <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-stretch sm:justify-between sm:gap-4 sm:px-5 sm:py-4">
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <div>
                                                <p class="text-sm font-semibold leading-snug text-slate-900">{{ $row['title'] }}</p>
                                                @if (! empty($row['course_line']))
                                                    <p class="mt-0.5 truncate text-xs text-slate-600">{{ $row['course_line'] }}</p>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-[11px] text-slate-600">
                                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-800">{{ $row['type_label'] }}</span>
                                                @if (! empty($row['submission_format']))
                                                    <span class="text-slate-400" aria-hidden="true">·</span>
                                                    <span>{{ $row['submission_format'] }}</span>
                                                @endif
                                                @if (! empty($row['paste_notice']))
                                                    <span class="text-slate-400" aria-hidden="true">·</span>
                                                    <span class="font-medium text-amber-800">{{ $row['paste_notice'] }}</span>
                                                @endif
                                                @if (! empty($row['due_line']))
                                                    <span class="text-slate-400" aria-hidden="true">·</span>
                                                    <span>{{ $row['due_line'] }}</span>
                                                @endif
                                                @if (! empty($row['time_limit_line']))
                                                    <span class="text-slate-400" aria-hidden="true">·</span>
                                                    <span>{{ $row['time_limit_line'] }}</span>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                                <p class="text-xs font-semibold text-slate-800">{{ $row['status_label'] }}</p>
                                                @if (! empty($row['score_line']))
                                                    <span class="text-slate-400" aria-hidden="true">·</span>
                                                    <p class="text-xs text-slate-600">{{ $row['score_line'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:min-w-[10.5rem] sm:justify-center">
                                            @if (! empty($row['secondary_href']))
                                                <a
                                                    href="{{ $row['secondary_href'] }}"
                                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 transition hover:bg-slate-50 sm:w-full"
                                                >
                                                    {{ $row['secondary_label'] ?? __('More') }}
                                                </a>
                                            @endif
                                            @if (! empty($row['action_href']))
                                                <a
                                                    href="{{ $row['action_href'] }}"
                                                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-xl bg-slate-900 px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 sm:w-full"
                                                >
                                                    {{ $row['action_label'] }}
                                                </a>
                                            @else
                                                <span class="inline-flex min-h-[44px] w-full items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-3 text-center text-xs font-semibold text-slate-500 sm:w-full">{{ $row['action_label'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
