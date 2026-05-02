<x-layouts.coordinator>
    <x-slot name="title">Exam builder</x-slot>
    <x-slot name="subtitle">{{ $exam->title }}</x-slot>

    <div class="mb-6 flex flex-wrap gap-3 text-sm text-qs-muted">
        <span>Course: <strong class="text-qs-text">{{ $exam->course?->code }}</strong></span>
        <span>Duration: <strong class="text-qs-text">{{ $exam->duration_minutes }} min</strong></span>
        <span>Total marks: <strong class="text-qs-text">{{ $exam->total_marks }}</strong></span>
    </div>

    <div class="mb-8 space-y-6">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-qs-text mb-2">Import questions (JSON)</h3>
            <p class="text-xs text-qs-muted mb-3">Paste JSON with a top-level <code class="text-qs-text">sections</code> array. Preview validates structure before anything is saved.</p>
            @error('import_json')
                <div class="mb-3 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-3 py-2 text-xs text-qs-danger whitespace-pre-line">{{ $message }}</div>
            @enderror
            <form method="post" action="{{ route('examiner.exams.questions.import.preview', $exam) }}" class="space-y-3">
                @csrf
                <textarea name="import_json" rows="8" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm font-mono text-qs-text" placeholder='{"sections":[{"title":"Section A","questions":[...]}]}'>{{ old('import_json') }}</textarea>
                <button type="submit" class="qs-btn-secondary text-sm">Preview import</button>
            </form>
        </div>

        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-qs-text mb-2">Generate AI prompt</h3>
            <p class="text-xs text-qs-muted mb-3">Build a strict JSON-schema prompt you can paste into an external LLM, or into “Custom prompt” below for internal generation.</p>
            <form method="post" action="{{ route('examiner.exams.questions.ai.prompt', $exam) }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-xs text-qs-muted mb-1">Topic</label>
                    <input type="text" name="ai_topic" value="{{ old('ai_topic') }}" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-qs-muted mb-1">Number of questions</label>
                    <input type="number" name="ai_count" value="{{ old('ai_count', 5) }}" min="1" max="50" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-qs-muted mb-1">Marks per question</label>
                    <input type="number" name="ai_marks" value="{{ old('ai_marks', 1) }}" step="0.01" min="0" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-qs-muted mb-1">Question types</label>
                    <div class="flex flex-wrap gap-3 text-sm text-qs-text">
                        @foreach (['mcq', 'true_false', 'fill_blank', 'essay'] as $t)
                            <label class="inline-flex items-center gap-1">
                                <input type="checkbox" name="ai_question_types[]" value="{{ $t }}" @checked(in_array($t, old('ai_question_types', ['mcq']), true)) />
                                {{ $t }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-qs-muted mb-1">Difficulty</label>
                    <input type="text" name="ai_difficulty" value="{{ old('ai_difficulty', 'undergraduate') }}" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="qs-btn-secondary text-sm">Generate prompt template</button>
                </div>
            </form>
            @if (session('generated_ai_prompt'))
                <div class="mt-4">
                    <label class="block text-xs text-qs-muted mb-1">Prompt (copy or use below)</label>
                    <textarea readonly rows="10" class="w-full rounded-lg border border-qs-soft bg-qs-card px-3 py-2 text-xs font-mono text-qs-text">{{ session('generated_ai_prompt') }}</textarea>
                </div>
            @endif
        </div>

        @if ($aiEnabled)
            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-qs-text mb-2">Generate with AI (internal)</h3>
                <p class="text-xs text-qs-muted mb-3">Uses encrypted API settings from System settings. Output is validated like pasted JSON before preview.</p>
                @error('ai')
                    <div class="mb-3 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-3 py-2 text-xs text-qs-danger whitespace-pre-line">{{ $message }}</div>
                @enderror
                <form method="post" action="{{ route('examiner.exams.questions.ai.generate', $exam) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-qs-muted mb-1">Custom prompt (optional)</label>
                        <textarea name="ai_custom_prompt" rows="6" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm font-mono text-qs-text" placeholder="Paste a full prompt requesting QUIZSNAP JSON…">{{ old('ai_custom_prompt') }}</textarea>
                    </div>
                    <p class="text-xs text-qs-muted">Or leave custom prompt empty and fill the fields below.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-qs-muted mb-1">Topic</label>
                            <input type="text" name="ai_topic" value="{{ old('ai_topic') }}" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-qs-muted mb-1">Number of questions</label>
                            <input type="number" name="ai_count" value="{{ old('ai_count', 5) }}" min="1" max="50" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs text-qs-muted mb-1">Marks per question</label>
                            <input type="number" name="ai_marks" value="{{ old('ai_marks', 1) }}" step="0.01" min="0" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                        </div>
                        <div class="sm:col-span-2 flex flex-wrap gap-3 text-sm text-qs-text">
                            @foreach (['mcq', 'true_false', 'fill_blank', 'essay'] as $t)
                                <label class="inline-flex items-center gap-1">
                                    <input type="checkbox" name="ai_question_types[]" value="{{ $t }}" @checked(in_array($t, old('ai_question_types', ['mcq']), true)) />
                                    {{ $t }}
                                </label>
                            @endforeach
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-qs-muted mb-1">Difficulty</label>
                            <input type="text" name="ai_difficulty" value="{{ old('ai_difficulty', 'undergraduate') }}" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                        </div>
                    </div>
                    <button type="submit" class="qs-btn-primary text-sm">Generate &amp; preview</button>
                </form>
            </div>
        @endif

        @if ($importPreview)
            <div class="rounded-xl border border-qs-accent/40 bg-qs-accent/10 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-qs-text">Import preview</h3>
                        <p class="text-xs text-qs-muted mt-1">Source: {{ $importPreview['source'] ?? 'paste' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="post" action="{{ route('examiner.exams.questions.import.commit', $exam) }}" class="inline">
                            @csrf
                            <button type="submit" class="qs-btn-primary text-sm">Save imported questions</button>
                        </form>
                        <form method="post" action="{{ route('examiner.exams.questions.import.cancel', $exam) }}" class="inline">
                            @csrf
                            <button type="submit" class="qs-btn-secondary text-sm">Cancel</button>
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

    <div class="mb-8 rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-qs-text mb-3">Add section</h3>
        <form method="post" action="{{ route('examiner.exams.sections.store', $exam) }}" class="flex flex-wrap gap-2 items-end">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-qs-muted mb-1">Section title</label>
                <input type="text" name="title" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" placeholder="e.g. Section A" />
            </div>
            <button type="submit" class="qs-btn-primary">Add section</button>
        </form>
    </div>

    @forelse ($exam->sections as $section)
        <div class="mb-10 rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4 border-b border-qs-soft pb-3">
                <h3 class="text-lg font-semibold text-qs-text">{{ $section->title }}</h3>
                <span class="text-xs text-qs-muted">Order {{ $section->section_order }}</span>
            </div>

            @foreach ($section->questions as $q)
                <div class="mb-4 rounded-lg bg-qs-card p-4 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="font-medium text-qs-text">{{ $loop->iteration }}. {{ $q->type }}</span>
                        <span class="text-qs-muted">{{ $q->marks }} pts</span>
                    </div>
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

            <div class="mt-4 border-t border-qs-soft pt-4">
                <h4 class="text-sm font-semibold text-qs-text mb-3">New question in this section</h4>
                <form method="post" action="{{ route('examiner.exams.questions.store', [$exam, $section]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-qs-muted mb-1">Type</label>
                        <select name="type" required class="qs-qtype w-full rounded-lg border border-qs-soft px-3 py-2 text-sm">
                            @foreach ($questionTypes as $qt)
                                <option value="{{ $qt }}">{{ $qt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-qs-muted mb-1">Question text</label>
                        <textarea name="question_text" rows="3" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs text-qs-muted mb-1">Marks</label>
                        <input type="number" name="marks" value="1" step="0.01" min="0" required class="w-32 rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                    </div>

                    <div class="qs-block qs-mcq space-y-2 border border-dashed border-qs-soft rounded-lg p-3">
                        <p class="text-xs text-qs-muted">MCQ: enter options, tick correct answer(s).</p>
                        @for ($i = 0; $i < 6; $i++)
                            <div class="flex gap-2 items-center">
                                <span class="text-xs w-6 text-qs-muted">{{ $i }}</span>
                                <input type="text" name="options[]" class="flex-1 rounded border border-qs-soft px-2 py-1 text-sm" placeholder="Option {{ $i }}" />
                                <label class="text-xs flex items-center gap-1 whitespace-nowrap">
                                    <input type="checkbox" name="correct_mcq[]" value="{{ $i }}" /> correct
                                </label>
                            </div>
                        @endfor
                    </div>

                    <div class="qs-block qs-tf hidden space-y-2 border border-dashed border-qs-soft rounded-lg p-3">
                        <p class="text-xs text-qs-muted">Correct answer</p>
                        <select name="correct_true_false" class="rounded-lg border border-qs-soft px-3 py-2 text-sm">
                            <option value="1">True</option>
                            <option value="0">False</option>
                        </select>
                    </div>

                    <div class="qs-block qs-fb hidden space-y-2 border border-dashed border-qs-soft rounded-lg p-3">
                        <p class="text-xs text-qs-muted">Acceptable answers (one per line, matched in order for multiple blanks).</p>
                        <textarea name="correct_blanks" rows="3" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" placeholder="Answer line 1&#10;Answer line 2"></textarea>
                    </div>

                    <div class="qs-block qs-essay hidden border border-dashed border-qs-soft rounded-lg p-3 text-xs text-qs-muted">
                        Essay questions are graded manually after submission.
                    </div>

                    <button type="submit" class="rounded-lg bg-qs-accent px-4 py-2 text-sm font-semibold text-qs-text hover:opacity-95">Save question</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-sm text-qs-muted">Add at least one section, then add questions per section.</p>
    @endforelse

    <div class="mt-8">
        <a href="{{ route('examiner.exams.index') }}" class="text-sm font-medium text-qs-text hover:underline">← Back to exams</a>
    </div>

    <script>
        document.querySelectorAll('form').forEach(function (form) {
            var sel = form.querySelector('.qs-qtype');
            if (!sel) return;
            function sync() {
                var t = sel.value;
                form.querySelectorAll('.qs-block').forEach(function (el) { el.classList.add('hidden'); });
                if (t === 'mcq') form.querySelector('.qs-mcq')?.classList.remove('hidden');
                if (t === 'true_false') form.querySelector('.qs-tf')?.classList.remove('hidden');
                if (t === 'fill_blank') form.querySelector('.qs-fb')?.classList.remove('hidden');
                if (t === 'essay') form.querySelector('.qs-essay')?.classList.remove('hidden');
            }
            sel.addEventListener('change', sync);
            sync();
        });
    </script>
</x-layouts.coordinator>
