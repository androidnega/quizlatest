<x-layouts.student>
    <x-slot name="title">{{ ($resultKindLabel ?? __('Result')) }} — {{ $session->exam?->title ?? '' }}</x-slot>
    <x-slot name="subtitle">
        <span class="inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-qs-muted">
            <span class="font-medium text-qs-text">{{ $resultKindLabel ?? __('Result') }}</span>
            <span aria-hidden="true">·</span>
            @if ($session->exam?->course?->code)
                <span class="font-medium text-qs-text">{{ $session->exam->course->code }}</span>
                <span aria-hidden="true">·</span>
            @endif
            @if ($session->end_time)
                <span>{{ __('Submitted') }} {{ $session->end_time->timezone(config('app.timezone'))->format('M j, Y · H:i') }}</span>
            @endif
        </span>
        @php
            $releaseLine = match ($resultStatus ?? '') {
                'held' => __('Status: Under review'),
                'pending_manual' => __('Status: Awaiting grading'),
                'graded' => ($assignmentGradesPending ?? false) ? __('Status: Graded — awaiting release to class') : __('Status: Released'),
                'published' => __('Status: Released'),
                default => __('Status: Submitted'),
            };
        @endphp
        <p class="mt-1 text-xs text-slate-500">{{ $releaseLine }}</p>
    </x-slot>

    <div class="w-full min-w-0 space-y-5 pb-4 text-slate-950 md:space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a
                href="{{ route('student.results.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
            >
                <i class="fa-solid fa-arrow-left text-xs text-slate-500" aria-hidden="true"></i>
                {{ __('All results') }}
            </a>
            @if ($resultStatus === 'graded' && $result && ! ($assignmentGradesPending ?? false))
                <a
                    href="{{ route('student.results.pdf', $session) }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-[var(--qs-primary)]/40 hover:bg-[var(--qs-primary)]/5"
                >
                    <i class="fa-solid fa-file-pdf text-red-600" aria-hidden="true"></i>
                    {{ __('Download PDF') }}
                </a>
            @endif
        </div>

        @if ($resultStatus === 'held')
            <div class="overflow-hidden rounded-[1.5rem] border border-amber-200 bg-amber-50 p-6 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-200/80 text-amber-900">
                        <i class="fa-solid fa-eye text-xl" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-900/80">{{ __('Under review') }}</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">{{ __('Result not released yet') }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-amber-950/90">{{ __('Your result is under review. Contact your examiner.') }}</p>
                    </div>
                </div>
            </div>
        @elseif ($resultStatus === 'pending_manual')
            <div class="overflow-hidden rounded-[1.5rem] border border-sky-200 bg-sky-50 p-6 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-200/80 text-sky-900">
                        <i class="fa-solid fa-pen-to-square text-xl" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-sky-900/80">{{ __('Pending grading') }}</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">{{ __('Waiting for your examiner') }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-sky-950/90">{{ __('Your result is pending manual grading.') }}</p>
                    </div>
                </div>
            </div>
        @elseif ($assignmentGradesPending ?? false)
            <div class="overflow-hidden rounded-[1.5rem] border border-violet-200 bg-violet-50 p-6 sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-violet-200/80 text-violet-900">
                        <i class="fa-solid fa-lock text-xl" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-violet-900/80">{{ __('Graded — awaiting release') }}</p>
                        <p class="mt-2 text-base font-semibold text-slate-900">{{ __('Your examiner has finished marking this assignment') }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-violet-950/90">{{ __('Scores and feedback will appear here after your examiner releases grades to the class.') }}</p>
                    </div>
                </div>
            </div>
        @elseif ($resultStatus === 'graded' && $result)
            <div class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white">
                <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-6 sm:px-8 sm:py-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700/90">{{ __('Released') }}</p>
                            <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ __('Your score') }}</p>
                        </div>
                        @if ($percentage !== null)
                            <div class="flex items-center gap-5">
                                <div class="relative h-24 w-24 shrink-0 sm:h-28 sm:w-28" aria-hidden="true">
                                    <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                                        <path
                                            class="text-slate-100"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        />
                                        <path
                                            class="text-[var(--qs-primary)]"
                                            stroke-dasharray="{{ min(100, max(0, $percentage)) }}, 100"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            stroke-width="3"
                                        />
                                    </svg>
                                    <span class="absolute inset-0 flex items-center justify-center text-lg font-bold tabular-nums text-slate-900 sm:text-xl">{{ $percentage }}%</span>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold tabular-nums text-slate-900 sm:text-4xl">{{ $result->score }}<span class="text-lg font-semibold text-slate-400">/{{ $session->exam?->total_marks ?? '—' }}</span></p>
                                    <p class="mt-1 text-sm text-slate-500">{{ __('Out of total marks for this quiz.') }}</p>
                                </div>
                            </div>
                        @else
                            <p class="text-3xl font-bold tabular-nums text-slate-900 sm:text-4xl">{{ $result->score }}<span class="text-lg font-semibold text-slate-400">/{{ $session->exam?->total_marks ?? '—' }}</span></p>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-6 sm:px-8 sm:py-7">
                    <dl class="grid gap-4 text-sm sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Score') }}</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-900">{{ $result->score }} / {{ $session->exam?->total_marks ?? '—' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Percentage') }}</dt>
                            <dd class="mt-1 font-semibold tabular-nums text-slate-900">{{ $percentage !== null ? $percentage.'%' : '—' }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</dt>
                            <dd class="mt-1 font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $result->status)) }}</dd>
                        </div>
                    </dl>

                    @if ($examinerFeedback)
                        <div class="mt-6 rounded-xl border border-qs-soft bg-qs-bg/80 px-4 py-4">
                            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-600">
                                <i class="fa-solid fa-comment-dots text-[var(--qs-primary)]" aria-hidden="true"></i>
                                {{ __('Examiner feedback') }}
                            </h3>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ $examinerFeedback }}</p>
                        </div>
                    @endif
                </div>
            </div>

            @if (count($breakdown) > 0)
                <div class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 bg-slate-100 px-5 py-4 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-900">{{ __('Question breakdown') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Per-question points and feedback from grading.') }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-white text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">#</th>
                                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Type') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Points') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Max') }}</th>
                                    @if ($showCorrectSummaries)
                                        <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Correct') }}</th>
                                    @endif
                                    <th class="min-w-[8rem] px-4 py-3 sm:px-5">{{ __('Feedback') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($breakdown as $row)
                                    <tr class="transition hover:bg-slate-50/80">
                                        <td class="whitespace-nowrap px-4 py-3 font-medium tabular-nums text-slate-900 sm:px-5">{{ $row['number'] }}</td>
                                        <td class="px-4 py-3 text-slate-700 sm:px-5">{{ str_replace('_', ' ', $row['type']) }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-900 sm:px-5">{{ $row['points'] }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 sm:px-5">{{ $row['max'] }}</td>
                                        @if ($showCorrectSummaries)
                                            <td class="px-4 py-3 text-slate-700 sm:px-5">{{ $row['correct_summary'] ?? '—' }}</td>
                                        @endif
                                        <td class="px-4 py-3 text-slate-600 sm:px-5">{{ $row['feedback'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div class="rounded-[1.5rem] border border-slate-200 bg-slate-100 px-6 py-10 text-center">
                <i class="fa-solid fa-circle-question text-3xl text-slate-400" aria-hidden="true"></i>
                <p class="mt-4 text-sm font-medium text-slate-800">{{ __('Your result is not available yet.') }}</p>
            </div>
        @endif
    </div>
</x-layouts.student>
