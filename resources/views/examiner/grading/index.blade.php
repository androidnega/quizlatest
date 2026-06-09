<x-layouts.examiner>
    <x-slot name="title">{{ __('Assignment grading') }}</x-slot>
    <x-slot name="subtitle">{{ __('Pending essay submissions') }}</x-slot>

    <div class="mb-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-600">
        {{ __('Only assignment essays appear here. Quiz-style questions (MCQ, True/False, Fill-in-the-blank) are auto-graded and do not need manual or AI review.') }}
    </div>

    @if (! empty($examFilter))
        <div class="mb-4 flex flex-col gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-950 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="font-semibold">{{ __('Filtering: :title', ['title' => $examFilter->title]) }}</p>
                <p class="mt-1 text-xs text-violet-900/90">{{ __('Only pending submissions for this assignment are shown.') }}</p>
                <a href="{{ route('examiner.grading.pending') }}" class="mt-2 inline-block text-xs font-semibold text-violet-800 underline">{{ __('Show all pending') }}</a>
            </div>
            @if (! empty($aiAssistAvailable))
                <form
                    method="post"
                    action="{{ route('examiner.exams.assignment-grade-ai', $examFilter) }}"
                    class="shrink-0"
                    onsubmit="return confirm(@js(__('Run AI assist on all pending submissions for this assignment? You will still review marks before releasing grades.')));"
                >
                    @csrf
                    {{-- Tells the controller to bring us back to the grading
                         queue instead of the workspace overview, so the user
                         actually sees the success flash + the "review & release"
                         panel for the just-drafted submissions. --}}
                    <input type="hidden" name="return_to" value="pending">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-violet-700 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-violet-800"
                    >
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        {{ __('AI assist all pending') }}
                    </button>
                    <p class="mt-1 text-[11px] text-violet-900/80">{{ __('Drafts marks + feedback; you confirm before release.') }}</p>
                </form>
            @elseif (! empty($examFilter) && ! empty($aiEnabled) && $answers->total() === 0)
                <span class="self-center text-xs text-violet-900/70">{{ __('Nothing pending — AI assist will appear here when submissions arrive.') }}</span>
            @endif
        </div>
    @else
        @if (! empty($aiEnabled) && ! empty($assignmentsWithPending))
            <section class="mb-4 rounded-xl border border-violet-200 bg-violet-50/70 px-4 py-3 text-sm text-violet-950">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        {{ __('AI assist by assignment') }}
                    </p>
                    <p class="text-[11px] text-violet-900/80">{{ __('Drafts marks + feedback — you still confirm before release.') }}</p>
                </div>
                <ul class="mt-3 space-y-2">
                    @foreach ($assignmentsWithPending as $row)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-violet-200/60 bg-white px-3 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $row['title'] }}</p>
                                <p class="text-[11px] text-slate-600">
                                    {{ trans_choice('{1} :n submission pending|[2,*] :n submissions pending', $row['submissions'], ['n' => $row['submissions']]) }}
                                    @if ($row['answers'] !== $row['submissions'])
                                        · {{ trans_choice('{1} :n essay answer|[2,*] :n essay answers', $row['answers'], ['n' => $row['answers']]) }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <a
                                    href="{{ route('examiner.grading.pending', ['exam' => $row['quiz_id']]) }}"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                                >
                                    {{ __('Open') }}
                                </a>
                                <form
                                    method="post"
                                    action="{{ route('examiner.exams.assignment-grade-ai', $row['quiz_id']) }}"
                                    onsubmit="return confirm(@js(__('Run AI assist on all pending submissions for this assignment? You will still review marks before releasing grades.')));"
                                >
                                    @csrf
                                    {{-- See note above: keep the user on the grading queue and surface drafts. --}}
                                    <input type="hidden" name="return_to" value="pending">
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-violet-700 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-violet-800">
                                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                        {{ __('AI assist') }}
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @elseif (empty($aiEnabled))
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                {{ __('AI grading is currently disabled. Ask a super admin to turn on "AI" in Admin → Settings to unlock automatic grading assistance.') }}
            </div>
        @endif
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (session('ai_grade_errors') && is_array(session('ai_grade_errors')) && count(session('ai_grade_errors')) > 0)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('Some submissions could not be AI-graded:') }}</p>
            <ul class="mt-1 list-disc pl-5 text-xs">
                @foreach (session('ai_grade_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Recently AI-drafted panel. Lists every answer the examiner just
         AI-graded so they can see the AI's mark + feedback snippet and jump
         straight to "Review & release" — otherwise the rows would disappear
         from the pending table the moment AI graded them. --}}
    @if (! empty($aiJustGradedAnswers) && $aiJustGradedAnswers->isNotEmpty())
        <section class="mb-5 overflow-hidden rounded-2xl border border-violet-300/70 bg-gradient-to-br from-violet-50 via-white to-fuchsia-50/40 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-violet-200/70 bg-violet-100/40 px-4 py-3">
                <div class="min-w-0">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold text-violet-950">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        {{ __('Recently AI-drafted — review & release') }}
                    </p>
                    @if (! empty($aiJustCompletedMeta['exam_title']))
                        <p class="mt-0.5 text-[11px] text-violet-900/80">
                            {{ __(':n draft(s) for :title', [
                                'n' => $aiJustGradedAnswers->count(),
                                'title' => $aiJustCompletedMeta['exam_title'],
                            ]) }}
                        </p>
                    @endif
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-700/10 px-2.5 py-1 text-[11px] font-semibold text-violet-900 ring-1 ring-inset ring-violet-300/70">
                    <i class="fa-solid fa-circle-info text-[10px]" aria-hidden="true"></i>
                    {{ __('You still confirm each grade before release.') }}
                </span>
            </header>
            <ul class="divide-y divide-violet-200/70">
                @foreach ($aiJustGradedAnswers as $aiAnswer)
                    @php
                        $aiAssistDetail = is_array($aiAnswer->evaluation_detail['ai_assist'] ?? null)
                            ? $aiAnswer->evaluation_detail['ai_assist']
                            : [];
                        $aiFeedback = trim((string) ($aiAnswer->grader_feedback ?? ''));
                        $aiFeedbackSnippet = $aiFeedback !== '' ? \Illuminate\Support\Str::limit($aiFeedback, 200) : null;
                        $aiPoints = $aiAnswer->points_awarded;
                        $aiMax = $aiAnswer->question?->marks;
                    @endphp
                    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm">
                                <span class="font-semibold text-slate-900">{{ $aiAnswer->examSession?->student?->name ?? '—' }}</span>
                                <span class="text-xs text-slate-500">·</span>
                                <span class="text-xs text-slate-600">{{ $aiAnswer->examSession?->exam?->title ?? '—' }}</span>
                            </p>
                            <p class="mt-1 line-clamp-2 text-xs text-slate-600">
                                {{ \Illuminate\Support\Str::limit($aiAnswer->question?->question_text ?? '', 140) }}
                            </p>
                            @if ($aiFeedbackSnippet !== null)
                                <p class="mt-2 rounded-lg bg-white/80 px-3 py-2 text-xs leading-relaxed text-slate-700 ring-1 ring-inset ring-violet-200/60">
                                    <span class="font-semibold text-violet-900">{{ __('AI feedback:') }}</span>
                                    {{ $aiFeedbackSnippet }}
                                </p>
                            @endif
                            @if (! empty($aiAssistDetail['strengths']) || ! empty($aiAssistDetail['improvements']))
                                <dl class="mt-2 grid gap-1 text-[11px] text-slate-700 sm:grid-cols-2">
                                    @if (! empty($aiAssistDetail['strengths']))
                                        <div><dt class="inline font-semibold text-emerald-800">{{ __('Strengths:') }}</dt>
                                            <dd class="ml-1 inline">{{ \Illuminate\Support\Str::limit((string) $aiAssistDetail['strengths'], 120) }}</dd></div>
                                    @endif
                                    @if (! empty($aiAssistDetail['improvements']))
                                        <div><dt class="inline font-semibold text-amber-800">{{ __('Improve:') }}</dt>
                                            <dd class="ml-1 inline">{{ \Illuminate\Support\Str::limit((string) $aiAssistDetail['improvements'], 120) }}</dd></div>
                                    @endif
                                </dl>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-700 px-3 py-1 text-[11px] font-semibold text-white shadow-sm">
                                <i class="fa-solid fa-wand-magic-sparkles text-[10px]" aria-hidden="true"></i>
                                {{ number_format((float) $aiPoints, 2) }} / {{ $aiMax }} {{ __('pts') }}
                            </span>
                            <a
                                href="{{ route('examiner.grading.show', $aiAnswer) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-violet-300 bg-white px-3 py-1.5 text-xs font-semibold text-violet-900 shadow-sm hover:bg-violet-50"
                            >
                                {{ __('Review & release') }}
                                <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
        <div class="qs-table-wrap -mx-1 border-0 bg-transparent sm:mx-0">
            <table class="qs-table">
                <thead>
                    <tr>
                        <th class="text-left">{{ __('Student') }}</th>
                        <th class="text-left">{{ __('Exam') }}</th>
                        <th class="text-left">{{ __('Question') }}</th>
                        <th class="text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($answers as $answer)
                        <tr>
                            <td class="font-medium">{{ $answer->examSession?->student?->name ?? '—' }}</td>
                            <td class="text-qs-muted">{{ $answer->examSession?->exam?->title ?? '—' }}</td>
                            <td class="max-w-xs text-qs-muted line-clamp-2">{{ \Illuminate\Support\Str::limit($answer->question?->question_text ?? '', 80) }}</td>
                            <td class="text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    @if (! empty($aiEnabled))
                                        <form
                                            method="post"
                                            action="{{ route('examiner.grading.ai-suggest', $answer) }}"
                                            class="inline"
                                            onsubmit="return confirm(@js(__('Run AI assist on this submission? You can override the grade right after.')));"
                                        >
                                            @csrf
                                            <button type="submit" class="inline-flex min-h-[40px] items-center gap-1.5 rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-900 hover:bg-violet-100" title="{{ __('AI suggest grade') }}">
                                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                                {{ __('AI') }}
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('examiner.grading.show', $answer) }}" class="qs-btn-primary inline-flex min-h-[40px] items-center justify-center px-4 py-1.5 text-xs font-semibold">{{ __('Grade') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-sm text-qs-muted">{{ __('No pending essay answers.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $answers->links() }}</div>
    </div>
</x-layouts.examiner>
