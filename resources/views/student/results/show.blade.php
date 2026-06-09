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

    <div class="w-full min-w-0 space-y-5 pb-6 text-slate-950 md:space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a
                href="{{ route('student.results.index') }}"
                class="qs-result-action qs-result-action--ghost"
            >
                <i class="fa-solid fa-arrow-left text-xs text-slate-500" aria-hidden="true"></i>
                {{ __('All results') }}
            </a>
            @if ($resultStatus === 'graded' && $result && ! ($assignmentGradesPending ?? false))
                <a
                    href="{{ route('student.results.pdf', $session) }}"
                    class="qs-result-action qs-result-action--ghost"
                >
                    <i class="fa-solid fa-file-pdf text-red-600" aria-hidden="true"></i>
                    {{ __('Download PDF') }}
                </a>
            @endif
        </div>

        @if ($resultStatus === 'held')
            <div class="qs-result-state qs-result-state--amber">
                <span class="qs-result-state__icon" aria-hidden="true">
                    <i class="fa-solid fa-eye"></i>
                </span>
                <div class="qs-result-state__body">
                    <p class="qs-result-state__eyebrow">{{ __('Under review') }}</p>
                    <p class="qs-result-state__title">{{ __('Result not released yet') }}</p>
                    <p class="qs-result-state__text">{{ __('Your result is under review. Contact your examiner.') }}</p>
                </div>
            </div>
        @elseif ($resultStatus === 'pending_manual')
            <div class="qs-result-state qs-result-state--sky">
                <span class="qs-result-state__icon" aria-hidden="true">
                    <i class="fa-solid fa-pen-to-square"></i>
                </span>
                <div class="qs-result-state__body">
                    <p class="qs-result-state__eyebrow">{{ __('In review') }}</p>
                    <p class="qs-result-state__title">{{ __('Your examiner is reviewing your work') }}</p>
                    <p class="qs-result-state__text">{{ __('Your score will appear here as soon as your examiner finishes reviewing your answers.') }}</p>
                </div>
            </div>
        @elseif ($assignmentGradesPending ?? false)
            <div class="qs-result-state qs-result-state--violet">
                <span class="qs-result-state__icon" aria-hidden="true">
                    <i class="fa-solid fa-lock"></i>
                </span>
                <div class="qs-result-state__body">
                    <p class="qs-result-state__eyebrow">{{ __('Graded — awaiting release') }}</p>
                    <p class="qs-result-state__title">{{ __('Your examiner has finished marking this assignment') }}</p>
                    <p class="qs-result-state__text">{{ __('Scores and feedback will appear here after your examiner releases grades to the class.') }}</p>
                </div>
            </div>
        @elseif ($resultStatus === 'graded' && $result)
            @php
                $fmtMark = static fn ($v) => is_numeric($v)
                    ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.')
                    : ($v ?? '—');
                $totalMarks = $fmtMark($session->exam?->total_marks);
                $scoreDisplay = $fmtMark($result->score);
                $pct = $percentage;
            @endphp

            <section class="qs-result-hero" aria-label="{{ __('Your score') }}">
                <div class="qs-result-hero__head">
                    <span class="qs-result-hero__eyebrow">{{ __('Released') }}</span>
                    <h2 class="qs-result-hero__title">{{ __('Your score') }}</h2>
                </div>

                <div class="qs-result-hero__body">
                    @if ($pct !== null)
                        <div class="qs-result-dial" aria-hidden="true">
                            <svg viewBox="0 0 36 36">
                                <path
                                    class="qs-result-dial__track"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3"
                                />
                                <path
                                    class="qs-result-dial__progress"
                                    stroke-dasharray="{{ min(100, max(0, $pct)) }}, 100"
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-linecap="round"
                                    stroke-width="3"
                                />
                            </svg>
                            <span class="qs-result-dial__value">{{ $pct }}%</span>
                        </div>
                    @endif

                    <div class="qs-result-hero__score">
                        <p class="qs-result-hero__big">
                            {{ $scoreDisplay }}<span class="qs-result-hero__big-of">/{{ $totalMarks }}</span>
                        </p>
                        <p class="qs-result-hero__note">{{ __('Out of total marks for this quiz.') }}</p>
                    </div>
                </div>
            </section>

            <section class="qs-stat-grid grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4" aria-label="{{ __('Result summary') }}">
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Score'),
                    'value' => $scoreDisplay.' / '.$totalMarks,
                    'icon' => 'fa-bullseye',
                    'tone' => 'results',
                    'minimal' => true,
                ])
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Percentage'),
                    'value' => $pct !== null ? $pct.'%' : '—',
                    'icon' => 'fa-percent',
                    'tone' => 'assessments',
                    'minimal' => true,
                ])
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Status'),
                    'value' => ucfirst(str_replace('_', ' ', $result->status)),
                    'icon' => 'fa-circle-check',
                    'tone' => 'notices',
                    'minimal' => true,
                ])
            </section>

            @if ($examinerFeedback)
                <section class="qs-result-feedback" aria-label="{{ __('Examiner feedback') }}">
                    <h3 class="qs-result-feedback__title">
                        <i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
                        {{ __('Examiner feedback') }}
                    </h3>
                    <p class="qs-result-feedback__text">{{ $examinerFeedback }}</p>
                </section>
            @endif

            @if (count($breakdown) > 0)
                <section class="qs-result-breakdown" aria-label="{{ __('Question breakdown') }}">
                    <div class="qs-result-breakdown__head">
                        <h3 class="qs-result-breakdown__title">{{ __('Question breakdown') }}</h3>
                        <p class="qs-result-breakdown__sub">{{ __('Review each question, your responses, and scores.') }}</p>
                    </div>
                    <div class="qs-result-breakdown__body">
                        @include('student.results.partials.question-breakdown', [
                            'breakdown' => $breakdown,
                            'showCorrectSummaries' => $showCorrectSummaries,
                        ])
                    </div>
                </section>
            @endif
        @else
            <div class="qs-result-empty">
                <i class="fa-solid fa-circle-question text-3xl text-slate-400" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-medium text-slate-800">{{ __('Your result is not available yet.') }}</p>
            </div>
        @endif
    </div>
</x-layouts.student>
