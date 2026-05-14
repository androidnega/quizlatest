@php
    /** @var array<string, array<int, array<string, mixed>>> $studentAssessmentDeck */
    $deck = $studentAssessmentDeck ?? [];
    $sections = [
        'active_now' => ['title' => __('Active now'), 'empty' => __('Nothing is open for you to start right now.')],
        'continue' => ['title' => __('Continue'), 'empty' => __('No assessments in progress.')],
        'assignments_due' => ['title' => __('Assignments due'), 'empty' => __('No assignments need attention.')],
        'upcoming' => ['title' => __('Upcoming'), 'empty' => __('No upcoming start windows.')],
        'submitted_work' => ['title' => __('Submitted work'), 'empty' => __('No submitted work waiting here.')],
        'results_released' => ['title' => __('Results released'), 'empty' => __('No released results in this list yet.')],
        'closed_missed' => ['title' => __('Closed or missed'), 'empty' => __('No closed items to show.')],
    ];
@endphp

<section id="student-work" class="scroll-mt-4 space-y-8" aria-labelledby="student-assessment-worklist-heading">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="student-assessment-worklist-heading" class="text-lg font-semibold text-slate-900">{{ __('Your work') }}</h2>
            <p class="mt-1 max-w-xl text-sm text-slate-600">{{ __('Open assessments, assignments, and results for your class. Actions update as you submit or your instructor releases marks.') }}</p>
        </div>
        <a href="{{ route('student.assignments.index') }}" class="text-sm font-semibold text-sky-700 underline-offset-2 hover:underline">{{ __('All assignments') }}</a>
    </div>

    <div class="space-y-6">
        @foreach ($sections as $key => $meta)
            @php $rows = $deck[$key] ?? []; @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-slate-900">{{ $meta['title'] }}</h3>
                @if ($rows === [])
                    <p class="mt-3 text-sm text-slate-500">{{ $meta['empty'] }}</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($rows as $row)
                            <li class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 sm:px-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $row['title'] }}</p>
                                        @if (! empty($row['course_line']))
                                            <p class="mt-0.5 truncate text-xs text-slate-600">{{ $row['course_line'] }}</p>
                                        @endif
                                        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-slate-700">
                                            <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-slate-200/80">{{ $row['type_label'] }}</span>
                                            @if (! empty($row['submission_format']))
                                                <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-slate-200/80">{{ $row['submission_format'] }}</span>
                                            @endif
                                            @if (! empty($row['paste_notice']))
                                                <span class="rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-900 ring-1 ring-amber-200/80">{{ $row['paste_notice'] }}</span>
                                            @endif
                                            @if (! empty($row['due_line']))
                                                <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-slate-200/80">{{ $row['due_line'] }}</span>
                                            @endif
                                            @if (! empty($row['time_limit_line']))
                                                <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-slate-200/80">{{ $row['time_limit_line'] }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-xs font-semibold text-slate-800">{{ $row['status_label'] }}</p>
                                        @if (! empty($row['score_line']))
                                            <p class="mt-1 text-xs text-slate-600">{{ $row['score_line'] }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                        @if (! empty($row['secondary_href']))
                                            <a
                                                href="{{ $row['secondary_href'] }}"
                                                class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                                            >
                                                {{ $row['secondary_label'] ?? __('More') }}
                                            </a>
                                        @endif
                                        @if (! empty($row['action_href']))
                                            <a
                                                href="{{ $row['action_href'] }}"
                                                class="inline-flex min-h-[44px] shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                                            >
                                                {{ $row['action_label'] }}
                                            </a>
                                        @else
                                            <span class="inline-flex min-h-[44px] items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-500">{{ $row['action_label'] }}</span>
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
</section>
