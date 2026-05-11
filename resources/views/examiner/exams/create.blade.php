@php($staffAcademicPeriodBadge = null)
<x-layouts.examiner>
    <x-slot name="title">{{ __('Create assessment') }}</x-slot>
    <x-slot name="subtitle">{{ __('Choose class groups, optional JSON import with a prompt helper, in-app AI generation, then scheduling.') }}</x-slot>

    <div
        class="w-full max-w-none space-y-6 rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm sm:p-6 lg:p-8"
        x-data="qsExamCreateForm(@js($examCreateAlpine))"
    >
        @if ($errors->any())
            <div class="rounded-xl border border-qs-danger/35 bg-qs-danger-soft px-4 py-3 text-sm text-qs-danger">
                <ul class="list-disc ps-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (count($classroomOptions) === 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ __('No class groups are linked to your courses yet. Ask a coordinator to assign classes to your courses, then return here.') }}
            </div>
        @endif

        <form method="post" action="{{ route('examiner.exams.store') }}" enctype="multipart/form-data" class="space-y-8" @submit="syncClassroomInputs()">
            @csrf

            <section class="space-y-4" aria-labelledby="ec-general">
                <h2 id="ec-general" class="text-sm font-semibold text-qs-text">{{ __('General') }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Title') }} <span class="text-qs-danger">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="qs-input mt-1 w-full py-2.5" placeholder="{{ __('e.g. Midterm Exam — March') }}" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Assessment type') }} <span class="text-qs-danger">*</span></label>
                    <select name="assessment_type" required class="qs-input mt-1 w-full py-2.5">
                        @foreach (['quiz' => __('Quiz'), 'mid' => __('Midterm'), 'exam' => __('End of semester'), 'assignment' => __('Assignment')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('assessment_type', 'quiz') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-qs-muted">{{ __('Shown on reports and student-facing labels.') }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Course') }} <span class="text-qs-danger">*</span></label>
                    <select name="course_id" x-model="courseId" required class="qs-input mt-1 w-full py-2.5">
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((int) old('course_id', request('course_id')) === (int) $course->id)>{{ $course->code }} — {{ $course->title }}</option>
                        @endforeach
                    </select>
                </div>
                <fieldset>
                    <legend class="mb-2 block text-sm font-medium text-qs-muted">{{ __('Class groups') }} <span class="text-qs-danger">*</span></legend>
                    <p class="mb-2 text-xs text-qs-muted">{{ __('Select all groups that should see this quiz when it is published. Each group must be enrolled in the course above.') }}</p>
                    <div class="max-h-52 space-y-2 overflow-y-auto rounded-lg border border-qs-soft bg-white p-3">
                        <template x-for="row in filteredClassrooms()" :key="row.id">
                            <label class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50">
                                <input type="checkbox" :value="row.id" x-model="selectedClassIds" class="mt-1 size-4 rounded border-qs-soft text-qs-accent" />
                                <span class="text-sm text-qs-text" x-text="row.label"></span>
                            </label>
                        </template>
                        <p x-show="filteredClassrooms().length === 0" class="text-sm text-qs-muted">{{ __('No class groups for this course.') }}</p>
                    </div>
                    <div id="classroom-ids-mount"></div>
                </fieldset>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Description (optional)') }}</label>
                    <textarea name="description" rows="3" class="qs-input mt-1 w-full py-2.5">{{ old('description') }}</textarea>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="ec-pool">
                <h2 id="ec-pool" class="text-sm font-semibold text-qs-text">{{ __('Question pool & delivery') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Duration (minutes)') }} <span class="text-qs-danger">*</span></label>
                        <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 30) }}" min="1" max="600" required class="qs-input mt-1 w-full py-2.5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Questions per student') }}</label>
                        <input type="number" name="questions_per_student" value="{{ old('questions_per_student', 10) }}" min="1" max="500" class="qs-input mt-1 w-full py-2.5" :required="source === 'paste_json' || source === 'ai_generate'" />
                        <p class="mt-1 text-xs text-qs-muted">{{ __('Required when importing JSON or using in-app AI. Each student draws this many from the approved pool.') }}</p>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border border-qs-soft bg-white px-4 py-3">
                        <input type="hidden" name="randomize_questions" value="0" />
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="randomize_questions" value="1" class="mt-0.5 size-4 shrink-0 rounded border-qs-soft text-qs-accent" @checked(old('randomize_questions', true)) />
                            <span>
                                <span class="block text-sm font-medium text-qs-text">{{ __('Randomize question order') }}</span>
                                <span class="mt-0.5 block text-xs text-qs-muted">{{ __('Each student may see questions in a different sequence.') }}</span>
                            </span>
                        </label>
                    </div>
                    <div class="rounded-lg border border-qs-soft bg-white px-4 py-3">
                        <input type="hidden" name="randomize_options" value="0" />
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="randomize_options" value="1" class="mt-0.5 size-4 shrink-0 rounded border-qs-soft text-qs-accent" @checked(old('randomize_options', true)) />
                            <span>
                                <span class="block text-sm font-medium text-qs-text">{{ __('Randomize MCQ options') }}</span>
                                <span class="mt-0.5 block text-xs text-qs-muted">{{ __('Shuffle answer choices for multiple-choice items.') }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="ec-source">
                <h2 id="ec-source" class="text-sm font-semibold text-qs-text">{{ __('Questions') }}</h2>
                <p class="text-xs leading-snug text-qs-muted">{{ __('Save as a draft and build later, import JSON with a live prompt helper, or generate questions in-app when AI is enabled.') }}</p>
                <input type="hidden" name="question_source" :value="source" />
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="source === 'later' ? 'border-qs-accent bg-qs-accent/10' : 'border-qs-soft bg-white'">
                        <input type="radio" value="later" x-model="source" class="size-4 border-qs-soft text-qs-accent" />
                        <span class="font-medium text-qs-text">{{ __('Later') }}</span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="source === 'paste_json' ? 'border-qs-accent bg-qs-accent/10' : 'border-qs-soft bg-white'">
                        <input type="radio" value="paste_json" x-model="source" class="size-4 border-qs-soft text-qs-accent" />
                        <span class="font-medium text-qs-text">{{ __('Import JSON') }}</span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="source === 'ai_generate' ? 'border-qs-accent bg-qs-accent/10' : 'border-qs-soft bg-white'">
                        <input type="radio" value="ai_generate" x-model="source" class="size-4 border-qs-soft text-qs-accent" @disabled(! $aiEnabled) />
                        <span class="font-medium text-qs-text">{{ __('Generate with AI') }}</span>
                    </label>
                </div>
                @if (! $aiEnabled)
                    <p class="text-xs text-qs-muted">{{ __('In-app AI generation is turned off for your institution. You can still import JSON.') }}</p>
                @endif

                {{-- JSON import path: prompt + paste + validate --}}
                <div x-show="source === 'paste_json'" x-cloak class="space-y-6">
                    <div class="space-y-3 rounded-lg border border-qs-soft bg-white p-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-qs-muted" for="paste-prompt-topics">{{ __('Topics (one line or short list)') }}</label>
                            <textarea
                                id="paste-prompt-topics"
                                name="paste_prompt_topics"
                                rows="2"
                                x-model="pastePromptTopics"
                                class="qs-input mt-1 w-full py-2.5 text-sm"
                                placeholder="{{ __('e.g. General knowledge; cell biology; week 3 readings') }}"
                            ></textarea>
                        </div>
                        <div class="max-w-xs">
                            <label class="mb-1 block text-sm font-medium text-qs-muted" for="paste-prompt-count">{{ __('Number of questions') }}</label>
                            <input
                                id="paste-prompt-count"
                                type="number"
                                name="paste_prompt_count"
                                min="1"
                                max="250"
                                x-model.number="pastePromptCount"
                                class="qs-input mt-1 w-full py-2.5"
                            />
                        </div>
                        <p class="text-xs leading-relaxed text-qs-muted">
                            {{ __('Add topics and number of questions above. The box below updates automatically — click the box or “Copy prompt” to copy, then run it in your own tool. Paste the returned JSON in the next section.') }}
                        </p>
                        <div>
                            <textarea
                                readonly
                                rows="12"
                                class="w-full cursor-text rounded-lg border border-qs-soft bg-qs-card px-3 py-2 font-mono text-xs text-qs-text"
                                :value="buildExternalMcqPrompt()"
                                @click="$el.select()"
                                @focus="$el.select()"
                            ></textarea>
                        </div>
                        <button type="button" class="qs-btn-secondary inline-flex min-h-[40px] items-center gap-2 px-4 text-sm font-semibold" @click="copyExternalPrompt()">
                            <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                            <span>{{ __('Copy prompt') }}</span>
                        </button>
                        <p x-show="copyPromptHint" x-transition class="text-xs text-qs-primary" x-text="copyPromptHint"></p>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold text-qs-text">{{ __('Paste JSON') }}</h3>
                        <p class="text-xs text-qs-muted">{{ __('Paste the JSON from your generator here, then click Validate.') }}</p>
                        <textarea
                            id="import-json-field"
                            name="import_json"
                            rows="14"
                            x-model="importJsonDraft"
                            class="w-full rounded-lg border border-qs-soft bg-white px-3 py-2 font-mono text-xs text-qs-text placeholder:text-qs-muted"
                            placeholder='[{"text":"Question?","options":{"A":"...","B":"...","C":"...","D":"..."},"correct":"A","topic":"..."}]'
                        ></textarea>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" class="inline-flex min-h-[40px] items-center rounded-lg bg-qs-text px-4 text-sm font-semibold text-white hover:opacity-95" @click="validateImportJson()">
                                {{ __('Validate JSON') }}
                            </button>
                            <span x-show="importValidateBusy" class="text-xs text-qs-muted">{{ __('Checking…') }}</span>
                        </div>
                        <div x-show="importValidateMessage" class="rounded-lg border px-3 py-2 text-sm" :class="importValidateOk ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-qs-danger/35 bg-qs-danger-soft text-qs-danger'" x-text="importValidateMessage"></div>
                    </div>
                </div>

                {{-- In-app AI --}}
                <div x-show="source === 'ai_generate'" x-cloak class="space-y-4 rounded-lg border border-qs-soft bg-white p-4">
                    <p class="text-xs text-qs-muted">{{ __('Topics are sent to the configured model. You can optionally attach an outline (PDF, TXT, or DOCX). Defaults: 10 questions, MCQ, moderate difficulty, 1 mark each.') }}</p>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-qs-muted" for="ai-topics-create">{{ __('Topics') }}</label>
                        <textarea id="ai-topics-create" name="ai_topics" rows="4" class="qs-input mt-1 w-full py-2.5 text-sm" placeholder="{{ __('What should the questions cover?') }}">{{ old('ai_topics') }}</textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-qs-muted" for="ai-count-create">{{ __('Number of questions') }} <span class="text-qs-danger">*</span></label>
                            <input id="ai-count-create" type="number" name="ai_question_count" value="{{ old('ai_question_count', 10) }}" min="1" max="250" required class="qs-input mt-1 w-full py-2.5" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-qs-muted" for="ai-outline-create">{{ __('Outline file (optional)') }}</label>
                            <input id="ai-outline-create" type="file" name="ai_outline_file" accept=".pdf,.txt,.docx,application/pdf,text/plain,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="mt-1 block w-full text-sm text-qs-muted file:me-3 file:rounded-lg file:border-0 file:bg-qs-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-qs-text" />
                        </div>
                    </div>
                    <input type="hidden" name="ai_question_types[]" value="mcq" />
                    <input type="hidden" name="ai_difficulty" value="moderate" />
                    <input type="hidden" name="ai_marks" value="1" />
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="ec-schedule">
                <h2 id="ec-schedule" class="text-sm font-semibold text-qs-text">{{ __('Scheduling') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Opens at (optional)') }}</label>
                        <input type="datetime-local" name="start_time" value="{{ old('start_time') }}" class="qs-input mt-1 w-full py-2.5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Closes at (optional)') }}</label>
                        <input type="datetime-local" name="end_time" value="{{ old('end_time') }}" class="qs-input mt-1 w-full py-2.5" />
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-qs-text">
                    <input type="checkbox" name="activate_now" value="1" class="size-4 rounded border-qs-soft text-qs-accent" @checked(old('activate_now')) />
                    {{ __('Publish immediately if validation passes') }}
                </label>
                <p class="text-xs text-qs-muted">{{ __('Requires an approved question pool and delivery settings. If anything is missing, the assessment is saved as a draft.') }}</p>
                <label class="inline-flex items-center gap-2 text-sm text-qs-text">
                    <input type="checkbox" name="show_correct_answers_in_results" value="1" class="size-4 rounded border-qs-soft text-qs-accent" @checked(old('show_correct_answers_in_results')) />
                    {{ __('Allow students to see correct answers in results (when your school policy permits)') }}
                </label>
            </section>

            <div class="flex flex-wrap gap-3 border-t border-qs-soft pt-4">
                <button type="submit" class="qs-btn-primary min-h-[44px] px-5 text-sm font-semibold" @if (count($classroomOptions) === 0) disabled @endif>
                    {{ __('Create assessment') }}
                </button>
                <a href="{{ route('examiner.exams.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-qs-soft px-4 text-sm font-semibold text-qs-muted hover:bg-qs-card">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function qsExamCreateForm(cfg) {
                return {
                    rows: cfg.rows,
                    courseId: cfg.courseId,
                    selectedClassIds: cfg.selectedClassIds,
                    source: cfg.source,
                    pastePromptTopics: cfg.pastePromptTopics,
                    pastePromptCount: cfg.pastePromptCount,
                    importJsonDraft: cfg.importJsonDraft,
                    validateImportUrl: cfg.validateImportUrl,
                    csrfToken: cfg.csrfToken,
                    importValidateBusy: false,
                    importValidateOk: false,
                    importValidateMessage: '',
                    copyPromptHint: '',
                    filteredClassrooms() {
                        const cid = parseInt(this.courseId, 10) || 0;
                        if (!cid) {
                            return [];
                        }
                        return this.rows.filter((r) => (r.course_ids || []).includes(cid));
                    },
                    syncClassroomInputs() {
                        const mount = document.getElementById('classroom-ids-mount');
                        if (!mount) {
                            return;
                        }
                        mount.innerHTML = '';
                        this.selectedClassIds.forEach((id) => {
                            const inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'classroom_ids[]';
                            inp.value = String(id);
                            mount.appendChild(inp);
                        });
                    },
                    buildExternalMcqPrompt() {
                        const topics = String(this.pastePromptTopics || '').trim() || 'General knowledge';
                        let n = parseInt(this.pastePromptCount, 10);
                        if (Number.isNaN(n) || n < 1) {
                            n = 10;
                        }
                        n = Math.min(250, n);
                        const example =
                            '[{"text":"Your question here?","options":{"A":"...","B":"...","C":"...","D":"..."},"correct":"A","topic":"..."}]';
                        return [
                            'Use ONLY these precise topics—do not add or substitute others: ' + topics + '.',
                            'Generate exactly ' +
                                n +
                                ' multiple choice quiz questions (MCQ) that clearly align with these topics. Difficulty: moderate.',
                            'For each question provide: question text, exactly 4 options (A, B, C, D), and exactly one correct answer (one letter). Do not include explanations.',
                            'Output format: reply with a JSON array only, no other text before or after.',
                            'Each item in the array must have: "text" (question text), "options" (object with keys "A", "B", "C", "D"), "correct" (one letter A–D), "topic" (one of the listed topics).',
                            'Example shape: ' + example,
                        ].join('\n');
                    },
                    copyExternalPrompt() {
                        const text = this.buildExternalMcqPrompt();
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(text).then(() => {
                                this.copyPromptHint = @json(__('Copied.'));
                                window.setTimeout(() => {
                                    this.copyPromptHint = '';
                                }, 2000);
                            });
                        } else {
                            this.copyPromptHint = @json(__('Select the prompt box and copy manually (clipboard needs HTTPS).'));
                        }
                    },
                    async validateImportJson() {
                        this.importValidateMessage = '';
                        this.importValidateBusy = true;
                        try {
                            const body = new FormData();
                            body.append('_token', this.csrfToken);
                            body.append('import_json', this.importJsonDraft || '');
                            const res = await fetch(this.validateImportUrl, {
                                method: 'POST',
                                body,
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin',
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.ok) {
                                this.importValidateOk = true;
                                this.importValidateMessage = data.message || @json(__('JSON is valid.'));
                            } else {
                                this.importValidateOk = false;
                                const errs = Array.isArray(data.errors) ? data.errors : [];
                                this.importValidateMessage =
                                    errs.length > 0 ? errs.join('\n') : data.message || @json(__('Validation failed.'));
                            }
                        } catch (e) {
                            this.importValidateOk = false;
                            this.importValidateMessage = @json(__('Could not reach the server. Try again.'));
                        } finally {
                            this.importValidateBusy = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-layouts.examiner>
