<x-layouts.examiner>
    @php
        $poolQuestionTotal = (int) ($poolQuestionTotal ?? 0);
        $poolApprovedCount = (int) ($poolApprovedCount ?? 0);
        $poolDraftCount = max(0, $poolQuestionTotal - $poolApprovedCount);
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
    @php
        $__poolUrlTpl = '';
        $__qAny = $exam->sections->flatMap(fn ($sec) => $sec->questions)->first();
        if ($__qAny !== null) {
            $__poolUrlTpl = str_replace(
                '/questions/'.$__qAny->id.'/',
                '/questions/__QID__/',
                route('examiner.exams.questions.pool-status', [$exam, $__qAny])
            );
        }
    @endphp

    <x-slot name="title">{{ __('Review question pool') }}</x-slot>
    <x-slot name="subtitle">{{ $exam->title }}</x-slot>

    <div
        x-data="{
            generationLocked: @js($generationLocked),
            poolComplete: @js($poolComplete),
            canEditPool: @js($canEditPool),
            overviewQuestions: @js($overviewQuestions),
            qFilter: '',
            poolQFilter: '',
            poolUrlTemplate: @js($__poolUrlTpl),
            fileSlug: @js($slugTitle),
            lockedLabel: @js(__('Locked')),
            filteredQuestions() {
                const f = (this.qFilter || '').toLowerCase().trim();
                if (!f) return this.overviewQuestions;
                return this.overviewQuestions.filter((q) =>
                    (q.text || '').toLowerCase().includes(f)
                    || (q.topic || '').toLowerCase().includes(f)
                    || (q.type || '').toLowerCase().includes(f)
                    || (q.typeLabel || '').toLowerCase().includes(f)
                    || (q.section || '').toLowerCase().includes(f)
                );
            },
            draftPoolCount() {
                return this.overviewQuestions.filter((q) => q.pool_status === 'draft').length;
            },
            approvedCount() {
                return this.overviewQuestions.filter((q) => q.pool_status === 'approved').length;
            },
            poolDraftQuestions() {
                const f = (this.poolQFilter || '').toLowerCase().trim();
                let qs = this.overviewQuestions.filter((q) => q.pool_status === 'draft');
                if (!f) return qs;
                return qs.filter((q) =>
                    (q.text || '').toLowerCase().includes(f)
                    || (q.topic || '').toLowerCase().includes(f)
                    || (q.type || '').toLowerCase().includes(f)
                    || (q.typeLabel || '').toLowerCase().includes(f)
                    || (q.section || '').toLowerCase().includes(f)
                );
            },
            poolStatusUrl(qid) {
                const t = this.poolUrlTemplate || '';
                if (!t || !t.includes('__QID__')) {
                    return '#';
                }
                return t.replace('__QID__', String(qid));
            },
            downloadTxt(fullPool) {
                let out = '';
                const qs = fullPool ? this.overviewQuestions : this.overviewQuestions.filter((q) => q.pool_status === 'approved');
                for (const q of qs) {
                    out += 'Q' + q.n + '. [' + q.pool_status + '] (' + q.type + ')\n' + q.text + '\nAnswer: ' + q.answer + '\n';
                    if (q.topic) out += 'Topic: ' + q.topic + '\n';
                    out += '\n';
                }
                const blob = new Blob([out], { type: 'text/plain;charset=utf-8' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (this.fileSlug || 'quiz') + (fullPool ? '-full-pool.txt' : '-questions.txt');
                a.click();
                URL.revokeObjectURL(a.href);
            },
        }"
        class="space-y-6"
    >
    {{-- Step banner --}}
    <header class="rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">{{ __('Step 3 of 3') }} · {{ __('Review & approve') }}</p>
                <h1 class="mt-1 text-lg font-semibold text-slate-900 sm:text-xl">{{ $exam->title }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($courseTitleUpper)
                        <span>{{ $courseTitleUpper }}</span>
                        <span class="text-slate-400"> · </span>
                    @endif
                    <span x-text="overviewQuestions.length"></span>
                    <span>{{ __('questions in pool') }}</span>
                    <span class="text-slate-400"> · </span>
                    <span class="text-emerald-700"><span x-text="approvedCount()"></span> {{ __('approved') }}</span>
                    <span class="text-slate-400"> · </span>
                    <span class="text-amber-700"><span x-text="draftPoolCount()"></span> {{ __('pending') }}</span>
                </p>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">
                    {{ __('Approve the generated questions below. Once everything looks good, continue to the assessment workspace where you can publish and share with students.') }}
                </p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-2">
                <a href="{{ route('examiner.exams.index') }}" class="text-xs font-medium text-slate-500 underline decoration-slate-300 underline-offset-4 hover:text-slate-800">{{ __('← Exams') }}</a>
            </div>
        </div>

        {{-- Step progress --}}
        <div class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-medium text-slate-500">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">
                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">1</span>
                {{ __('Create') }}
            </span>
            <span class="text-slate-300">→</span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">
                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">2</span>
                {{ __('Proctoring') }}
            </span>
            <span class="text-slate-300">→</span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 font-semibold text-sky-900">
                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-sky-600 text-[10px] font-bold text-white">3</span>
                {{ __('Review & approve') }}
            </span>
            <span class="text-slate-300">→</span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-slate-500">
                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-300 bg-white text-[10px] font-bold text-slate-500">4</span>
                {{ __('Workspace') }}
            </span>
        </div>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @error('lifecycle')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->get('lifecycle') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @enderror

    {{-- Top approve-all action when there are drafts --}}
    <div
        x-show="canEditPool && draftPoolCount() > 0"
        x-cloak
        class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-4"
    >
        <div>
            <p class="font-semibold text-sky-900">{{ __('Action required') }}</p>
            <p class="mt-0.5 text-sky-900/90">{{ __('Approve generated questions below so they can be delivered to students.') }}</p>
        </div>
        <form method="post" action="{{ route('examiner.exams.questions.pool-status.bulk', $exam) }}" class="mt-3 shrink-0 sm:mt-0">
            @csrf
            @method('PATCH')
            <input type="hidden" name="pool_status" value="approved" />
            <input type="hidden" name="mode" value="all" />
            <button type="submit" class="inline-flex min-h-[44px] items-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-sky-700">
                {{ __('Approve all') }} (<span x-text="draftPoolCount()"></span>)
            </button>
        </form>
    </div>

    {{-- Pool-complete success state --}}
    <div
        x-show="poolComplete"
        x-cloak
        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 shadow-sm"
    >
        <p class="font-semibold text-emerald-900">{{ __('All questions approved') }}</p>
        <p class="mt-0.5 text-emerald-900/90">{{ __('You are ready to publish. Use the Continue button at the bottom to open the workspace and share with your students.') }}</p>
    </div>

    {{-- Pending pool draft list --}}
    <section x-show="canEditPool && draftPoolCount() > 0" x-cloak class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="pool-draft-heading">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="pool-draft-heading" class="text-base font-semibold text-slate-900">{{ __('Pending approval') }}</h2>
                <p class="mt-1 text-xs text-slate-600">{{ __('Draft questions must be approved before delivery. Reject anything that does not belong.') }}</p>
            </div>
        </div>

        <label class="mt-4 block">
            <span class="sr-only">{{ __('Filter pool') }}</span>
            <input
                type="search"
                x-model="poolQFilter"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                placeholder="{{ __('Type to filter questions…') }}"
            />
        </label>

        <div class="mt-6 space-y-4">
            <template x-for="q in poolDraftQuestions()" :key="'pool-draft-' + q.id">
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold leading-relaxed text-slate-900 whitespace-pre-wrap" x-text="q.text"></p>
                            <p class="mt-1 text-xs text-slate-500">
                                <span x-text="q.typeLabel"></span>
                                <span class="text-slate-400"> · </span>
                                <span x-text="q.section"></span>
                            </p>
                        </div>
                        <div x-show="canEditPool" class="flex shrink-0 flex-wrap justify-end gap-2">
                            <form method="post" class="inline" :action="poolStatusUrl(q.id)">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="pool_status" value="approved" />
                                <button type="submit" class="rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700">{{ __('Approve') }}</button>
                            </form>
                            <form
                                method="post"
                                class="inline"
                                :action="poolStatusUrl(q.id)"
                                onsubmit="return confirm(@js(__('Reject this question? It will be moved out of the active pool (archived).')));"
                            >
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="pool_status" value="archived" />
                                <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">{{ __('Reject') }}</button>
                            </form>
                        </div>
                    </div>

                    <template x-if="q.options && q.options.length">
                        <ul class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                            <template x-for="(opt, idx) in q.options" :key="'opt-' + q.id + '-' + idx">
                                <li class="flex flex-wrap items-baseline gap-2 text-sm text-slate-800">
                                    <span class="w-6 shrink-0 font-semibold text-slate-500" x-text="String.fromCharCode(65 + idx) + '.'"></span>
                                    <span class="min-w-0 flex-1" x-text="opt"></span>
                                    <template x-if="q.correct_indices && q.correct_indices.includes(idx)">
                                        <span class="shrink-0 text-xs font-semibold text-sky-700">({{ __('correct') }})</span>
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </template>
                    <template x-if="!q.options || !q.options.length">
                        <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-700">
                            <span class="font-medium text-slate-500">{{ __('Answer') }}:</span>
                            <span x-text="q.answer"></span>
                        </p>
                    </template>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-if="q.topic">
                                <span class="inline-flex max-w-full items-center rounded-md bg-sky-100 px-2.5 py-0.5 text-xs font-medium text-sky-900" x-text="q.topic"></span>
                            </template>
                            <template x-if="q.ai">
                                <span class="inline-flex rounded-md bg-violet-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-violet-800">{{ __('AI') }}</span>
                            </template>
                        </div>
                    </div>
                </article>
            </template>
        </div>

        <template x-if="overviewQuestions.length && poolDraftQuestions().length === 0">
            <p class="mt-6 text-center text-sm text-slate-500">{{ __('No draft questions match this filter.') }}</p>
        </template>
        @error('pool_status')
            <p class="mt-3 text-center text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </section>

    {{-- Full question overview (approved + drafts, read-only summary) --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="q-overview-heading">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 id="q-overview-heading" class="text-sm font-semibold text-slate-900">{{ __('Question overview') }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ __('Questions:') }} <strong class="font-semibold text-slate-900">{{ $poolQuestionTotal }}</strong>
                    {{ __('in this assessment (filter applies below).') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="downloadTxt(false)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">{{ __('Download approved TXT') }}</button>
                <button type="button" @click="downloadTxt(true)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">{{ __('Download full pool TXT') }}</button>
            </div>
        </div>
        <label class="mt-4 block">
            <span class="sr-only">{{ __('Filter questions') }}</span>
            <input
                type="search"
                x-model="qFilter"
                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                placeholder="{{ __('Type to filter by question text, topic, type…') }}"
            />
        </label>

        <ul class="mt-6 space-y-3">
            <template x-for="q in filteredQuestions()" :key="q.id">
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.9)]">
                    <div class="flex gap-3 sm:gap-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-xs font-semibold tabular-nums text-slate-700" x-text="q.n"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium leading-relaxed text-slate-900 whitespace-pre-wrap" x-text="q.text"></p>
                            <p class="mt-2 text-sm font-semibold text-sky-800" x-show="q.marks > 0">
                                <span class="text-slate-500 font-medium">{{ __('Marks') }}:</span>
                                <span x-text="q.marks"></span>
                            </p>
                            <template x-if="q.options && q.options.length">
                                <ul class="mt-3 space-y-1 text-sm text-slate-800">
                                    <template x-for="(opt, idx) in q.options" :key="idx">
                                        <li>
                                            <span class="font-semibold text-slate-500" x-text="String.fromCharCode(65 + idx) + '.'"></span>
                                            <span x-text="opt"></span>
                                            <span x-show="q.correct_indices && q.correct_indices.includes(idx)" class="ml-1 text-xs font-semibold text-emerald-700">({{ __('correct') }})</span>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700" x-show="q.type !== 'essay' && q.answer">
                                <span class="text-slate-500">{{ __('Answer') }}:</span> <span x-text="q.answer"></span>
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-semibold uppercase tracking-wide text-slate-700" x-text="q.typeLabel"></span>
                                <template x-if="q.ai">
                                    <span class="rounded-md bg-violet-50 px-2 py-0.5 font-semibold uppercase tracking-wide text-violet-800">{{ __('AI') }}</span>
                                </template>
                                <template x-if="q.topic">
                                    <span class="text-slate-500">· <span x-text="q.topic"></span></span>
                                </template>
                            </div>
                        </div>
                        <span
                            class="shrink-0 self-start rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                            :class="q.pool_status === 'approved' ? 'bg-emerald-100 text-emerald-800' : (q.pool_status === 'archived' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-900')"
                            x-text="q.pool_status === 'approved' ? lockedLabel : q.pool_status"
                        ></span>
                    </div>
                </li>
            </template>
        </ul>
        <template x-if="overviewQuestions.length && filteredQuestions().length === 0">
            <p class="mt-6 text-center text-sm text-slate-500">{{ __('No questions match this filter.') }}</p>
        </template>
        <template x-if="!overviewQuestions.length">
            <p class="mt-6 text-center text-sm text-slate-500">{{ __('No questions yet.') }}</p>
        </template>
    </section>

    {{-- Add more (optional, collapsible) --}}
    @unless ($generationLocked)
        @if ($canEditContent)
        <details class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">
                {{ __('Need to add more questions?') }}
                <span class="ml-2 text-xs font-normal text-slate-500">{{ __('Optional · import JSON or generate with AI') }}</span>
            </summary>
            <div class="mt-4 space-y-4" x-data="{ sourceMode: '{{ old('ai_custom_prompt') || old('ai_topic') || old('ai_count') ? 'ai' : 'json' }}' }">
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="allowed-qtypes-heading">
                    <h3 id="allowed-qtypes-heading" class="text-sm font-semibold text-slate-900">{{ __('Allowed question types') }}</h3>
                    <p class="mt-1 text-xs text-slate-600">{{ __('The pool only accepts questions of these types. You cannot remove a type while non-archived questions still use it.') }}</p>
                    @error('selected_question_types')
                        <p class="mt-2 text-xs text-rose-700">{{ $message }}</p>
                    @enderror
                    <form method="post" action="{{ route('examiner.exams.question-types.update', $exam) }}" class="mt-3 flex flex-wrap items-center gap-3">
                        @csrf
                        @method('PATCH')
                        @foreach (\App\Support\AssessmentQuestionTypes::ALL as $tq)
                            <label class="inline-flex items-center gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="selected_question_types[]" value="{{ $tq }}" class="size-4 rounded border-slate-300 text-sky-600" @checked(in_array($tq, $allowedQuestionTypes, true)) />
                                <span>{{ $questionTypeLabels[$tq] ?? $tq }}</span>
                            </label>
                        @endforeach
                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">{{ __('Save types') }}</button>
                    </form>
                </section>
                <div class="rounded-xl border border-qs-soft bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-qs-text mb-2">{{ __('Question source') }}</h3>
                    <div class="flex flex-wrap gap-3 text-sm text-qs-text">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" value="json" x-model="sourceMode" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                            {{ __('Use JSON import') }}
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" value="ai" x-model="sourceMode" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                            {{ __('Use AI generation') }}
                        </label>
                    </div>
                </div>
                <div x-show="sourceMode === 'json'" x-cloak class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-qs-text mb-2">{{ __('External AI via JSON') }}</h3>
                    <p class="text-xs text-qs-muted mb-3">{{ __('Enter topics and number of questions, generate a prompt template, run it in your external AI, then paste returned JSON below.') }}</p>
                    <form method="post" action="{{ route('examiner.exams.questions.ai.prompt', $exam) }}" class="grid gap-3 sm:grid-cols-2" x-data="topicTags(@js(old('ai_topic', '')))">
                        @csrf
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-qs-muted mb-1">{{ __('Topics') }}</label>
                            <input type="hidden" name="ai_topic" :value="joined" />
                            <div class="w-full rounded-lg border border-qs-soft bg-white p-2">
                                <div class="mb-1.5 flex flex-wrap gap-1.5" x-show="tags.length > 0">
                                    <template x-for="(tag, idx) in tags" :key="idx + ':' + JSON.stringify(tag)">
                                        <span class="inline-flex items-center gap-1 rounded-md" :class="topicChipClass(idx)">
                                            <span x-text="tag"></span>
                                            <button type="button" class="opacity-70 hover:opacity-100" :class="topicChipCloseClass(idx)" @click="remove(idx)">×</button>
                                        </span>
                                    </template>
                                </div>
                                <input type="text" x-model="input" @keydown.enter.prevent="addFromInput()" @keydown.comma.prevent="commitOneSegmentOnComma($event)" @blur="addFromInput()" placeholder="{{ __('Separate topics with commas; use "quotes" if a topic contains a comma. Comma or Enter adds.') }}" class="w-full border-0 p-0 text-sm focus:outline-none focus:ring-0" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-qs-muted mb-1">{{ __('Number of questions') }}</label>
                            <input type="number" name="ai_count" value="{{ old('ai_count', 5) }}" min="1" max="250" required class="w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm" />
                        </div>
                        <input type="hidden" name="ai_marks" value="1" />
                        <input type="hidden" name="ai_difficulty" value="undergraduate" />
                        <div class="sm:col-span-2 space-y-1">
                            <span class="block text-xs text-qs-muted">{{ __('Question types to include in the prompt') }}</span>
                            <div class="flex flex-wrap gap-3">
                                @foreach ($allowedQuestionTypes as $tq)
                                    <label class="inline-flex items-center gap-2 text-sm text-qs-text">
                                        <input type="checkbox" name="ai_question_types[]" value="{{ $tq }}" class="size-4 rounded border-qs-soft text-qs-accent" @checked(in_array($tq, old('ai_question_types', $__aiTypeDefault), true)) />
                                        <span>{{ $questionTypeLabels[$tq] ?? $tq }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">{{ __('Generate prompt template') }}</button>
                        </div>
                    </form>
                    @if (session('generated_ai_prompt'))
                        <div class="mt-4">
                            <label class="block text-xs text-qs-muted mb-1">{{ __('Prompt (copy or use below)') }}</label>
                            <textarea readonly rows="10" class="w-full rounded-lg border border-qs-soft bg-qs-card px-3 py-2 text-xs font-mono text-qs-text">{{ session('generated_ai_prompt') }}</textarea>
                        </div>
                    @endif
                    @error('import_json')
                        <div class="mt-3 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-3 py-2 text-xs text-qs-danger whitespace-pre-line">{{ $message }}</div>
                    @enderror
                    <form method="post" action="{{ route('examiner.exams.questions.import.preview', $exam) }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="block text-xs text-qs-muted">{{ __('Paste JSON result') }}</label>
                        <textarea name="import_json" rows="8" class="w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm font-mono text-qs-text" placeholder='{"sections":[{"title":"Section A","questions":[...]}]}'>{{ old('import_json') }}</textarea>
                        <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">{{ __('Preview import') }}</button>
                    </form>
                </div>

                @if ($aiEnabled)
                    <div x-show="sourceMode === 'ai'" x-cloak class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-qs-text mb-2">{{ __('Generate with AI (internal)') }}</h3>
                        <p class="text-xs text-qs-muted mb-3">{{ __('Uses encrypted API settings from System settings. Output is validated like pasted JSON before preview.') }}</p>
                        @error('ai')
                            <div class="mb-3 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-3 py-2 text-xs text-qs-danger whitespace-pre-line">{{ $message }}</div>
                        @enderror
                        <form method="post" action="{{ route('examiner.exams.questions.ai.generate', $exam) }}" class="space-y-3" x-data="topicTags(@js(old('ai_topic', '')))">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-qs-muted mb-1">{{ __('Topics') }}</label>
                                    <input type="hidden" name="ai_topic" :value="joined" />
                                    <div class="w-full rounded-lg border border-qs-soft bg-white p-2">
                                        <div class="mb-1.5 flex flex-wrap gap-1.5" x-show="tags.length > 0">
                                            <template x-for="(tag, idx) in tags" :key="idx + ':' + JSON.stringify(tag)">
                                                <span class="inline-flex items-center gap-1 rounded-md" :class="topicChipClass(idx)">
                                                    <span x-text="tag"></span>
                                                    <button type="button" class="opacity-70 hover:opacity-100" :class="topicChipCloseClass(idx)" @click="remove(idx)">×</button>
                                                </span>
                                            </template>
                                        </div>
                                        <input type="text" x-model="input" @keydown.enter.prevent="addFromInput()" @keydown.comma.prevent="commitOneSegmentOnComma($event)" @blur="addFromInput()" placeholder="{{ __('Separate topics with commas; use "quotes" if a topic contains a comma. Comma or Enter adds.') }}" class="w-full border-0 p-0 text-sm focus:outline-none focus:ring-0" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-qs-muted mb-1">{{ __('Number of questions') }}</label>
                                    <input type="number" name="ai_count" value="{{ old('ai_count', 5) }}" min="1" max="250" class="w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm" />
                                </div>
                                <input type="hidden" name="ai_custom_prompt" value="" />
                                <input type="hidden" name="ai_marks" value="1" />
                                <input type="hidden" name="ai_difficulty" value="undergraduate" />
                                <div class="sm:col-span-2 space-y-1">
                                    <span class="block text-xs text-qs-muted">{{ __('Question types to generate') }}</span>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach ($allowedQuestionTypes as $tq)
                                            <label class="inline-flex items-center gap-2 text-sm text-qs-text">
                                                <input type="checkbox" name="ai_question_types[]" value="{{ $tq }}" class="size-4 rounded border-qs-soft text-qs-accent" @checked(in_array($tq, old('ai_question_types', $__aiTypeDefault), true)) />
                                                <span>{{ $questionTypeLabels[$tq] ?? $tq }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="qs-btn-primary min-h-[44px] text-sm">{{ __('Generate & preview') }}</button>
                        </form>
                    </div>
                @endif

                @if ($importPreview && $canEditContent)
                    <div class="rounded-xl border border-qs-accent/40 bg-qs-accent/10 p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-qs-text">{{ __('Import preview') }}</h3>
                                <p class="text-xs text-qs-muted mt-1">{{ __('Source:') }} {{ $importPreview['source'] ?? 'paste' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <form method="post" action="{{ route('examiner.exams.questions.import.commit', $exam) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="qs-btn-primary min-h-[44px] text-sm">{{ __('Save imported questions') }}</button>
                                </form>
                                <form method="post" action="{{ route('examiner.exams.questions.import.cancel', $exam) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">{{ __('Cancel') }}</button>
                                </form>
                            </div>
                        </div>
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($importPreview['sections'] ?? [] as $sec)
                                <li class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                                    <p class="font-semibold text-qs-text">{{ $sec['title'] }}</p>
                                    <ul class="mt-2 list-disc list-inside text-qs-muted">
                                        @foreach ($sec['questions'] ?? [] as $q)
                                            <li>{{ $q['type'] }} — {{ \Illuminate\Support\Str::limit($q['question_text'], 120) }} ({{ $q['marks'] }} pts)</li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </details>
        @endif
    @endunless

    {{-- Sticky continue footer on mobile --}}
    <div class="sticky bottom-4 z-20 mt-6 flex justify-end">
        <a
            href="{{ route('examiner.quizzes.workspace', $exam) }}"
            class="inline-flex min-h-[48px] items-center rounded-full px-5 text-sm font-semibold shadow-lg"
            :class="poolComplete ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'"
        >
            <span x-text="poolComplete ? @js(__('Continue to workspace')) : @js(__('Skip for now'))"></span>
            <span class="ml-2">→</span>
        </a>
    </div>

    </div>

    @include('examiner.exams.partials.topic-tags-script')
</x-layouts.examiner>
