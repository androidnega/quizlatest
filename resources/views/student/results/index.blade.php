<x-layouts.student>
    <x-slot name="title">{{ __('Results') }}</x-slot>
    <x-slot name="subtitle">{{ __('Submitted work and released scores.') }}</x-slot>

    @php
        $tz = config('app.timezone');
        $total = $sessions->count();
        $gradedN = $sessions->filter(fn ($s) => $s->result?->status === 'graded'
            && ($s->exam?->assignmentGradesVisibleToStudents() ?? true))->count();
        $awaitingN = $total - $gradedN;
    @endphp

    <div class="qs-std-results space-y-5 pb-6">
        @if ($total > 0)
            <section class="qs-stat-grid grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4" aria-label="{{ __('Results overview') }}">
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Submissions'),
                    'value' => number_format($total),
                    'icon' => 'fa-clipboard-list',
                    'tone' => 'assessments',
                    'minimal' => true,
                ])
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Released'),
                    'value' => number_format($gradedN),
                    'icon' => 'fa-square-poll-vertical',
                    'tone' => 'results',
                    'minimal' => true,
                ])
                @include('student.partials.dashboard-stat-card', [
                    'label' => __('Awaiting'),
                    'value' => number_format($awaitingN),
                    'icon' => 'fa-hourglass-half',
                    'tone' => 'assignments',
                    'minimal' => true,
                ])
            </section>

            <ul class="qs-wl-list qs-wl-list--shimmer">
                @foreach ($sessions as $s)
                    @php
                        $r = $s->result;
                        $status = $r?->status;
                        /* For assignments, the examiner may have finished grading but not
                           yet released marks to the class. In that window we surface the
                           card under the "submitted" bucket and hide the score.            */
                        $gradesReleased = $s->exam?->assignmentGradesVisibleToStudents() ?? true;
                        $effectiveStatus = ($status === 'graded' && ! $gradesReleased) ? 'awaiting_release' : $status;
                        $bucketKey = $effectiveStatus === 'graded' ? 'results_released' : 'submitted_work';
                        $statusPill = match ($effectiveStatus) {
                            'graded' => __('GRADED'),
                            'held' => __('UNDER REVIEW'),
                            'pending_manual' => __('PENDING'),
                            'awaiting_release' => __('SUBMITTED'),
                            default => __('PROCESSING'),
                        };
                        $statusLabel = match ($effectiveStatus) {
                            'held' => __('Under review'),
                            'pending_manual' => __('Awaiting grading'),
                            'graded' => __('Graded'),
                            'awaiting_release' => __('Awaiting release'),
                            default => __('Processing'),
                        };
                        $totalMarks = $s->exam?->total_marks;
                        $score = $r?->score;
                        $fmtMark = static fn ($v) => is_numeric($v)
                            ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.')
                            : null;
                        $scoreDisplay = $fmtMark($score);
                        $totalDisplay = $fmtMark($totalMarks);

                        $courseLine = trim(
                            ($s->exam?->course?->code ? $s->exam->course->code.' · ' : '')
                            .((string) ($s->exam?->course?->title ?? ''))
                        , ' ·');
                        $submittedOn = $s->end_time ? $s->end_time->timezone($tz)->format('M j, Y') : null;

                        $assessmentType = (string) ($s->exam?->assessment_type ?? 'exam');
                        [$typeLabel, $typeIcon, $typeKey] = match ($assessmentType) {
                            'assignment' => [__('Assignment'), 'fa-file-pen', 'assignment'],
                            'quiz' => [__('Quiz'), 'fa-clipboard-question', 'quiz'],
                            'mid' => [__('Mid-semester'), 'fa-flask', 'mid'],
                            'exam' => [__('Exam'), 'fa-shield-halved', 'exam'],
                            default => [__('Assessment'), 'fa-clipboard-list', 'exam'],
                        };
                    @endphp
                    <li class="qs-wl-item qs-wl-item--{{ $bucketKey }}" style="--card-i: {{ $loop->index }}">
                        <span class="qs-type-tag qs-type-tag--{{ $typeKey }}" aria-label="{{ __('Type: :type', ['type' => $typeLabel]) }}">
                            <i class="fa-solid {{ $typeIcon }}" aria-hidden="true"></i>
                            <span class="qs-type-tag__label">{{ $typeLabel }}</span>
                        </span>

                        <div class="qs-wl-item__head">
                            <h3 class="qs-wl-item__title">{{ $s->exam?->title ?? __('Assessment') }}</h3>
                            <span class="qs-wl-item__icon" aria-hidden="true">
                                <i class="fa-solid {{ $effectiveStatus === 'graded' ? 'fa-square-poll-vertical' : 'fa-check-double' }}"></i>
                            </span>
                        </div>

                        @if ($courseLine !== '')
                            <p class="qs-wl-item__sub">{{ $courseLine }}</p>
                        @endif

                        <div class="qs-wl-item__pills">
                            <span class="qs-wl-pill">
                                <span class="qs-wl-pill__dot" aria-hidden="true"></span>
                                {{ $statusPill }}
                            </span>

                            @if ($submittedOn)
                                <span class="qs-wl-pill qs-wl-pill--time">
                                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                    <span>{{ $submittedOn }}</span>
                                </span>
                            @endif
                        </div>

                        @if ($effectiveStatus === 'graded' && $scoreDisplay !== null)
                            <p class="qs-wl-item__status">
                                <span class="qs-wl-item__status-label">{{ $scoreDisplay }}@if ($totalDisplay !== null) / {{ $totalDisplay }}@endif</span>
                            </p>
                        @else
                            <p class="qs-wl-item__status">
                                <span class="qs-wl-item__status-label">{{ $statusLabel }}</span>
                            </p>
                        @endif

                        <a href="{{ route('student.results.show', $s) }}" class="qs-wl-action qs-wl-action--primary">
                            <span>{{ __('View result') }}</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="qs-std-card qs-std-results__empty">
                <p class="qs-std-results__empty-title">{{ __('No results yet') }}</p>
                <p class="qs-std-results__empty-text">{{ __('Submitted assessments will appear here after you finish and submit.') }}</p>
                <a href="{{ route('student.work.index') }}" class="qs-std-btn qs-std-btn--primary mt-5 inline-flex">{{ __('View assessments') }}</a>
            </div>
        @endif
    </div>
</x-layouts.student>
