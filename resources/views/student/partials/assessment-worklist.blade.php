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
    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
            <div class="min-w-0">
                <h2 id="student-assessment-worklist-heading" class="text-base font-semibold text-slate-900">{{ __('Your work') }}</h2>
                <p class="mt-1 text-xs text-slate-500 sm:text-sm">{{ __('Each row is one assessment. Your main action is on the right.') }}</p>
            </div>
            <a href="{{ route('student.assignments.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-800 hover:bg-slate-100">
                {{ __('All assignments') }}
            </a>
        </div>

        <div class="mt-4 space-y-4">
            @foreach ($sections as $key => $meta)
                @php $rows = $deck[$key] ?? []; $count = count($rows); @endphp
                <div class="rounded-lg border border-slate-100 bg-slate-50/50">
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 bg-white px-3 py-2.5 sm:px-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ $meta['title'] }}</h3>
                        @if ($count > 0)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium tabular-nums text-slate-600">{{ $count }}</span>
                        @endif
                    </div>
                    @if ($rows === [])
                        <p class="px-3 py-4 text-sm text-slate-500 sm:px-4">{{ $meta['empty'] }}</p>
                    @else
                        <ul class="divide-y divide-slate-100 bg-white">
                            @foreach ($rows as $row)
                                <li class="px-3 py-3 sm:px-4">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-slate-900">{{ $row['title'] }}</p>
                                            @if (! empty($row['course_line']))
                                                <p class="mt-0.5 truncate text-xs text-slate-600">{{ $row['course_line'] }}</p>
                                            @endif
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <span class="rounded-md bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-700 ring-1 ring-slate-200/80">{{ $row['type_label'] }}</span>
                                                @if (! empty($row['submission_format']))
                                                    <span class="rounded-md bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-700 ring-1 ring-slate-200/80">{{ $row['submission_format'] }}</span>
                                                @endif
                                                @if (! empty($row['paste_notice']))
                                                    <span class="rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-900 ring-1 ring-amber-200/80">{{ $row['paste_notice'] }}</span>
                                                @endif
                                                @if (! empty($row['due_line']))
                                                    <span class="rounded-md bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-700 ring-1 ring-slate-200/80">{{ $row['due_line'] }}</span>
                                                @endif
                                                @if (! empty($row['time_limit_line']))
                                                    <span class="rounded-md bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-700 ring-1 ring-slate-200/80">{{ $row['time_limit_line'] }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-2 text-xs font-semibold text-slate-800">{{ $row['status_label'] }}</p>
                                            @if (! empty($row['score_line']))
                                                <p class="mt-0.5 text-xs text-slate-600">{{ $row['score_line'] }}</p>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 flex-wrap items-stretch gap-2 sm:justify-end">
                                            @if (! empty($row['secondary_href']))
                                                <a
                                                    href="{{ $row['secondary_href'] }}"
                                                    class="inline-flex min-h-[44px] min-w-[44px] flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50 sm:flex-initial"
                                                >
                                                    {{ $row['secondary_label'] ?? __('More') }}
                                                </a>
                                            @endif
                                            @if (! empty($row['action_href']))
                                                <a
                                                    href="{{ $row['action_href'] }}"
                                                    class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800 sm:flex-initial sm:min-w-[7rem]"
                                                >
                                                    {{ $row['action_label'] }}
                                                </a>
                                            @else
                                                <span class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-500 sm:flex-initial">{{ $row['action_label'] }}</span>
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
