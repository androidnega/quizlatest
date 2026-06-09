<x-layouts.examiner>
    <x-slot name="title">{{ __('Grade essay') }}</x-slot>
    <x-slot name="subtitle">{{ $answer->examSession?->exam?->title }}</x-slot>

    @php
        $md = is_array($answer->question->metadata ?? null) ? $answer->question->metadata : [];
        $history = ($answer->evaluation_detail['grading_history'] ?? []) ?: [];
        $isOverride = $answer->evaluation_status === 'manual_graded';
        $aiAssist = $answer->evaluation_detail['ai_assist'] ?? null;
        $lastAiHistory = collect($history)->last(fn ($row) => ($row['action'] ?? null) === 'ai_assist');

        $maxMarks = (float) ($answer->question->marks ?? 0);
        $studentName = (string) ($answer->examSession?->student?->name ?? '—');
        $studentInitials = collect(preg_split('/\s+/', trim($studentName)))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
        if ($studentInitials === '') {
            $studentInitials = '?';
        }
        $examTitle = (string) ($answer->examSession?->exam?->title ?? '');
        $courseLabel = (string) ($answer->examSession?->exam?->course?->code ?? $answer->examSession?->exam?->course?->title ?? '');
        $submittedText = (string) ($answer->answer_payload['text'] ?? '');
        $isHtmlAnswer = \App\Support\EssayAnswerHtml::looksLikeHtml($submittedText);
        $plainTextForCount = trim(\App\Support\EssayAnswerHtml::toPlainText($submittedText));
        $wordCount = $plainTextForCount === '' ? 0 : count(preg_split('/\s+/u', $plainTextForCount, -1, PREG_SPLIT_NO_EMPTY));
        $charCount = mb_strlen($plainTextForCount);

        $statusLabel = $isOverride ? __('Graded') : __('Pending review');
        $statusTone = $isOverride
            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200/80'
            : 'bg-amber-50 text-amber-800 ring-amber-200/80';

        // Pre-resolve reference panels so we can hide the section entirely when nothing is provided.
        $hasMarkingGuide = ! empty($md['marking_guide']);
        $hasRubric = ! empty($md['rubric']);
        $hasSampleAnswer = ! empty($md['sample_answer']);
        $hasExplanation = ! empty($md['explanation']);
        $hasAnyReference = $hasMarkingGuide || $hasRubric || $hasSampleAnswer || $hasExplanation;
    @endphp

    {{-- HEADER STRIP: student, exam title, status, back link.
         Compact card so the rest of the screen is dedicated to grading. --}}
    <div class="mb-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div class="flex min-w-0 items-center gap-3">
            <span aria-hidden="true" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-cyan-100 to-emerald-100 text-sm font-bold text-emerald-800 ring-1 ring-inset ring-emerald-200/70">
                {{ $studentInitials }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $studentName }}</p>
                <p class="truncate text-xs text-slate-500">
                    @if ($courseLabel !== '')
                        <span class="font-medium text-slate-700">{{ $courseLabel }}</span>
                        <span class="mx-1 text-slate-300">·</span>
                    @endif
                    {{ $examTitle }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:shrink-0">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $statusTone }}">
                <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full {{ $isOverride ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse' }}"></span>
                {{ $statusLabel }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-inset ring-slate-200/80">
                <i class="fa-solid fa-bullseye text-[10px] text-slate-400" aria-hidden="true"></i>
                {{ __('Max :n pts', ['n' => rtrim(rtrim(number_format($maxMarks, 2, '.', ''), '0'), '.')]) }}
            </span>
            <a href="{{ route('examiner.grading.pending') }}"
               class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left text-[10px]" aria-hidden="true"></i>
                {{ __('Back to queue') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @error('ai')
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }}</div>
    @enderror
    @error('answer')
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }}</div>
    @enderror

    {{-- TWO-COLUMN GRID: question/context (left) + answer/grading (right).
         Stacks on mobile, sticky grade panel on lg+. --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-5 lg:items-start xl:gap-6">
        {{-- ========================== LEFT COLUMN ========================== --}}
        <section class="space-y-5 lg:col-span-3" aria-label="{{ __('Question and grading reference') }}">
            {{-- Question card. Treated as the editorial focal point on the left. --}}
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50/80 via-white to-slate-50/40 px-5 py-3">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                        <i class="fa-solid fa-clipboard-question text-slate-400" aria-hidden="true"></i>
                        {{ __('Question') }}
                    </p>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">
                        <i class="fa-solid fa-bullseye text-[10px]" aria-hidden="true"></i>
                        {{ __(':n pts', ['n' => rtrim(rtrim(number_format($maxMarks, 2, '.', ''), '0'), '.')]) }}
                    </span>
                </header>
                <div class="px-5 py-4 text-[15px] leading-relaxed text-slate-800">
                    <p class="whitespace-pre-wrap">{{ $answer->question->question_text }}</p>
                </div>
                @if (! empty($md['topic']) || ! empty($md['difficulty']) || ! empty($md['learning_outcome']))
                    <dl class="grid gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-3 text-xs sm:grid-cols-2">
                        @if (! empty($md['topic']))
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-cyan-100 text-cyan-700" aria-hidden="true"><i class="fa-solid fa-tag text-[10px]"></i></span>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Topic') }}</dt>
                                    <dd class="truncate text-slate-800">{{ $md['topic'] }}</dd>
                                </div>
                            </div>
                        @endif
                        @if (! empty($md['difficulty']))
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-amber-100 text-amber-800" aria-hidden="true"><i class="fa-solid fa-gauge-high text-[10px]"></i></span>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Difficulty') }}</dt>
                                    <dd class="truncate text-slate-800">{{ $md['difficulty'] }}</dd>
                                </div>
                            </div>
                        @endif
                        @if (! empty($md['learning_outcome']))
                            <div class="flex items-start gap-2 sm:col-span-2">
                                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-violet-100 text-violet-700" aria-hidden="true"><i class="fa-solid fa-graduation-cap text-[10px]"></i></span>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Learning outcome') }}</dt>
                                    <dd class="text-slate-800">{{ $md['learning_outcome'] }}</dd>
                                </div>
                            </div>
                        @endif
                    </dl>
                @endif
            </article>

            {{-- Reference card: marking guide / rubric / sample answer / explanation.
                 Uses a small tabset-like layout for scannability. --}}
            @if ($hasAnyReference)
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-amber-50/60 via-white to-white px-5 py-3">
                        <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-amber-800">
                            <i class="fa-solid fa-book-open text-amber-500" aria-hidden="true"></i>
                            {{ __('Grading reference') }}
                        </p>
                        <span class="text-[11px] text-slate-500">{{ __('What to look for') }}</span>
                    </header>
                    <div class="divide-y divide-slate-100">
                        @if ($hasMarkingGuide)
                            <details class="group" open>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fa-solid fa-list-check text-amber-600 text-[12px]" aria-hidden="true"></i>
                                        {{ __('Marking guide') }}
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition group-open:rotate-180" aria-hidden="true"></i>
                                </summary>
                                <p class="whitespace-pre-wrap px-5 pb-4 text-sm leading-relaxed text-slate-700">{{ $md['marking_guide'] }}</p>
                            </details>
                        @endif
                        @if ($hasRubric)
                            <details class="group">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fa-solid fa-table-list text-violet-600 text-[12px]" aria-hidden="true"></i>
                                        {{ __('Rubric') }}
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition group-open:rotate-180" aria-hidden="true"></i>
                                </summary>
                                <div class="px-5 pb-4">
                                    @if (is_array($md['rubric']))
                                        <pre class="overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-800 ring-1 ring-inset ring-slate-200">{{ json_encode($md['rubric'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @else
                                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ $md['rubric'] }}</p>
                                    @endif
                                </div>
                            </details>
                        @endif
                        @if ($hasSampleAnswer)
                            <details class="group">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fa-solid fa-pen-nib text-emerald-600 text-[12px]" aria-hidden="true"></i>
                                        {{ __('Sample answer') }}
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition group-open:rotate-180" aria-hidden="true"></i>
                                </summary>
                                <p class="whitespace-pre-wrap px-5 pb-4 text-sm leading-relaxed text-slate-700">{{ $md['sample_answer'] }}</p>
                            </details>
                        @endif
                        @if ($hasExplanation)
                            <details class="group">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                                    <span class="inline-flex items-center gap-2">
                                        <i class="fa-solid fa-circle-info text-slate-600 text-[12px]" aria-hidden="true"></i>
                                        {{ __('Explanation') }}
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition group-open:rotate-180" aria-hidden="true"></i>
                                </summary>
                                <p class="whitespace-pre-wrap px-5 pb-4 text-sm leading-relaxed text-slate-700">{{ $md['explanation'] }}</p>
                            </details>
                        @endif
                    </div>
                </article>
            @endif

            {{-- Grading history (kept on the left as historical context). --}}
            @if ($isOverride && count($history) > 0)
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3">
                        <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500">
                            <i class="fa-solid fa-clock-rotate-left text-slate-400" aria-hidden="true"></i>
                            {{ __('Grading history') }}
                        </p>
                        <span class="text-[11px] text-slate-500">{{ trans_choice('{1} :n entry|[2,*] :n entries', count($history), ['n' => count($history)]) }}</span>
                    </header>
                    <ol class="divide-y divide-slate-100 text-sm">
                        @foreach ($history as $row)
                            @php
                                $action = (string) ($row['action'] ?? 'initial');
                                $tone = match ($action) {
                                    'ai_assist' => 'bg-violet-50 text-violet-800 ring-violet-200/70',
                                    'override' => 'bg-amber-50 text-amber-800 ring-amber-200/70',
                                    default => 'bg-emerald-50 text-emerald-800 ring-emerald-200/70',
                                };
                                $tonePill = match ($action) {
                                    'ai_assist' => __('AI assist'),
                                    'override' => __('Override'),
                                    default => __('Initial'),
                                };
                            @endphp
                            <li class="px-5 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-700">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $tone }}">{{ $tonePill }}</span>
                                        <span class="tabular-nums">{{ $row['graded_at'] ?? '' }}</span>
                                    </span>
                                    <span class="text-xs font-bold tabular-nums text-slate-900">
                                        {{ rtrim(rtrim(number_format((float) ($row['points_awarded'] ?? 0), 2, '.', ''), '0'), '.') }} / {{ rtrim(rtrim(number_format($maxMarks, 2, '.', ''), '0'), '.') }} {{ __('pts') }}
                                    </span>
                                </div>
                                @if (! empty($row['override_reason']))
                                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                                        <span class="font-semibold text-slate-700">{{ __('Reason:') }}</span>
                                        {{ $row['override_reason'] }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </article>
            @endif
        </section>

        {{-- ========================== RIGHT COLUMN ========================== --}}
        <section class="space-y-5 lg:col-span-2 lg:sticky lg:top-4" aria-label="{{ __('Submitted answer and grading') }}">
            {{-- Submitted answer card --}}
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-gradient-to-r from-cyan-50/60 via-white to-white px-5 py-3">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-cyan-800">
                        <i class="fa-solid fa-file-pen text-cyan-600" aria-hidden="true"></i>
                        {{ __('Submitted answer') }}
                    </p>
                    @if (! empty($aiSuggestAvailable))
                        <form
                            method="post"
                            action="{{ route('examiner.grading.ai-suggest', $answer) }}"
                            onsubmit="return confirm(@js(__('Run AI assist on this submission? It will fill in a suggested mark and feedback that you can immediately review or override below.')));"
                        >
                            @csrf
                            <button type="submit" class="group inline-flex min-h-[34px] items-center gap-1.5 rounded-full bg-gradient-to-r from-violet-600 to-fuchsia-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm shadow-violet-500/20 transition hover:-translate-y-0.5 hover:shadow-md hover:shadow-violet-500/30">
                                <i class="fa-solid fa-wand-magic-sparkles text-[11px]" aria-hidden="true"></i>
                                {{ __('AI suggest grade') }}
                            </button>
                        </form>
                    @elseif (! empty($aiEnabled) && $answer->evaluation_status === 'manual_graded')
                        <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-semibold text-violet-800 ring-1 ring-inset ring-violet-200/70">
                            <i class="fa-solid fa-circle-check text-[9px]" aria-hidden="true"></i>
                            {{ __('Already graded') }}
                        </span>
                    @endif
                </header>

                @if ($plainTextForCount === '')
                    <div class="px-5 py-5 text-center text-sm text-slate-500">
                        <i class="fa-regular fa-circle-question text-slate-300" aria-hidden="true"></i>
                        {{ __('No typed response was submitted.') }}
                    </div>
                @else
                    <div class="max-h-[28rem] overflow-y-auto px-5 py-4 text-[15px] leading-relaxed text-slate-800">
                        @if ($isHtmlAnswer)
                            <div class="qs-essay-rendered">
                                {!! \App\Support\EssayAnswerHtml::sanitize($submittedText) !!}
                            </div>
                        @else
                            <p class="whitespace-pre-wrap">{{ $submittedText }}</p>
                        @endif
                    </div>
                    <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-2 text-[11px] text-slate-500">
                        <span class="inline-flex items-center gap-1">
                            <i class="fa-regular fa-keyboard text-slate-400" aria-hidden="true"></i>
                            {{ __(':n words', ['n' => number_format($wordCount)]) }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <i class="fa-regular fa-file-lines text-slate-400" aria-hidden="true"></i>
                            {{ __(':n characters', ['n' => number_format($charCount)]) }}
                        </span>
                    </footer>
                @endif
            </article>

            {{-- AI suggestion card (when present) --}}
            @if ($aiAssist !== null || $lastAiHistory !== null)
                @php
                    $aiPoints = (float) ($lastAiHistory['points_awarded'] ?? 0);
                    $aiPercent = $maxMarks > 0 ? max(0, min(100, ($aiPoints / $maxMarks) * 100)) : 0;
                @endphp
                <article class="overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-fuchsia-50/40 shadow-sm">
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-violet-200/70 bg-violet-100/40 px-5 py-3">
                        <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-violet-900">
                            <i class="fa-solid fa-wand-magic-sparkles text-violet-700" aria-hidden="true"></i>
                            {{ __('AI suggestion') }}
                        </p>
                        @if ($maxMarks > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-700 px-2.5 py-1 text-[11px] font-bold text-white">
                                {{ rtrim(rtrim(number_format($aiPoints, 2, '.', ''), '0'), '.') }} / {{ rtrim(rtrim(number_format($maxMarks, 2, '.', ''), '0'), '.') }} {{ __('pts') }}
                            </span>
                        @endif
                    </header>
                    <div class="space-y-3 px-5 py-4 text-sm text-violet-950">
                        @if ($maxMarks > 0)
                            <div class="flex h-1.5 overflow-hidden rounded-full bg-violet-200/50" role="img" aria-label="{{ __('AI suggested score') }}">
                                <span class="block h-full bg-gradient-to-r from-violet-500 to-fuchsia-500" style="width: {{ $aiPercent }}%"></span>
                            </div>
                        @endif
                        @if (! empty($answer->grader_feedback))
                            <p class="whitespace-pre-wrap leading-relaxed">{{ $answer->grader_feedback }}</p>
                        @endif
                        @if (is_array($aiAssist))
                            <dl class="space-y-1 text-xs leading-relaxed">
                                @if (! empty($aiAssist['strengths']))
                                    <div class="flex items-start gap-2">
                                        <dt class="shrink-0 font-semibold text-emerald-800">{{ __('Strengths:') }}</dt>
                                        <dd class="text-slate-700">{{ $aiAssist['strengths'] }}</dd>
                                    </div>
                                @endif
                                @if (! empty($aiAssist['improvements']))
                                    <div class="flex items-start gap-2">
                                        <dt class="shrink-0 font-semibold text-amber-800">{{ __('Improvements:') }}</dt>
                                        <dd class="text-slate-700">{{ $aiAssist['improvements'] }}</dd>
                                    </div>
                                @endif
                            </dl>
                        @endif
                        <p class="rounded-lg bg-white/60 px-3 py-2 text-[11px] text-violet-900/80 ring-1 ring-inset ring-violet-200/60">
                            <i class="fa-solid fa-circle-info text-[10px]" aria-hidden="true"></i>
                            {{ __('You are responsible for the final grade. Edit below to override; an audit reason will be required.') }}
                        </p>
                    </div>
                </article>
            @endif

            {{-- Grading form card --}}
            <form method="post" action="{{ route('examiner.grading.grade', $answer) }}"
                  class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @csrf
                <header class="flex items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-emerald-50/60 via-white to-white px-5 py-3">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-800">
                        <i class="fa-solid fa-pen-to-square text-emerald-600" aria-hidden="true"></i>
                        {{ $isOverride ? __('Override grade') : __('Grade this answer') }}
                    </p>
                    @if ($isOverride)
                        <span class="text-[11px] text-slate-500">{{ __('Audit reason required') }}</span>
                    @endif
                </header>
                <div class="space-y-4 px-5 py-4">
                    @if ($isOverride)
                        <p class="rounded-lg bg-amber-50/80 px-3 py-2 text-xs text-amber-900 ring-1 ring-inset ring-amber-200/70">
                            <i class="fa-solid fa-triangle-exclamation text-[11px]" aria-hidden="true"></i>
                            {{ __('You are updating an existing grade. A short reason is required for the audit trail.') }}
                        </p>
                    @endif

                    <div>
                        <label class="mb-1.5 flex items-center justify-between gap-2 text-sm font-medium text-slate-700">
                            <span>{{ __('Points') }}</span>
                            <span class="text-[11px] font-semibold text-slate-500">
                                {{ __('Max') }}
                                <span class="tabular-nums text-slate-900">{{ rtrim(rtrim(number_format($maxMarks, 2, '.', ''), '0'), '.') }}</span>
                            </span>
                        </label>
                        <div class="relative">
                            <input type="number" name="points_awarded" step="0.01" min="0" max="{{ $maxMarks }}" required
                                   class="qs-input mt-0 w-full py-2.5 pr-16 text-base font-semibold tabular-nums"
                                   value="{{ old('points_awarded', $answer->points_awarded ?? 0) }}" />
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-slate-400">
                                / {{ rtrim(rtrim(number_format($maxMarks, 2, '.', ''), '0'), '.') }} {{ __('pts') }}
                            </span>
                        </div>
                        @error('points_awarded')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ __('Feedback') }} <span class="text-xs font-normal text-slate-400">({{ __('optional') }})</span></label>
                        <textarea name="grader_feedback" rows="5"
                                  placeholder="{{ __('Explain what was strong, what could improve, and how the grade was reached.') }}"
                                  class="qs-input mt-0 py-2.5">{{ old('grader_feedback', $answer->grader_feedback) }}</textarea>
                    </div>

                    @if ($isOverride)
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">
                                {{ __('Reason for change') }} <span class="text-rose-600">*</span>
                            </label>
                            <textarea name="override_reason" rows="3" required
                                      class="qs-input mt-0 py-2.5"
                                      placeholder="{{ __('Explain why the mark is being changed.') }}">{{ old('override_reason') }}</textarea>
                            @error('override_reason')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
                <footer class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-3 sm:flex-row sm:items-center sm:justify-end">
                    <a href="{{ route('examiner.grading.pending') }}"
                       class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit"
                            class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 px-5 text-sm font-semibold text-white shadow-sm shadow-emerald-500/20 transition hover:-translate-y-0.5 hover:shadow-md hover:shadow-emerald-500/30">
                        <i class="fa-solid fa-check text-[12px]" aria-hidden="true"></i>
                        {{ $isOverride ? __('Save override') : __('Save grade') }}
                    </button>
                </footer>
            </form>
        </section>
    </div>
</x-layouts.examiner>
