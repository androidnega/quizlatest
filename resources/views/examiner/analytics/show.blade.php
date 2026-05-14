<x-layouts.examiner>
    <x-slot name="title">{{ __('Assessment analytics') }}</x-slot>
    <x-slot name="subtitle">{{ $exam->title }}</x-slot>

    @php
        $tabUrls = fn (string $t) => route('examiner.exams.analytics.show', ['exam' => $exam, 'tab' => $t] + ($t === 'students' && $filter ? ['filter' => $filter] : []));
        $fmtDt = fn ($v) => $v ? $v->timezone(config('app.timezone'))->format('M j, Y H:i') : '—';
        $fmtDur = fn ($s) => $s === null ? '—' : sprintf('%d:%02d', intdiv((int) $s, 3600), intdiv((int) $s % 3600, 60));
        $riskLabel = fn ($r) => match ($r) {
            'critical' => __('Critical'),
            'suspicious' => __('Suspicious'),
            'locked' => __('Locked'),
            'warning' => __('Warning'),
            'normal', null, '' => __('Normal'),
            default => (string) $r,
        };
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <a href="{{ route('examiner.quizzes.workspace', $exam) }}" class="font-medium text-sky-700 underline-offset-2 hover:underline">← {{ __('Back to workspace') }}</a>
        <span class="text-slate-300">·</span>
        <a href="{{ route('examiner.exams.sessions.index', $exam) }}" class="font-medium text-sky-700 underline-offset-2 hover:underline">{{ __('Sessions') }}</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 border-b border-slate-200 pb-3">
        @foreach (['overview' => __('Overview'), 'questions' => __('Questions'), 'sections' => __('Sections'), 'students' => __('Students'), 'proctoring' => __('Proctoring')] as $tk => $tl)
            <a
                href="{{ $tabUrls($tk) }}"
                @class([
                    'rounded-full border px-3 py-1 text-xs font-semibold',
                    'border-sky-600 bg-sky-50 text-sky-900' => $tab === $tk,
                    'border-slate-200 bg-white text-slate-700 hover:border-slate-300' => $tab !== $tk,
                ])
            >{{ $tl }}</a>
        @endforeach
    </div>

    @if ($tab === 'overview')
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Cohort') }}</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ([
                        __('Assigned students') => $cohort['assigned_students'] ?? 0,
                        __('Started') => $cohort['started_students'] ?? 0,
                        __('Not started') => $cohort['not_started'] ?? 0,
                        __('In progress') => $cohort['in_progress'] ?? 0,
                        __('Submitted') => $cohort['submitted'] ?? 0,
                        __('Awaiting grading') => $cohort['awaiting_grading'] ?? 0,
                        __('Graded') => $cohort['graded'] ?? 0,
                        __('Published results') => $cohort['published_result'] ?? 0,
                        __('Held') => $cohort['held'] ?? 0,
                        __('Auto-submitted') => $cohort['auto_submitted'] ?? 0,
                        __('Flagged sessions') => $cohort['flagged_sessions'] ?? 0,
                        __('Avg score') => $cohort['avg_score'] ?? '—',
                        __('Lowest') => $cohort['min_score'] ?? '—',
                        __('Highest') => $cohort['max_score'] ?? '—',
                        __('Pass rate %') => isset($cohort['pass_rate_percent']) ? $cohort['pass_rate_percent'].'%' : '—',
                        __('Avg completion') => isset($cohort['avg_completion_seconds']) ? $fmtDur($cohort['avg_completion_seconds']) : '—',
                        __('Late submissions') => $cohort['late_submissions'] ?? 0,
                    ] as $label => $val)
                        <div class="rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                            <dt class="text-[11px] font-medium text-slate-500">{{ $label }}</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ $val }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @if ($assignmentExtras)
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Assignment submission signals') }}</h2>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        @foreach ([
                            __('Text submissions') => $assignmentExtras['text_submissions'] ?? 0,
                            __('File submissions') => $assignmentExtras['file_submissions'] ?? 0,
                            __('Optional attachment used') => $assignmentExtras['optional_attachment_used'] ?? 0,
                            __('Required attachment missing') => $assignmentExtras['required_attachment_missing'] ?? 0,
                            __('Paste attempts') => $assignmentExtras['paste_attempts'] ?? 0,
                            __('Awaiting grade release') => $assignmentExtras['awaiting_feedback_release'] ?? 0,
                        ] as $label => $val)
                            <div class="rounded-lg border border-slate-100 px-3 py-2">
                                <dt class="text-[11px] font-medium text-slate-500">{{ $label }}</dt>
                                <dd class="mt-0.5 font-semibold tabular-nums text-slate-900">{{ $val }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            <p class="text-xs leading-relaxed text-slate-600">
                {{ __('Proctoring violations do not automatically deduct marks. They are used for warnings, flags, auto-submit, holds, and examiner review.') }}
            </p>

            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <a href="{{ route('examiner.exams.analytics.export.students', $exam) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">{{ __('Export students CSV') }}</a>
                <a href="{{ route('examiner.exams.analytics.export.questions', $exam) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">{{ __('Export questions CSV') }}</a>
                <a href="{{ route('examiner.exams.analytics.export.proctoring', $exam) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">{{ __('Export proctoring CSV') }}</a>
                @if ($exam->isAssignment())
                    <a href="{{ route('examiner.exams.analytics.export.assignment-submissions', $exam) }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">{{ __('Export assignment submissions CSV') }}</a>
                @endif
            </div>
        </div>
    @endif

    @if ($tab === 'questions')
        <div class="space-y-3">
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2">{{ __('Preview') }}</th>
                            <th class="px-3 py-2">{{ __('Type') }}</th>
                            <th class="px-3 py-2">{{ __('Section') }}</th>
                            <th class="px-3 py-2">{{ __('Topic') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Marks') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Ans') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('✓') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('✗') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Skip') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Avg') }}</th>
                            <th class="px-3 py-2">{{ __('Difficulty') }}</th>
                            <th class="px-3 py-2">{{ __('MCQ correct') }}</th>
                            <th class="px-3 py-2">{{ __('Top wrong') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @forelse ($questionRows as $qr)
                            <tr>
                                <td class="max-w-[14rem] px-3 py-2">{{ $qr['preview'] ?? '' }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $qr['type'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $qr['section'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $qr['topic'] ?? '' }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $qr['marks'] ?? '' }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $qr['answered'] ?? 0 }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $qr['correct'] ?? 0 }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $qr['wrong'] ?? 0 }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $qr['unanswered'] ?? 0 }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $qr['avg_score'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $qr['difficulty'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $qr['mcq_correct_label'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ $qr['mcq_most_wrong_label'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="px-3 py-6 text-center text-slate-500">{{ __('No questions yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($tab === 'sections')
        <div class="space-y-4">
            @forelse ($sectionRows as $sr)
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ $sr['title'] ?? __('Section') }}</h3>
                    <dl class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        <div><dt class="text-slate-500">{{ __('Questions') }}</dt><dd class="font-semibold">{{ $sr['question_count'] ?? 0 }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('Total marks') }}</dt><dd class="font-semibold">{{ $sr['total_marks'] ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('Avg score') }}</dt><dd class="font-semibold">{{ $sr['avg_score'] ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('Low / high') }}</dt><dd class="font-semibold">{{ ($sr['min_score'] ?? '—').' / '.($sr['max_score'] ?? '—') }}</dd></div>
                    </dl>
                    <p class="mt-2 text-xs text-slate-600"><span class="font-semibold">{{ __('Weakest') }}:</span> {{ $sr['weakest_question'] ?? '—' }}</p>
                    <p class="text-xs text-slate-600"><span class="font-semibold">{{ __('Strongest') }}:</span> {{ $sr['strongest_question'] ?? '—' }}</p>
                    @if (! empty($sr['topic_performance']))
                        <div class="mt-3 border-t border-slate-100 pt-3">
                            <p class="text-[11px] font-semibold uppercase text-slate-500">{{ __('Topics in this section') }}</p>
                            <ul class="mt-2 space-y-1 text-xs">
                                @foreach ($sr['topic_performance'] as $tp)
                                    <li class="flex flex-wrap justify-between gap-2">
                                        <span>{{ $tp['topic'] ?? '' }}</span>
                                        <span class="tabular-nums text-slate-600">{{ __('Avg') }} {{ $tp['avg_score'] ?? '—' }} · {{ __('Q') }} {{ $tp['question_count'] ?? 0 }}@if (! empty($tp['weak'])) <span class="text-rose-700">{{ __('· weak') }}</span>@endif</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('No section aggregates yet.') }}</p>
            @endforelse

            @if ($topicRows !== [])
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('All topics') }}</h3>
                    <ul class="mt-2 divide-y divide-slate-100 text-xs">
                        @foreach ($topicRows as $tp)
                            <li class="flex justify-between py-1.5">
                                <span>{{ $tp['topic'] ?? '' }}</span>
                                <span class="text-slate-600">{{ __('Avg') }} {{ $tp['avg_score'] ?? '—' }}@if (! empty($tp['weak'])) <span class="ms-2 text-rose-700">{{ __('weak') }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if ($tab === 'students')
        @php $activeFilter = $filter ?? 'all'; @endphp
        <form method="get" action="{{ route('examiner.exams.analytics.show', $exam) }}" class="mb-3 flex flex-wrap items-end gap-2">
            <input type="hidden" name="tab" value="students" />
            <div>
                <label for="analytics-filter" class="block text-[11px] font-medium text-slate-500">{{ __('Filter') }}</label>
                <select id="analytics-filter" name="filter" class="mt-0.5 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm" onchange="this.form.submit()">
                    @foreach ([
                        'all' => __('All'),
                        'submitted' => __('Submitted'),
                        'not_submitted' => __('Not submitted'),
                        'pending_grading' => __('Pending grading'),
                        'graded' => __('Graded'),
                        'published' => __('Published'),
                        'held' => __('Held'),
                        'flagged' => __('Flagged'),
                        'auto_submitted' => __('Auto-submitted'),
                        'assignment_only' => __('Assignment only'),
                        'quiz_exam_only' => __('Quiz / exam only'),
                    ] as $fv => $fl)
                        <option value="{{ $fv }}" @selected($activeFilter === $fv)>{{ $fl }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">{{ __('Student') }}</th>
                        <th class="px-3 py-2">{{ __('Index') }}</th>
                        <th class="px-3 py-2">{{ __('Class') }}</th>
                        <th class="px-3 py-2">{{ __('Status') }}</th>
                        <th class="px-3 py-2">{{ __('Started') }}</th>
                        <th class="px-3 py-2">{{ __('Submitted') }}</th>
                        <th class="px-3 py-2">{{ __('Duration') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('Score') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('%') }}</th>
                        <th class="px-3 py-2">{{ __('Result') }}</th>
                        <th class="px-3 py-2">{{ __('Risk') }}</th>
                        <th class="px-3 py-2">{{ __('Auto-submit') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($studentRows as $row)
                        <tr class="text-slate-800">
                            <td class="px-3 py-2 font-medium">{{ $row['name'] ?? '' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $row['index_number'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['class'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ str_replace('_', ' ', (string) ($row['session_status'] ?? '')) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $fmtDt($row['started_at'] ?? null) }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $fmtDt($row['submitted_at'] ?? null) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ $fmtDur($row['duration_seconds'] ?? null) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $row['score'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $row['percentage'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['result_status'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $riskLabel($row['risk_state'] ?? null) }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $row['auto_submit_reason'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-end whitespace-nowrap">
                                @can('manageResults', $exam)
                                    @if (! empty($row['exam_session_id']))
                                        <a href="{{ route('examiner.exam-sessions.show', $row['exam_session_id']) }}" class="text-sky-700 hover:underline">{{ __('View') }}</a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                    @if (($row['result_status'] ?? '') === 'pending_manual')
                                        <span class="text-slate-300">·</span>
                                        <a href="{{ route('examiner.grading.pending') }}" class="text-sky-700 hover:underline">{{ __('Grade') }}</a>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="px-3 py-6 text-center text-slate-500">{{ __('No students match this filter.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'proctoring')
        <div class="space-y-4">
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                {{ __('Proctoring violations do not automatically deduct marks. They are used for warnings, flags, auto-submit, holds, and examiner review.') }}
            </p>
            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:grid-cols-4">
                @foreach ([
                    __('Total events') => $proctoring['event_total'] ?? 0,
                    __('Flagged sessions') => $proctoring['flagged_sessions'] ?? 0,
                    __('Auto-submitted') => $proctoring['auto_submitted_sessions'] ?? 0,
                    __('Held results') => $proctoring['held_results'] ?? 0,
                    __('Tab switch limit') => $proctoring['tab_switch_limit'] ?? 0,
                    __('Phone detected') => $proctoring['phone_detected'] ?? 0,
                    __('Face events') => $proctoring['face_events'] ?? 0,
                    __('Screenshot attempts') => $proctoring['screenshot_attempts'] ?? 0,
                    __('External display risk') => $proctoring['external_display_risk'] ?? 0,
                    __('Camera permission lost') => $proctoring['camera_permission_lost'] ?? 0,
                    __('Avg violation score') => $proctoring['avg_violation_score'] ?? '—',
                ] as $label => $val)
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                        <dt class="text-[11px] font-medium text-slate-500">{{ $label }}</dt>
                        <dd class="mt-0.5 font-semibold tabular-nums">{{ $val }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">{{ __('Time') }}</th>
                            <th class="px-3 py-2">{{ __('Student') }}</th>
                            <th class="px-3 py-2">{{ __('Event') }}</th>
                            <th class="px-3 py-2">{{ __('Risk') }}</th>
                            <th class="px-3 py-2">{{ __('Action') }}</th>
                            <th class="px-3 py-2">{{ __('Summary') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($proctoring['timeline'] ?? [] as $ev)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2">{{ $fmtDt($ev['at'] ?? null) }}</td>
                                <td class="px-3 py-2">{{ $ev['student'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $ev['event_type'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $ev['risk_level'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $ev['action'] ?? '—' }}</td>
                                <td class="max-w-[18rem] px-3 py-2 text-slate-600">{{ $ev['summary'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">{{ __('No proctoring events recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.examiner>
