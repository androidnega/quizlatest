<x-layouts.examiner>
    @php
        $poolQuestionTotal = (int) ($poolQuestionTotal ?? 0);
        $poolApprovedCount = (int) ($poolApprovedCount ?? 0);
        $poolComplete = $poolQuestionTotal > 0 && $poolApprovedCount === $poolQuestionTotal;
        $slugTitle = \Illuminate\Support\Str::slug($exam->title);
        $courseTitleUpper = $exam->course ? mb_strtoupper($exam->course->title) : null;
        $questionTypeLabels = [
            'mcq' => __('MCQ'),
            'true_false' => __('True/False'),
            'fill_blank' => __('Fill-in-the-blank'),
            'essay' => __('Essay'),
        ];
        $allowedQuestionTypes = $allowedQuestionTypes ?? \App\Support\AssessmentQuestionTypes::ALL;
        $__aiTypeDefault = array_values(array_intersect(['mcq'], $allowedQuestionTypes));
        if ($__aiTypeDefault === []) {
            $__aiTypeDefault = array_slice($allowedQuestionTypes, 0, 1) ?: ['mcq'];
        }
    @endphp

    <x-slot name="title">{{ $exam->title }}</x-slot>
    <x-slot name="subtitle"></x-slot>

    <div
        x-data="{
            tab: @js($workspaceTab),
            generationLocked: @js($generationLocked),
            poolComplete: @js($poolComplete),
            canEditPool: @js($canEditPool),
            shareUrl: @js($shareUrl),
            displayToken: @js($displayToken),
            copyToast: false,
            async copyText(val) {
                try {
                    await navigator.clipboard.writeText(val);
                    this.copyToast = true;
                    setTimeout(() => (this.copyToast = false), 2000);
                } catch (e) {}
            },
            syncWorkspaceTab(name) {
                this.tab = name;
                try {
                    const u = new URL(window.location.href);
                    if (name === 'overview') {
                        u.searchParams.delete('tab');
                    } else {
                        u.searchParams.set('tab', name);
                    }
                    window.history.replaceState({}, '', u);
                } catch (e) {}
            },
        }"
        class="space-y-6"
    >
    {{-- Quiz identity --}}
    <header class="rounded-xl border border-slate-200/90 bg-white px-5 py-5 shadow-sm sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm text-slate-600">
                    @if ($courseTitleUpper)
                        <span>{{ $courseTitleUpper }}</span>
                    @endif
                    @if ($exam->isAssignment())
                        @php
                            $assignmentTotalMarks = collect($overviewQuestions)->sum('marks');
                        @endphp
                        @if ($assignmentTotalMarks > 0)
                            <span class="text-slate-400"> · </span><span>{{ number_format($assignmentTotalMarks, $assignmentTotalMarks == floor($assignmentTotalMarks) ? 0 : 1) }} {{ __('marks total') }}</span>
                        @endif
                        @if ($exam->due_at)
                            <span class="text-slate-400"> · </span><span>{{ __('Due') }} {{ $exam->due_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</span>
                        @endif
                    @else
                        @if ($exam->questions_per_student !== null)
                            <span class="text-slate-400"> · </span><span>{{ $exam->questions_per_student }} {{ __('per student') }}</span>
                        @endif
                        <span class="text-slate-400"> · </span><span>{{ $exam->duration_minutes }} {{ __('min') }}</span>
                    @endif
                </p>
                @unless ($exam->isAssignment())
                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
                    <span class="font-semibold text-slate-700">{{ __('Pool question types:') }}</span>
                    @foreach ($allowedQuestionTypes as $t)
                        <span class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-800">{{ $questionTypeLabels[$t] ?? $t }}</span>
                    @endforeach
                </div>
                @endunless
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($exam->status === 'published')
                        <span class="inline-flex items-center rounded-md bg-sky-600 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-white">{{ __('Active') }}</span>
                    @else
                        <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-700">{{ ucfirst($exam->status) }}</span>
                    @endif
                    @if ($mobileOnly)
                        <span class="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-800">{{ __('Mobile only') }}</span>
                    @endif
                </div>
            </div>
            <a href="{{ route('examiner.exams.index') }}" class="shrink-0 text-sm font-medium text-slate-500 underline decoration-slate-300 underline-offset-4 hover:text-slate-800">{{ __('← Exams') }}</a>
        </div>

        {{-- Primary tabs --}}
        <nav class="mt-6 flex gap-0 border-b border-slate-200" aria-label="{{ __('Quiz workspace') }}">
            <button
                type="button"
                @click="syncWorkspaceTab('overview')"
                :class="tab === 'overview' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold"
            >
                <i class="fa-regular fa-circle-question text-xs opacity-80" aria-hidden="true"></i>
                {{ __('Overview') }}
            </button>
            @if ($exam->isAssignment())
                <button
                    type="button"
                    @click="syncWorkspaceTab('settings')"
                    :class="tab === 'settings' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold"
                >
                    <i class="fa-solid fa-gear text-xs opacity-80" aria-hidden="true"></i>
                    {{ __('Settings') }}
                </button>
            @endif
            @if ($sessionsWorkspace)
                <button
                    type="button"
                    @click="syncWorkspaceTab('sessions')"
                    :class="tab === 'sessions' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold"
                >
                    <i class="fa-solid fa-users text-xs opacity-80" aria-hidden="true"></i>
                    {{ __('Sessions') }}
                    @if ($sessionsCount > 0)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold tabular-nums text-slate-800">{{ $sessionsCount }}</span>
                    @endif
                </button>
            @endif
            <button
                type="button"
                @click="syncWorkspaceTab('scores')"
                :class="tab === 'scores' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold"
            >
                <i class="fa-regular fa-file-lines text-xs opacity-80" aria-hidden="true"></i>
                {{ __('Scores & export') }}
            </button>
            <button
                type="button"
                @click="syncWorkspaceTab('analytics')"
                :class="tab === 'analytics' ? 'border-sky-600 text-sky-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                class="-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold"
            >
                <i class="fa-solid fa-chart-column text-xs opacity-80" aria-hidden="true"></i>
                {{ __('Question analytics') }}
            </button>
        </nav>
    </header>

    <template x-if="tab === 'overview'">
    <div class="space-y-6">
    @unless ($exam->isAssignment())
    @if ($generationLocked)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
            Question generation is locked for this assessment because questions have already been saved. Create a new quiz to generate again.
        </div>
    @endif
    @if ($poolComplete)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-900">
            All questions are approved. You can still review the overview above; new generation stays locked for this assessment.
        </div>
    @endif

    {{-- Availability & share --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex sm:flex-wrap sm:items-center sm:justify-between sm:gap-4">
        <div class="flex min-w-0 flex-wrap items-center gap-2 sm:gap-3">
            <span class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-xs font-medium tracking-wide text-slate-800">{{ $displayToken }}</span>
            <button type="button" @click="copyText(displayToken)" class="rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700">{{ __('Copy') }}</button>
            <button type="button" @click="copyText(shareUrl)" class="text-sm font-semibold text-sky-700 underline decoration-sky-300 underline-offset-4 hover:text-sky-900">{{ __('Share link') }}</button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2 sm:mt-0">
            @if ($exam->status === 'published')
                <form method="post" action="{{ route('examiner.exams.unpublish', $exam) }}" class="inline" onsubmit="return confirm(@js(__('Stop new attempts? Students will no longer be able to start this quiz.')));">
                    @csrf
                    <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">{{ __('End availability') }}</button>
                </form>
            @elseif ($canEditSchedule)
                <a
                    href="{{ route('examiner.exams.review', $exam) }}"
                    x-show="!poolComplete"
                    x-cloak
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100"
                >{{ __('Review question pool') }}</a>
                <form
                    x-show="poolComplete"
                    x-cloak
                    method="post"
                    action="{{ route('examiner.exams.publish', $exam) }}"
                    class="inline"
                    onsubmit="return confirm(@js(__('Publish this quiz for students? It becomes visible to eligible students on their dashboard and they can start it within the availability window you set.')));"
                >
                    @csrf
                    <button
                        type="submit"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
                        title="{{ __('Publishes the quiz so eligible students can see and start it (within your schedule).') }}"
                    >{{ __('Publish for students') }}</button>
                </form>
            @endif
        </div>
        <p x-show="copyToast" x-cloak class="mt-2 w-full text-xs font-medium text-emerald-700 sm:mt-0">{{ __('Copied to clipboard') }}</p>
    </section>
    @endunless

    @if ($exam->isAssignment())
        @include('examiner.exams.partials.assignment-coursework-panel', [
            'exam' => $exam,
            'canEditSchedule' => $canEditSchedule,
            'submissionStats' => $assignmentWorkspaceStats ?? null,
            'aiEnabled' => $aiEnabled ?? false,
            'variant' => 'summary',
        ])
    @endif

    @unless ($exam->isAssignment())
    {{-- Pool review entry-point. The full overview + approval UI lives on the
         dedicated review page so this workspace stays focused on delivery. --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="pool-summary-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 id="pool-summary-heading" class="text-sm font-semibold text-slate-900">{{ __('Question pool') }}</h2>
                <p class="mt-1 text-xs text-slate-600">
                    <strong class="font-semibold text-slate-900">{{ $poolQuestionTotal }}</strong> {{ __('total') }}
                    <span class="text-slate-400"> · </span>
                    <span class="text-emerald-700">{{ $poolApprovedCount }} {{ __('approved') }}</span>
                    @php $__draftCount = max(0, $poolQuestionTotal - $poolApprovedCount); @endphp
                    @if ($__draftCount > 0)
                        <span class="text-slate-400"> · </span>
                        <span class="text-amber-700">{{ $__draftCount }} {{ __('pending approval') }}</span>
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-500">{{ __('Review individual questions, approve drafts, or add more on the review page.') }}</p>
            </div>
            <a
                href="{{ route('examiner.exams.review', $exam) }}"
                class="inline-flex shrink-0 items-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
            >
                @if ($poolQuestionTotal === 0)
                    {{ __('Add questions') }} →
                @elseif ($poolApprovedCount < $poolQuestionTotal)
                    {{ __('Review & approve pool') }} →
                @else
                    {{ __('Open question pool') }} →
                @endif
            </a>
        </div>
    </section>
    @endunless

    </div>
    </template>

    @if ($exam->isAssignment())
        <template x-if="tab === 'settings'">
            <div class="space-y-6">
                @include('examiner.exams.partials.assignment-coursework-panel', [
                    'exam' => $exam,
                    'canEditSchedule' => $canEditSchedule,
                    'submissionStats' => $assignmentWorkspaceStats ?? null,
                    'aiEnabled' => $aiEnabled ?? false,
                    'variant' => 'settings',
                ])
            </div>
        </template>
    @endif

    @if ($sessionsWorkspace)
        <template x-if="tab === 'sessions'">
            <div class="min-w-0">
                @include('examiner.exam_sessions.partials.workspace-sessions-panel', $sessionsWorkspace)
            </div>
        </template>
    @endif

    <template x-if="tab === 'scores'">
    <div class="space-y-5">
        @if (! $scoresPayload)
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-slate-700">{{ __('You do not have permission to view scoring for this assessment.') }}</p>
            </div>
        @else
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Export results') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ __('Preview the official score report or download it as PDF / CSV. Held results show as "On hold – see lecturer".') }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a
                        href="{{ $scoresPayload['preview_pdf_url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white shadow-sm hover:bg-slate-800"
                    >
                        <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                        {{ __('Preview PDF') }}
                    </a>
                    <a
                        href="{{ $scoresPayload['download_pdf_url'] }}"
                        class="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        {{ __('Download PDF') }}
                    </a>
                    <a
                        href="{{ $scoresPayload['export_csv_url'] }}"
                        class="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                        {{ __('Download CSV') }}
                    </a>
                    <a
                        href="{{ $scoresPayload['class_summary_url'] }}"
                        class="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                    >
                        <i class="fa-solid fa-school" aria-hidden="true"></i>
                        {{ __('By class summary') }}
                    </a>
                </div>
            </div>
        @endif
    </div>
    </template>

    <template x-if="tab === 'analytics'">
    <div class="space-y-5">
        @php
            $rowsForChart = collect($questionPerfRows ?? [])->values();
            $maxRowAttempts = max(1, (int) ($rowsForChart->max('answered') ?? 0));
            $donutCorrect = (int) ($analyticsHeader['correct'] ?? 0);
            $donutWrong = (int) ($analyticsHeader['wrong'] ?? 0);
            $donutTotal = $donutCorrect + $donutWrong;
            $donutCorrectPct = $donutTotal > 0 ? ($donutCorrect / $donutTotal) * 100 : 0;
            $fmtNum = function ($v) {
                if ($v === null) {
                    return '—';
                }
                $s = number_format((float) $v, 2, '.', '');
                if (str_contains($s, '.')) {
                    $s = rtrim(rtrim($s, '0'), '.');
                }

                return $s === '' ? '0' : $s;
            };
        @endphp

        {{-- Question type counts (small rollup at the top) --}}
        @if (! empty($questionAnalytics))
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Questions by type') }}</h2>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($questionAnalytics as $type => $count)
                        <div class="rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', (string) $type) }}</p>
                            <p class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900">{{ $count }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Header stat tiles --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Sessions') }}</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($analyticsHeader['sessions'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Total attempts') }}</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($analyticsHeader['attempts'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Total correct') }}</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums text-emerald-700">{{ number_format($analyticsHeader['correct'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Total wrong') }}</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums text-rose-700">{{ number_format($analyticsHeader['wrong'] ?? 0) }}</p>
            </div>
        </div>

        {{-- Per-question table --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Per-question performance') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Counts come from submitted sessions for this assessment.') }}</p>
                </div>
            </div>
            @if ($rowsForChart->isEmpty())
                <div class="px-4 py-12 text-center">
                    <p class="text-sm font-medium text-slate-700">{{ __('No question performance yet.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Once students start submitting attempts, you will see per-question stats and charts here.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-end">{{ __('#') }}</th>
                                <th class="px-3 py-2">{{ __('Question') }}</th>
                                <th class="px-3 py-2">{{ __('Type') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Answered') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Correct') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Wrong') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Skip') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('% Rate') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Marks') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-800">
                            @foreach ($rowsForChart as $i => $qr)
                                @php
                                    $ans = (int) ($qr['answered'] ?? 0);
                                    $cor = (int) ($qr['correct'] ?? 0);
                                    $rate = $ans > 0 ? round(($cor / $ans) * 100) : null;
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 text-end tabular-nums text-slate-500">{{ $i + 1 }}</td>
                                    <td class="max-w-[22rem] px-3 py-2">
                                        <p class="line-clamp-2 text-slate-900">{{ $qr['preview'] ?? '' }}</p>
                                        @if (! empty($qr['topic']))
                                            <p class="mt-0.5 text-[11px] text-slate-500">{{ $qr['topic'] }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $qr['type'] ?? '' }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ $ans }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums text-emerald-700">{{ $cor }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums text-rose-700">{{ (int) ($qr['wrong'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums text-slate-500">{{ (int) ($qr['unanswered'] ?? 0) }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums font-semibold {{ $rate === null ? 'text-slate-400' : ($rate >= 60 ? 'text-emerald-700' : ($rate >= 40 ? 'text-amber-700' : 'text-rose-700')) }}">
                                        {{ $rate === null ? '—' : $rate.'%' }}
                                    </td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ $fmtNum($qr['marks'] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Charts --}}
        @if ($rowsForChart->isNotEmpty())
            <div class="grid gap-4 lg:grid-cols-3">
                {{-- Bar chart: correct (green) vs wrong (red) per question --}}
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Score for each question') }}</h2>
                        <div class="flex items-center gap-3 text-[11px] text-slate-600">
                            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-sm bg-emerald-500"></span>{{ __('Correct') }}</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block size-2.5 rounded-sm bg-rose-500"></span>{{ __('Wrong') }}</span>
                        </div>
                    </div>
                    @php
                        $barCount = $rowsForChart->count();
                        $chartHeight = 160;
                        $barWidth = 14;
                        $barGap = 6;
                        $chartWidth = $barCount * ($barWidth + $barGap);
                    @endphp
                    <div class="mt-3 overflow-x-auto">
                        <svg
                            class="block h-44"
                            viewBox="0 0 {{ $chartWidth }} {{ $chartHeight + 22 }}"
                            preserveAspectRatio="xMinYMid meet"
                            role="img"
                            aria-label="{{ __('Per-question correct vs wrong bar chart') }}"
                            style="width: {{ max(640, $chartWidth) }}px;"
                        >
                            @foreach ($rowsForChart as $i => $qr)
                                @php
                                    $cor = (int) ($qr['correct'] ?? 0);
                                    $wrn = (int) ($qr['wrong'] ?? 0);
                                    $corH = $maxRowAttempts > 0 ? ($cor / $maxRowAttempts) * $chartHeight : 0;
                                    $wrnH = $maxRowAttempts > 0 ? ($wrn / $maxRowAttempts) * $chartHeight : 0;
                                    $xLeft = $i * ($barWidth + $barGap);
                                @endphp
                                <g>
                                    <rect x="{{ $xLeft }}" y="{{ $chartHeight - $corH }}" width="{{ $barWidth / 2 - 1 }}" height="{{ max(1, $corH) }}" fill="#10b981" rx="1">
                                        <title>{{ __('Q') }}{{ $i + 1 }} · {{ __('Correct') }}: {{ $cor }}</title>
                                    </rect>
                                    <rect x="{{ $xLeft + $barWidth / 2 + 1 }}" y="{{ $chartHeight - $wrnH }}" width="{{ $barWidth / 2 - 1 }}" height="{{ max(1, $wrnH) }}" fill="#f43f5e" rx="1">
                                        <title>{{ __('Q') }}{{ $i + 1 }} · {{ __('Wrong') }}: {{ $wrn }}</title>
                                    </rect>
                                    @if ($barCount <= 60 || $i % max(1, intdiv($barCount, 30)) === 0)
                                        <text x="{{ $xLeft + $barWidth / 2 }}" y="{{ $chartHeight + 14 }}" font-size="8" text-anchor="middle" fill="#64748b">{{ $i + 1 }}</text>
                                    @endif
                                </g>
                            @endforeach
                            <line x1="0" y1="{{ $chartHeight }}" x2="{{ $chartWidth }}" y2="{{ $chartHeight }}" stroke="#e2e8f0" stroke-width="0.5" />
                        </svg>
                    </div>
                </div>

                {{-- Donut: correct vs wrong overall --}}
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Overall correct vs wrong') }}</h2>
                    @php
                        $r = 36; $c = 2 * M_PI * $r;
                        $corDash = $c * ($donutCorrectPct / 100);
                        $wrnDash = $c - $corDash;
                    @endphp
                    <div class="mt-3 flex items-center gap-4">
                        <svg viewBox="0 0 90 90" class="size-24 -rotate-90" role="img" aria-label="{{ __('Correct vs wrong donut') }}">
                            <circle cx="45" cy="45" r="{{ $r }}" stroke="#e2e8f0" stroke-width="14" fill="none" />
                            @if ($donutTotal > 0)
                                <circle cx="45" cy="45" r="{{ $r }}" stroke="#10b981" stroke-width="14" fill="none"
                                    stroke-dasharray="{{ $corDash }} {{ $wrnDash }}" />
                                <circle cx="45" cy="45" r="{{ $r }}" stroke="#f43f5e" stroke-width="14" fill="none"
                                    stroke-dasharray="{{ $wrnDash }} {{ $corDash }}"
                                    stroke-dashoffset="-{{ $corDash }}" />
                            @endif
                        </svg>
                        <div class="space-y-1 text-xs">
                            <p class="flex items-center gap-2"><span class="inline-block size-2.5 rounded-sm bg-emerald-500"></span>{{ __('Correct') }}: <strong class="tabular-nums">{{ number_format($donutCorrect) }}</strong></p>
                            <p class="flex items-center gap-2"><span class="inline-block size-2.5 rounded-sm bg-rose-500"></span>{{ __('Wrong') }}: <strong class="tabular-nums">{{ number_format($donutWrong) }}</strong></p>
                            <p class="text-slate-500">{{ __('Total graded answers') }}: <strong class="tabular-nums text-slate-700">{{ number_format($donutTotal) }}</strong></p>
                            <p class="text-slate-500">{{ __('Success rate') }}: <strong class="tabular-nums {{ $donutCorrectPct >= 60 ? 'text-emerald-700' : ($donutCorrectPct >= 40 ? 'text-amber-700' : 'text-rose-700') }}">{{ $donutTotal > 0 ? round($donutCorrectPct).'%' : '—' }}</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
    </template>

    </div>

    @include('examiner.exams.partials.topic-tags-script')
</x-layouts.examiner>
