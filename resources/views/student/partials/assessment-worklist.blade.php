@php
    /** @var array<string, array<int, array<string, mixed>>> $studentAssessmentDeck */
    $deck = $studentAssessmentDeck ?? [];
    $sections = [
        'active' => ['title' => __('Active assessments'), 'empty' => __('Nothing active right now.')],
        'upcoming' => ['title' => __('Upcoming assessments'), 'empty' => __('No upcoming start windows.')],
        'assignments_due' => ['title' => __('Assignments due'), 'empty' => __('No assignments need attention right now.')],
        'submitted_pending' => ['title' => __('Submitted / awaiting grading'), 'empty' => __('No submissions waiting on marking.')],
        'released' => ['title' => __('Results released'), 'empty' => __('No released results in this list yet.')],
        'closed' => ['title' => __('Missed or closed'), 'empty' => __('No closed items to show.')],
    ];
@endphp

<section class="space-y-6" aria-labelledby="student-assessment-worklist-heading">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="student-assessment-worklist-heading" class="text-lg font-semibold text-slate-900">{{ __('Your assessments') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Cards show status and the next sensible action. Proctoring flags are separate from your score.') }}</p>
        </div>
        <a href="{{ route('student.assignments.index') }}" class="text-sm font-semibold text-[var(--qs-primary)] hover:underline">{{ __('All assignments') }}</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($sections as $key => $meta)
            @php $rows = $deck[$key] ?? []; @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-slate-900">{{ $meta['title'] }}</h3>
                @if ($rows === [])
                    <p class="mt-3 text-sm text-slate-500">{{ $meta['empty'] }}</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($rows as $row)
                            <li class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 sm:px-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $row['title'] }}</p>
                                        @if (! empty($row['course_line']))
                                            <p class="mt-0.5 truncate text-xs text-slate-600">{{ $row['course_line'] }}</p>
                                        @endif
                                        <div class="mt-2 flex flex-wrap gap-x-2 gap-y-1 text-[11px] text-slate-600">
                                            <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-slate-200/80">{{ $row['type_label'] }}</span>
                                            @if (! empty($row['submission_format']))
                                                <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-slate-200/80">{{ $row['submission_format'] }}</span>
                                            @endif
                                            @if (! empty($row['due_line']))
                                                <span class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-slate-200/80">{{ $row['due_line'] }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-xs font-semibold text-slate-800">{{ $row['status_label'] }}</p>
                                    </div>
                                    @if (! empty($row['action_href']))
                                        <a
                                            href="{{ $row['action_href'] }}"
                                            class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                                        >
                                            {{ $row['action_label'] }}
                                        </a>
                                    @else
                                        <span class="inline-flex shrink-0 items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-500">{{ $row['action_label'] }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</section>
