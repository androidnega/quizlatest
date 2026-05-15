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

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <p class="text-sm text-slate-600">{{ __('Each card is one assessment. Your main action is on the right.') }}</p>
            <a
                href="{{ route('student.assignments.index') }}"
                class="inline-flex shrink-0 items-center justify-center self-stretch rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:border-slate-300 hover:bg-slate-50 sm:self-auto"
            >
                {{ __('All assignments') }}
            </a>
        </div>

        <div class="px-4 py-4 sm:px-5 sm:py-5">
            @foreach ($sections as $key => $meta)
                @php $rows = $deck[$key] ?? []; @endphp
                @continue($rows === [])

                <div class="{{ $printedSection ? 'mt-7 border-t border-slate-100 pt-7' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $meta['title'] }}</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium tabular-nums text-slate-600">{{ count($rows) }}</span>
                    </div>

                    <div class="mt-3 grid gap-2.5">
                        @foreach ($rows as $row)
                            <article
                                class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-4 transition-colors hover:border-slate-300 sm:flex-row sm:items-stretch sm:justify-between sm:gap-5 sm:p-4"
                            >
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div>
                                        <p class="text-sm font-medium leading-snug text-slate-900">{{ $row['title'] }}</p>
                                        @if (! empty($row['course_line']))
                                            <p class="mt-0.5 truncate text-xs text-slate-600">{{ $row['course_line'] }}</p>
                                        @endif
                                    </div>
                                    <p class="text-[11px] leading-relaxed text-slate-600">
                                        <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 font-medium text-slate-700">{{ $row['type_label'] }}</span>
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
                                        <span class="font-medium text-slate-800">{{ $row['status_label'] }}</span>
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
                                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-800 transition-colors hover:bg-slate-50"
                                        >
                                            {{ $row['secondary_label'] ?? __('More') }}
                                        </a>
                                    @endif
                                    @if (! empty($row['action_href']))
                                        <a
                                            href="{{ $row['action_href'] }}"
                                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-slate-900 px-4 text-xs font-medium text-white transition-colors hover:bg-slate-800"
                                        >
                                            {{ $row['action_label'] }}
                                        </a>
                                    @else
                                        <span class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 text-center text-xs font-medium text-slate-500">{{ $row['action_label'] }}</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
                @php $printedSection = true; @endphp
            @endforeach

            @if (! $printedSection)
                <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center sm:px-6">
                    <p class="text-sm font-medium text-slate-800">{{ __('Nothing here yet') }}</p>
                    <p class="mx-auto mt-2 max-w-md text-xs leading-relaxed text-slate-600">
                        {{ __('When your class has open windows, due assignments, or released results, they will show as cards in the sections above.') }}
                    </p>
                    <a
                        href="{{ route('student.assignments.index') }}"
                        class="mt-5 inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-xs font-medium text-slate-800 transition-colors hover:border-slate-400 hover:bg-slate-50"
                    >
                        {{ __('Browse assignments') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
