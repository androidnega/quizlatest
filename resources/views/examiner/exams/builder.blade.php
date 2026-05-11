<x-layouts.examiner>
    @php($staffAcademicPeriodBadge = null)
    @php($poolComplete = $poolQuestionTotal > 0 && $poolApprovedCount === $poolQuestionTotal)
    @php($slugTitle = \Illuminate\Support\Str::slug($exam->title))
    @php($courseTitleUpper = $exam->course ? mb_strtoupper($exam->course->title) : null)

    <x-slot name="title">{{ $exam->title }}</x-slot>
    <x-slot name="subtitle"></x-slot>

    <div
        x-data="{
            tab: @js($workspaceTab),
            generationLocked: @js($generationLocked),
            poolComplete: @js($poolComplete),
            overviewQuestions: @js($overviewQuestions),
            qFilter: '',
            shareUrl: @js($shareUrl),
            displayToken: @js($displayToken),
            copyToast: false,
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
            async copyText(val) {
                try {
                    await navigator.clipboard.writeText(val);
                    this.copyToast = true;
                    setTimeout(() => (this.copyToast = false), 2000);
                } catch (e) {}
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
                    @if ($exam->questions_per_student !== null)
                        <span class="text-slate-400"> · </span><span>{{ $exam->questions_per_student }} {{ __('per student') }}</span>
                    @endif
                    <span class="text-slate-400"> · </span><span>{{ $exam->duration_minutes }} {{ __('min') }}</span>
                </p>
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
    @if ($generationLocked)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
            Question generation is locked for this assessment because questions have already been saved. Create a new quiz to generate again.
        </div>
    @endif
    @if ($poolComplete)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-900">
            All questions are approved. Moved to Delivery &amp; Publish. Question pool is now inactive.
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
                <a href="#builder-delivery" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900 hover:bg-emerald-100">{{ __('Schedule window') }}</a>
            @endif
        </div>
        <p x-show="copyToast" x-cloak class="mt-2 w-full text-xs font-medium text-emerald-700 sm:mt-0">{{ __('Copied to clipboard') }}</p>
    </section>

    {{-- Proctoring — configure on the workspace after the assessment exists --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="builder-proctoring-heading">
        <h2 id="builder-proctoring-heading" class="text-sm font-semibold text-slate-900">{{ __('Proctoring options') }}</h2>
        <p class="mt-1 text-xs text-slate-600">{{ __('Capped by your institution’s policy.') }}</p>

        <div class="mt-4 space-y-2">
            <label class="flex min-h-[44px] cursor-default items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-800">
                <input type="checkbox" class="size-4 rounded border-slate-300 text-sky-600" @checked($proctoringPolicy['allow_exam_start_snapshot']) disabled />
                <span class="min-w-0 flex-1">{{ __('Exam start verification photo') }}</span>
                <span class="shrink-0 text-[11px] font-medium text-slate-500">{{ $proctoringPolicy['allow_exam_start_snapshot'] ? __('Required by admin') : __('Disabled by admin') }}</span>
            </label>
            <label class="flex min-h-[44px] cursor-default items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-800">
                <input type="checkbox" class="size-4 rounded border-slate-300 text-sky-600" @checked($proctoringPolicy['allow_camera_monitoring']) disabled />
                <span class="min-w-0 flex-1">{{ __('Proctoring camera during exam') }}</span>
                <span class="shrink-0 text-[11px] font-medium text-slate-500">{{ $proctoringPolicy['allow_camera_monitoring'] ? __('Required by admin') : __('Disabled by admin') }}</span>
            </label>
        </div>

        @if ($canEditSchedule)
            <form method="post" action="{{ route('examiner.exams.proctoring-options.update', $exam) }}" class="mt-5 space-y-2 border-t border-slate-100 pt-5">
                @csrf
                @method('patch')
                <p class="mb-2 text-xs text-slate-600">{{ __('These apply when students take the exam.') }}</p>
                <input type="hidden" name="enable_phone" value="0" />
                <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                    <input type="checkbox" name="enable_phone" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_phone', $examProctoringControls['phone_detection_enabled'])) @disabled(! $proctoringPolicy['allow_phone']) />
                    <span>{{ __('Phone detection') }}</span>
                </label>
                <input type="hidden" name="enable_fullscreen" value="0" />
                <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                    <input type="checkbox" name="enable_fullscreen" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_fullscreen', $examProctoringControls['fullscreen_enforced'])) @disabled(! $proctoringPolicy['allow_fullscreen']) />
                    <span>{{ __('Fullscreen enforcement') }}</span>
                </label>
                <input type="hidden" name="enable_auto_submit" value="0" />
                <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                    <input type="checkbox" name="enable_auto_submit" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_auto_submit', $examProctoringControls['auto_submit_enabled'])) @disabled(! $proctoringPolicy['allow_auto_submit']) />
                    <span>{{ __('Auto submit on severe violations') }}</span>
                </label>
                <div class="pt-2">
                    <button type="submit" class="rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700">{{ __('Save proctoring options') }}</button>
                </div>
            </form>
        @else
            <div class="mt-5 space-y-2 border-t border-slate-100 pt-5 text-sm text-slate-600">
                <p><span class="font-medium text-slate-800">{{ __('Phone detection') }}:</span> {{ $examProctoringControls['phone_detection_enabled'] ? __('On') : __('Off') }}</p>
                <p><span class="font-medium text-slate-800">{{ __('Fullscreen enforcement') }}:</span> {{ $examProctoringControls['fullscreen_enforced'] ? __('On') : __('Off') }}</p>
                <p><span class="font-medium text-slate-800">{{ __('Auto submit on severe violations') }}:</span> {{ $examProctoringControls['auto_submit_enabled'] ? __('On') : __('Off') }}</p>
            </div>
        @endif
    </section>

    {{-- Question overview --}}
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="q-overview-heading">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 id="q-overview-heading" class="text-sm font-semibold text-slate-900">{{ __('Question overview') }}</h2>
                <p class="mt-1 text-sm text-slate-600">
                    {{ __('Questions:') }} <strong class="font-semibold text-slate-900">{{ $poolQuestionTotal }}</strong>
                    {{ __('in this quiz (filter applies below).') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="downloadTxt(false)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">{{ __('Download questions TXT') }}</button>
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
                            <p class="mt-2 text-sm leading-relaxed text-slate-700"><span class="text-slate-500">{{ __('Answer') }}:</span> <span x-text="q.answer"></span></p>
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
                            class="shrink-0 self-start text-[11px] font-medium uppercase tracking-wide text-slate-400"
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
            <p class="mt-6 text-center text-sm text-slate-500">{{ __('No questions yet. Use Authoring below to import or generate.') }}</p>
        </template>
    </section>

    <div class="space-y-6">

    @error('lifecycle')
        <div class="mb-4 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-3 py-2 text-xs text-qs-danger">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->get('lifecycle') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @enderror

    <div x-show="!generationLocked" x-cloak id="builder-import-ai" class="scroll-mt-28 space-y-6" x-data="{ sourceMode: '{{ old('ai_custom_prompt') || old('ai_topic') || old('ai_count') ? 'ai' : 'json' }}' }">
        @if ($canEditContent)
        <div class="rounded-xl border border-qs-soft bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-qs-text mb-2">Question source</h3>
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
            <h3 class="text-sm font-semibold text-qs-text mb-2">External AI via JSON</h3>
            <p class="text-xs text-qs-muted mb-3">Enter topics and number of questions, generate a prompt template, run it in your external AI, then paste returned JSON below.</p>
            <form method="post" action="{{ route('examiner.exams.questions.ai.prompt', $exam) }}" class="grid gap-3 sm:grid-cols-2" x-data="topicTags(@js(old('ai_topic', '')))">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-xs text-qs-muted mb-1">Topics</label>
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
                        <input type="text" x-model="input" @keydown.enter.prevent="addFromInput()" @blur="addFromInput()" placeholder="{{ __('Separate topics with commas; use "quotes" if a topic contains a comma. Press Enter to add.') }}" class="w-full border-0 p-0 text-sm focus:outline-none focus:ring-0" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-qs-muted mb-1">Number of questions</label>
                    <input type="number" name="ai_count" value="{{ old('ai_count', 5) }}" min="1" max="50" required class="w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm" />
                </div>
                <input type="hidden" name="ai_marks" value="1" />
                <input type="hidden" name="ai_difficulty" value="undergraduate" />
                <input type="hidden" name="ai_question_types[]" value="mcq" />
                <div class="sm:col-span-2">
                    <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">Generate prompt template</button>
                </div>
            </form>
            @if (session('generated_ai_prompt'))
                <div class="mt-4">
                    <label class="block text-xs text-qs-muted mb-1">Prompt (copy or use below)</label>
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
                <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">Preview import</button>
            </form>
        </div>

        @if ($aiEnabled)
            <div x-show="sourceMode === 'ai'" x-cloak class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-qs-text mb-2">Generate with AI (internal)</h3>
                <p class="text-xs text-qs-muted mb-3">Uses encrypted API settings from System settings. Output is validated like pasted JSON before preview.</p>
                @error('ai')
                    <div class="mb-3 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-3 py-2 text-xs text-qs-danger whitespace-pre-line">{{ $message }}</div>
                @enderror
                <form method="post" action="{{ route('examiner.exams.questions.ai.generate', $exam) }}" class="space-y-3" x-data="topicTags(@js(old('ai_topic', '')))">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-qs-muted mb-1">Topics</label>
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
                                <input type="text" x-model="input" @keydown.enter.prevent="addFromInput()" @blur="addFromInput()" placeholder="{{ __('Separate topics with commas; use "quotes" if a topic contains a comma. Press Enter to add.') }}" class="w-full border-0 p-0 text-sm focus:outline-none focus:ring-0" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-qs-muted mb-1">Number of questions</label>
                            <input type="number" name="ai_count" value="{{ old('ai_count', 5) }}" min="1" max="50" class="w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm" />
                        </div>
                        <input type="hidden" name="ai_custom_prompt" value="" />
                        <input type="hidden" name="ai_marks" value="1" />
                        <input type="hidden" name="ai_difficulty" value="undergraduate" />
                        <input type="hidden" name="ai_question_types[]" value="mcq" />
                    </div>
                    <button type="submit" class="qs-btn-primary min-h-[44px] text-sm">Generate &amp; preview</button>
                </form>
            </div>
        @endif

        @endif

        @if ($importPreview && $canEditContent)
            <div class="rounded-xl border border-qs-accent/40 bg-qs-accent/10 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-qs-text">Import preview</h3>
                        <p class="text-xs text-qs-muted mt-1">Source: {{ $importPreview['source'] ?? 'paste' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="post" action="{{ route('examiner.exams.questions.import.commit', $exam) }}" class="inline">
                            @csrf
                            <button type="submit" class="qs-btn-primary min-h-[44px] text-sm">Save imported questions</button>
                        </form>
                        <form method="post" action="{{ route('examiner.exams.questions.import.cancel', $exam) }}" class="inline">
                            @csrf
                            <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">Cancel</button>
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

    <div x-show="!poolComplete" x-cloak id="builder-sections" class="scroll-mt-28 space-y-8">
    <div class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-qs-text mb-2">Question pool preview</h3>
        <p class="text-xs text-qs-muted">Sections and questions are created from imported/generate JSON. Approve ready questions so they can be used in randomized delivery.</p>
        @if ($exam->sections->isNotEmpty())
            <form method="post" action="{{ route('examiner.exams.questions.pool-status.bulk', $exam) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="pool_status" value="approved" />
                    <input type="hidden" name="mode" value="all" />
                    <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">Approve all questions</button>
                </div>
            </form>
            <p class="mt-2 text-xs text-qs-muted">Use checkboxes below to batch approve selected questions.</p>
        @endif
    </div>
    @if ($exam->sections->isNotEmpty())
        <form id="pool-batch-approve-selected" method="post" action="{{ route('examiner.exams.questions.pool-status.bulk', $exam) }}" class="rounded-xl border border-qs-soft bg-white p-4 shadow-sm">
            @csrf
            @method('PATCH')
            <input type="hidden" name="pool_status" value="approved" />
            <input type="hidden" name="mode" value="selected" />
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm">Approve selected</button>
                @error('pool_status')
                    <span class="text-xs text-qs-danger">{{ $message }}</span>
                @enderror
            </div>
        </form>
    @endif
    @forelse ($exam->sections as $section)
        <div class="mb-10 rounded-xl border border-qs-soft bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4 border-b border-qs-soft pb-3">
                <h3 class="text-lg font-semibold text-qs-text">{{ $section->title }}</h3>
                <span class="text-xs text-qs-muted">Order {{ $section->section_order }}</span>
            </div>

            @foreach ($section->questions as $q)
                <div class="mb-4 rounded-lg bg-white p-4 text-sm border border-qs-soft">
                    <div class="flex flex-wrap justify-between gap-2 items-start">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="question_ids[]" value="{{ $q->id }}" form="pool-batch-approve-selected" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                            <span class="font-medium text-qs-text">{{ $loop->iteration }}. {{ $q->type }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs uppercase tracking-wide text-qs-muted">{{ $q->pool_status }}</span>
                            <span class="text-qs-muted">{{ $q->marks }} pts</span>
                        </div>
                    </div>
                    @if ($canEditPool)
                        <form method="post" action="{{ route('examiner.exams.questions.pool-status', [$exam, $q]) }}" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            @csrf
                            @method('PATCH')
                            <label class="text-qs-muted">Pool status</label>
                            <select name="pool_status" class="min-h-[44px] rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm text-qs-text" onchange="this.form.submit()">
                                @foreach (['draft', 'approved', 'archived'] as $ps)
                                    <option value="{{ $ps }}" @selected($q->pool_status === $ps)>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <p class="mt-2 text-qs-text whitespace-pre-wrap">{{ $q->question_text }}</p>
                    @if ($q->isMCQ() && is_array($q->options))
                        <ul class="mt-2 list-disc list-inside text-qs-muted">
                            @foreach ($q->options as $idx => $opt)
                                <li>{{ $idx }}: {{ $opt }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

        </div>
    @empty
        <p class="rounded-xl border border-dashed border-qs-soft bg-qs-card px-4 py-8 text-center text-sm text-qs-muted">No questions yet. Use JSON or internal AI above to generate and preview questions first.</p>
    @endforelse
    </div>

    @if ($canEditDelivery)
        <div id="builder-delivery" class="scroll-mt-28 rounded-xl border border-qs-soft bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-qs-text mb-2">Randomized delivery</h3>
            <p class="text-xs text-qs-muted mb-3">Students only receive a subset of <strong class="text-qs-text">approved</strong> questions. Approve questions in the pool above, then set how many each student sees.</p>
            <p class="mb-2 text-xs text-qs-muted">{{ __('Approved in pool') }}: <strong class="text-qs-text">{{ $poolApprovedCount }}</strong></p>
            @error('questions_per_student')
                <div class="mb-2 text-xs text-qs-danger">{{ $message }}</div>
            @enderror
            @if ($poolApprovedCount < 1)
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {{ __('Approve at least one question first. Delivery settings become available immediately after approval.') }}
                </div>
            @endif
            <form method="post" action="{{ route('examiner.exams.delivery.update', $exam) }}" class="space-y-3 max-w-xl">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs text-qs-muted mb-1">Questions each student answers</label>
                    <input type="number" name="questions_per_student" value="{{ old('questions_per_student', $exam->questions_per_student ?? 1) }}" min="1" max="500" required class="w-full max-w-xs rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm min-h-[44px]" @disabled($poolApprovedCount < 1) />
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-qs-text">
                    <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 py-1">
                        <input type="checkbox" name="randomize_questions" value="1" class="size-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" @checked(old('randomize_questions', $exam->randomize_questions)) @disabled($poolApprovedCount < 1) />
                        Randomize which questions (and order)
                    </label>
                    <label class="inline-flex min-h-[44px] cursor-pointer items-center gap-2 py-1">
                        <input type="checkbox" name="randomize_options" value="1" class="size-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" @checked(old('randomize_options', $exam->randomize_options)) @disabled($poolApprovedCount < 1) />
                        Randomize MCQ option order
                    </label>
                </div>
                <button type="submit" class="qs-btn-secondary min-h-[44px] text-sm" @disabled($poolApprovedCount < 1)>Apply delivery options</button>
            </form>
        </div>
    @endif

    </div>
    </template>

    @if ($sessionsWorkspace)
        <template x-if="tab === 'sessions'">
            <div class="min-w-0">
                @include('examiner.exam_sessions.partials.workspace-sessions-panel', $sessionsWorkspace)
            </div>
        </template>
    @endif

    <template x-if="tab === 'scores'">
    <div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Scores & export') }}</h2>
        <p class="mt-1 max-w-prose text-sm leading-relaxed text-slate-600">{{ __('Review outcomes by class or open the full sessions list for this quiz.') }}</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('examiner.exams.classes.summary', $exam) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">{{ __('By class summary') }}</a>
            @if ($sessionsWorkspace)
                <button
                    type="button"
                    @click="syncWorkspaceTab('sessions')"
                    class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                >{{ __('All sessions') }}</button>
            @endif
        </div>
    </div>
    </template>

    <template x-if="tab === 'analytics'">
    <div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Question analytics') }}</h2>
        <p class="mt-1 max-w-prose text-sm leading-relaxed text-slate-600">{{ __('Counts by question type in this quiz.') }}</p>
        <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-slate-50/30">
            @forelse ($questionAnalytics as $type => $count)
                <li class="flex items-center justify-between px-4 py-3 text-sm">
                    <span class="font-medium uppercase tracking-wide text-slate-700">{{ str_replace('_', ' ', (string) $type) }}</span>
                    <span class="tabular-nums font-semibold text-slate-900">{{ $count }}</span>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No questions to analyze yet.') }}</li>
            @endforelse
        </ul>
    </div>
    </template>

    </div>

    <script>
        function splitCommaSeparatedRespectingQuotes(str) {
            const s = String(str || '').trim();
            if (!s) {
                return [];
            }
            const parts = [];
            let cur = '';
            let inD = false;
            let inS = false;
            for (let i = 0; i < s.length; i++) {
                const c = s[i];
                const prev = i > 0 ? s[i - 1] : '';
                if (inD) {
                    if (c === '"' && prev !== '\\') {
                        inD = false;
                    } else {
                        cur += c;
                    }
                    continue;
                }
                if (inS) {
                    if (c === "'" && prev !== '\\') {
                        inS = false;
                    } else {
                        cur += c;
                    }
                    continue;
                }
                if (c === '"') {
                    inD = true;
                    continue;
                }
                if (c === "'") {
                    inS = true;
                    continue;
                }
                if (c === ',') {
                    if (cur.trim()) {
                        parts.push(cur.trim());
                    }
                    cur = '';
                    continue;
                }
                cur += c;
            }
            if (cur.trim()) {
                parts.push(cur.trim());
            }
            return parts.filter((p) => p.length > 0);
        }

        function topicTags(initial) {
            function parseInitial(raw) {
                if (raw == null) {
                    return [];
                }
                const s = String(raw).trim();
                if (!s) {
                    return [];
                }
                if (s.startsWith('[')) {
                    try {
                        const j = JSON.parse(s);
                        if (Array.isArray(j)) {
                            return [
                                ...new Set(
                                    j.filter((x) => typeof x === 'string').map((t) => t.trim()).filter((t) => t.length > 0),
                                ),
                            ];
                        }
                    } catch (e) {}
                }
                return [...new Set(splitCommaSeparatedRespectingQuotes(s))];
            }
            return {
                tags: parseInitial(initial),
                input: '',
                topicChipClass(idx) {
                    const palettes = [
                        'border-qs-primary/35 bg-qs-primary/10 text-qs-primary',
                        'border-emerald-400/45 bg-emerald-50 text-emerald-900',
                        'border-violet-400/45 bg-violet-50 text-violet-900',
                        'border-amber-400/45 bg-amber-50 text-amber-950',
                        'border-sky-400/45 bg-sky-50 text-sky-950',
                    ];
                    return palettes[idx % palettes.length] + ' px-2 py-0.5';
                },
                topicChipCloseClass(idx) {
                    const muted = [
                        'text-qs-primary/80',
                        'text-emerald-900/70',
                        'text-violet-900/70',
                        'text-amber-950/70',
                        'text-sky-950/70',
                    ];
                    return muted[idx % muted.length];
                },
                get joined() {
                    return JSON.stringify(this.tags);
                },
                addFromInput() {
                    const v = String(this.input || '').trim();
                    if (v === '') {
                        return;
                    }
                    const parts = splitCommaSeparatedRespectingQuotes(v);
                    parts.forEach((p) => {
                        if (p && !this.tags.includes(p)) {
                            this.tags.push(p);
                        }
                    });
                    this.input = '';
                },
                remove(idx) {
                    this.tags.splice(idx, 1);
                },
            };
        }

    </script>
</x-layouts.examiner>
