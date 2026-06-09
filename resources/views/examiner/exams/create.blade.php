<x-layouts.examiner>
    <x-slot name="title">{{ __('Create assessment') }}</x-slot>
    <x-slot name="subtitle">{{ __('Set up your assessment, then continue in the builder to review or publish.') }}</x-slot>

    <div
        class="w-full max-w-none space-y-6 rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm sm:p-6 lg:p-8"
        x-data="qsExamCreateForm(@js($examCreateAlpine))"
    >
        <p class="text-sm text-qs-muted">
            <span x-show="assessmentType !== 'assignment'">{{ __('Choose class groups, optional JSON import with a prompt helper, in-app AI generation, then scheduling.') }}</span>
            <span x-show="assessmentType === 'assignment'" x-cloak>{{ __('Write the essay question and instructions below, pick class groups and a due date, then save.') }}</span>
        </p>
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

        <form id="exam-create-form" method="post" action="{{ route('examiner.exams.store') }}" enctype="multipart/form-data" class="space-y-8" @submit.prevent="handleFormSubmit($event)">
            @csrf
            <input type="hidden" name="wizard_step" :value="wizardStep" />

            <div x-show="wizardStep === 1" class="space-y-8">
            <section class="space-y-4" aria-labelledby="ec-general">
                <h2 id="ec-general" class="text-sm font-semibold text-qs-text">{{ __('General') }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Title') }} <span class="text-qs-danger">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="qs-input mt-1 w-full py-2.5" placeholder="{{ __('e.g. Midterm Exam — March') }}" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Assessment type') }} <span class="text-qs-danger">*</span></label>
                    <select name="assessment_type" x-model="assessmentType" required class="qs-input mt-1 w-full py-2.5">
                        @foreach (['quiz' => __('Quiz'), 'mid' => __('Midterm'), 'exam' => __('End of semester'), 'assignment' => __('Assignment')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('assessment_type', 'quiz') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-qs-muted">{{ __('Shown on reports and student-facing labels.') }}</p>
                </div>
                <fieldset x-show="assessmentType !== 'assignment'" x-cloak class="space-y-2" aria-labelledby="ec-qtypes">
                    <legend id="ec-qtypes" class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Question types in pool') }} <span class="text-qs-danger">*</span></legend>
                    <p class="text-xs text-qs-muted">{{ __('Only these types can be added, imported, or AI-generated for this assessment. The per-type counters and JSON validation react to this selection in real time.') }}</p>
                    @php
                        // Essay is assignment-only. Quizzes/midterms/exams never
                        // expose it as an option (a hidden input below still
                        // forces essay for assignments).
                        $qtDefaults = ['mcq', 'true_false', 'fill_blank'];
                        $qtOld = old('selected_question_types', $qtDefaults);
                        $qtOld = is_array($qtOld) ? array_values(array_filter($qtOld, static fn ($t) => $t !== 'essay')) : $qtDefaults;
                        $qtLabels = [
                            'mcq' => ['label' => __('Multiple choice'), 'icon' => 'fa-list-ul'],
                            'true_false' => ['label' => __('True/False'), 'icon' => 'fa-toggle-on'],
                            'fill_blank' => ['label' => __('Fill-in-the-blank'), 'icon' => 'fa-i-cursor'],
                        ];
                    @endphp
                    {{-- Card-style toggle. The checkbox is bound to the
                         Alpine `selectedQuestionTypes` array via x-model so
                         every downstream UI (per-type counters, validator,
                         pool indicator) reacts immediately to a toggle. --}}
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($qtLabels as $value => $meta)
                            <label
                                class="group relative flex cursor-pointer items-center gap-3 rounded-xl border bg-white px-3.5 py-2.5 text-sm transition-all"
                                :class="(selectedQuestionTypes || []).includes('{{ $value }}') ? 'border-qs-accent bg-qs-accent/[0.06] shadow-sm ring-1 ring-inset ring-qs-accent/30' : 'border-qs-soft hover:border-qs-accent/40 hover:bg-slate-50/50'"
                            >
                                <input
                                    type="checkbox"
                                    name="selected_question_types[]"
                                    value="{{ $value }}"
                                    class="qs-qtype-cb sr-only"
                                    :disabled="assessmentType === 'assignment'"
                                    x-model="selectedQuestionTypes"
                                />
                                <span
                                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors"
                                    :class="(selectedQuestionTypes || []).includes('{{ $value }}') ? 'bg-qs-accent text-white' : 'bg-slate-100 text-slate-500'"
                                    aria-hidden="true"
                                >
                                    <i class="fa-solid {{ $meta['icon'] }} text-[12px]"></i>
                                </span>
                                <span class="min-w-0 flex-1 font-medium text-qs-text">{{ $meta['label'] }}</span>
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full transition"
                                    :class="(selectedQuestionTypes || []).includes('{{ $value }}') ? 'bg-qs-accent text-white' : 'bg-slate-100 text-slate-300 group-hover:bg-slate-200'"
                                    aria-hidden="true"
                                >
                                    <i class="fa-solid fa-check text-[10px]" x-show="(selectedQuestionTypes || []).includes('{{ $value }}')" x-cloak></i>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p x-show="(selectedQuestionTypes || []).length === 0" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ __('Pick at least one question type so the assessment can collect or generate questions.') }}
                    </p>
                </fieldset>
                <template x-if="assessmentType === 'assignment'">
                    <input type="hidden" name="selected_question_types[]" value="essay" />
                </template>
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
                    <p class="mb-2 text-xs text-qs-muted">
                        <span x-show="assessmentType !== 'assignment'">{{ __('Which student groups receive this assessment. Each group must be enrolled in the course above.') }}</span>
                        <span x-show="assessmentType === 'assignment'" x-cloak>{{ __('Which student groups can submit this assignment. Pick at least one group linked to the course above.') }}</span>
                    </p>
                    <div
                        class="max-h-52 space-y-2 overflow-y-auto rounded-lg border p-3"
                        :class="filteredClassrooms().length === 0 ? 'border-amber-200 bg-amber-50/50' : 'border-qs-soft bg-white'"
                    >
                        <template x-for="row in filteredClassrooms()" :key="row.id">
                            <label class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50">
                                <input
                                    type="checkbox"
                                    class="mt-1 size-4 rounded border-qs-soft text-qs-accent"
                                    :checked="isClassroomSelected(row.id)"
                                    @change="toggleClassroom(row.id, $event.target.checked)"
                                />
                                <span class="text-sm text-qs-text" x-text="row.label"></span>
                            </label>
                        </template>
                        <div x-show="filteredClassrooms().length === 0" class="space-y-2 text-sm text-amber-900">
                            <p>{{ __('No class groups are linked to this course yet.') }}</p>
                            <p class="text-xs text-amber-800">{{ __('A coordinator must enroll class groups in this course before you can target students. You can review your groups under Classes.') }}</p>
                            <a href="{{ route('examiner.teaching-classes.index') }}" class="inline-flex text-xs font-semibold text-sky-800 underline-offset-2 hover:underline">{{ __('Open Classes') }}</a>
                        </div>
                    </div>
                    <p x-show="selectedClassIds.length > 0" class="mt-2 text-xs text-qs-muted">
                        <span x-text="selectedClassIds.length"></span> {{ __('group(s) selected') }}
                    </p>
                    <div id="classroom-ids-mount"></div>
                </fieldset>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">
                        <span x-show="assessmentType !== 'assignment'">{{ __('Description') }}</span>
                        <span x-show="assessmentType === 'assignment'" x-cloak>{{ __('Instructions for students') }}</span>
                        <span class="text-qs-danger" x-show="assessmentType === 'assignment'">*</span>
                    </label>
                    <textarea name="description" rows="3" class="qs-input mt-1 w-full py-2.5" :required="assessmentType === 'assignment'">{{ old('description') }}</textarea>
                    <p class="mt-1 text-xs text-qs-muted" x-show="assessmentType === 'assignment'">{{ __('General rules only (how to submit, files, word count). The actual question students answer is in “Essay question” below.') }}</p>
                </div>
            </section>

            <section x-show="assessmentType === 'assignment'" x-cloak class="space-y-4" aria-labelledby="ec-assignment-question">
                <h2 id="ec-assignment-question" class="text-sm font-semibold text-qs-text">{{ __('Essay question') }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted" for="assignment-question">{{ __('Essay question (what students answer)') }} <span class="text-qs-danger">*</span></label>
                    <p class="mb-2 text-xs text-qs-muted">{{ __('Not an AI generator — this text becomes the single essay question in the assignment.') }}</p>
                    <textarea
                        id="assignment-question"
                        name="assignment_question"
                        rows="5"
                        class="qs-input mt-1 w-full py-2.5"
                        :required="assessmentType === 'assignment'"
                        placeholder="{{ __('e.g. Discuss the role of normalization in database design. Support your answer with examples.') }}"
                    >{{ old('assignment_question') }}</textarea>
                    <p class="mt-1 text-xs text-qs-muted">{{ __('This is what students answer. You can edit it later in the builder.') }}</p>
                </div>
                <div class="max-w-xs">
                    <label class="mb-1 block text-sm font-medium text-qs-muted" for="assignment-marks">{{ __('Total marks') }} <span class="text-qs-danger">*</span></label>
                    <input
                        id="assignment-marks"
                        type="number"
                        name="assignment_marks"
                        value="{{ old('assignment_marks', 100) }}"
                        min="1"
                        max="10000"
                        step="0.5"
                        class="qs-input mt-1 w-full py-2.5"
                        :required="assessmentType === 'assignment'"
                    />
                </div>
            </section>

            <section x-show="assessmentType === 'assignment'" x-cloak class="space-y-4" aria-labelledby="ec-assignment">
                <h2 id="ec-assignment" class="text-sm font-semibold text-qs-text">{{ __('Due date') }}</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Submit by') }} <span class="text-qs-danger">*</span></label>
                    <input type="datetime-local" name="due_at" value="{{ old('due_at') }}" class="qs-input mt-1 w-full max-w-md py-2.5" :required="assessmentType === 'assignment'" />
                    <p class="mt-1 text-xs text-qs-muted">{{ __('There is no exam timer — students work in their own time until this deadline. Late submissions can be allowed in the builder after you save.') }}</p>
                </div>
                <details class="rounded-lg border border-qs-soft bg-white px-4 py-3">
                    <summary class="cursor-pointer text-sm font-medium text-qs-text">{{ __('Optional: open and close window') }}</summary>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Leave blank to open as soon as you publish. Most assignments only need a due date above.') }}</p>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Available from') }}</label>
                            <input type="datetime-local" name="start_time" value="{{ old('start_time') }}" class="qs-input mt-1 w-full py-2.5" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Stop accepting after') }}</label>
                            <input type="datetime-local" name="end_time" value="{{ old('end_time') }}" class="qs-input mt-1 w-full py-2.5" />
                        </div>
                    </div>
                </details>
            </section>

            <section x-show="assessmentType !== 'assignment'" x-cloak class="space-y-4" aria-labelledby="ec-pool">
                <h2 id="ec-pool" class="text-sm font-semibold text-qs-text">{{ __('Question pool & delivery') }}</h2>

                {{-- Pool-relationship explainer. Shows the running pool size
                     (from JSON paste or AI counts) next to "Questions per
                     student" so the lecturer can see exactly how the
                     sampling works without scrolling between fields. --}}
                <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-50/40 p-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Duration (minutes)') }} <span class="text-qs-danger">*</span></label>
                            <div class="relative">
                                <input
                                    type="number"
                                    name="duration_minutes"
                                    value="{{ old('duration_minutes', 30) }}"
                                    min="1"
                                    max="600"
                                    required
                                    class="qs-input mt-1 w-full py-2.5 pe-12"
                                />
                                <span class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-qs-muted">{{ __('min') }}</span>
                            </div>
                            <p class="mt-1 text-xs text-qs-muted">{{ __('Exam time limit per student, not a question count.') }}</p>
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="block text-sm font-medium text-qs-muted">{{ __('Questions per student') }}</label>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                    :class="poolStatusClass"
                                    x-show="poolSizeAvailable > 0"
                                    x-cloak
                                >
                                    <i class="fa-solid fa-layer-group text-[9px]" aria-hidden="true"></i>
                                    <span x-text="poolStatusLabel"></span>
                                </span>
                            </div>
                            <input
                                type="number"
                                name="questions_per_student"
                                x-model.number="questionsPerStudent"
                                @input="questionsPerStudentDirty = true"
                                min="1"
                                max="500"
                                class="qs-input mt-1 w-full py-2.5"
                                :required="source === 'paste_json' || source === 'ai_generate'"
                            />
                            <p class="mt-1 text-xs text-qs-muted">
                                <span x-show="source === 'later'">{{ __('Each student draws this many from the approved pool. The pool is built later in the question editor.') }}</span>
                                <span x-show="source === 'paste_json'" x-cloak>{{ __('Pasted JSON below acts as the pool. Each student sees this many random questions from it.') }}</span>
                                <span x-show="source === 'ai_generate'" x-cloak>{{ __('The "Number of questions" you generate below is the pool. Each student sees this many random questions from it.') }}</span>
                            </p>
                            <p
                                x-show="source !== 'later' && poolSizeAvailable > 0 && questionsPerStudent > poolSizeAvailable"
                                x-cloak
                                class="mt-1 inline-flex items-start gap-1.5 text-xs font-medium text-amber-700"
                            >
                                <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                                <span x-text="poolMismatchHint"></span>
                            </p>
                        </div>
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

            <section x-show="assessmentType !== 'assignment'" x-cloak class="space-y-4" aria-labelledby="ec-source">
                <h2 id="ec-source" class="text-sm font-semibold text-qs-text">{{ __('Questions') }}</h2>
                <p class="text-xs leading-snug text-qs-muted">{{ __('Save as a draft and build later, import JSON with a live prompt helper, or generate questions in-app when AI is enabled.') }}</p>
                <input type="hidden" name="question_source" :value="source" />

                {{-- Source picker as modern radio cards. Each card has a
                     short headline and a one-line description so the
                     lecturer can pick the right path without reading docs. --}}
                <div class="grid gap-3 sm:grid-cols-3">
                    @php
                        $sources = [
                            'later' => [
                                'icon' => 'fa-pen-ruler',
                                'title' => __('Add later'),
                                'desc' => __('Save as a draft and build the pool in the question editor.'),
                                'disabled' => false,
                            ],
                            'paste_json' => [
                                'icon' => 'fa-file-import',
                                'title' => __('Import JSON'),
                                'desc' => __('Paste an external generator’s output. Live validation flags mismatches.'),
                                'disabled' => false,
                            ],
                            'ai_generate' => [
                                'icon' => 'fa-wand-magic-sparkles',
                                'title' => __('Generate with AI'),
                                'desc' => __('Use your institution’s AI to draft questions from an outline and topics.'),
                                'disabled' => ! $aiEnabled,
                            ],
                        ];
                    @endphp
                    @foreach ($sources as $value => $meta)
                        <label
                            class="group relative flex cursor-pointer flex-col gap-2 rounded-xl border bg-white p-3.5 text-sm transition-all"
                            :class="source === '{{ $value }}' ? 'border-qs-accent bg-qs-accent/[0.06] shadow-sm ring-1 ring-inset ring-qs-accent/30' : 'border-qs-soft hover:border-qs-accent/40 hover:bg-slate-50/50'"
                            @if ($meta['disabled']) data-disabled="1" aria-disabled="true" @endif
                        >
                            <input type="radio" value="{{ $value }}" x-model="source" class="sr-only" @if ($meta['disabled']) disabled @endif />
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors"
                                    :class="source === '{{ $value }}' ? 'bg-qs-accent text-white' : 'bg-slate-100 text-slate-500'"
                                    aria-hidden="true"
                                >
                                    <i class="fa-solid {{ $meta['icon'] }} text-sm"></i>
                                </span>
                                <span class="min-w-0 flex-1 font-semibold text-qs-text">{{ $meta['title'] }}</span>
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full ring-1 ring-inset transition"
                                    :class="source === '{{ $value }}' ? 'bg-qs-accent text-white ring-qs-accent' : 'bg-white text-transparent ring-slate-300 group-hover:ring-slate-400'"
                                    aria-hidden="true"
                                >
                                    <i class="fa-solid fa-circle text-[8px]" x-show="source === '{{ $value }}'" x-cloak></i>
                                </span>
                            </div>
                            <p class="text-xs leading-snug text-qs-muted">{{ $meta['desc'] }}</p>
                            @if ($meta['disabled'])
                                <span class="mt-auto inline-flex w-max items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <i class="fa-solid fa-lock text-[9px]" aria-hidden="true"></i>
                                    {{ __('Disabled') }}
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @if (! $aiEnabled)
                    <p class="text-xs text-qs-muted">{{ __('In-app AI generation is turned off for your institution. You can still import JSON.') }}</p>
                @endif

                {{-- JSON import path: prompt + paste + validate --}}
                <div x-show="source === 'paste_json'" x-cloak class="space-y-6">
                    <div class="space-y-3 rounded-lg border border-qs-soft bg-white p-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-qs-muted" for="paste-topic-chip-input">{{ __('Topics') }}</label>
                            <input type="hidden" name="paste_prompt_topics" :value="pastePromptTopicsSerialized" />
                            <div class="w-full rounded-lg border border-qs-soft bg-white p-2">
                                <p class="mb-1.5 text-xs text-qs-muted">{{ __('Comma or Enter adds a chip; use "quotes" if one topic contains a comma.') }}</p>
                                <div class="mb-1.5 flex flex-wrap gap-1.5" x-show="pasteTopicTags.length > 0">
                                    <template x-for="(tag, idx) in pasteTopicTags" :key="idx + ':' + JSON.stringify(tag)">
                                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium" :class="pasteTopicChipClass(idx)">
                                            <span x-text="tag"></span>
                                            <button type="button" class="opacity-70 hover:opacity-100" :class="pasteTopicChipCloseClass(idx)" @click="pasteTopicRemove(idx)">×</button>
                                        </span>
                                    </template>
                                </div>
                                <input
                                    id="paste-topic-chip-input"
                                    type="text"
                                    x-model="pasteTopicInput"
                                    @keydown.enter.prevent="pasteTopicsAddFromInput()"
                                    @keydown.comma.prevent="pasteTopicsCommitComma($event)"
                                    @blur="pasteTopicsAddFromInput()"
                                    placeholder="{{ __('e.g. photography — then comma or Enter for the next') }}"
                                    class="w-full border-0 bg-transparent p-0 text-sm focus:outline-none focus:ring-0"
                                />
                            </div>
                        </div>
                        @include('examiner.exams.partials.question-type-counts', [
                            'idPrefix' => 'paste-count-create',
                            'hiddenName' => 'paste_prompt_count',
                            'emptyMessage' => __('Pick at least one auto-gradable question type (MCQ, True/False, or Fill-in-the-blank) in the pool above so your external generator knows what to produce.'),
                        ])
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
                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-qs-text">{{ __('Paste JSON') }}</h3>
                                <p class="text-xs text-qs-muted">{{ __('Paste the JSON from your generator here, then click Validate.') }}</p>
                            </div>
                            {{-- Live parsed-pool counter. Counts as the
                                 lecturer pastes/types — no server roundtrip.
                                 Surfaces type breakdown alongside the total
                                 so they can see, at a glance, whether the
                                 JSON matches their selected types. --}}
                            <div class="text-right text-xs">
                                <div
                                    x-show="importJsonDraft && importJsonDraft.trim().length > 0"
                                    x-cloak
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-semibold ring-1 ring-inset"
                                    :class="importJsonClientCount.invalid ? 'bg-amber-50 text-amber-800 ring-amber-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200'"
                                >
                                    <i class="fa-solid fa-cube text-[10px]" aria-hidden="true"></i>
                                    <span x-text="importJsonClientLabel"></span>
                                </div>
                            </div>
                        </div>
                        <textarea
                            id="import-json-field"
                            name="import_json"
                            rows="14"
                            x-model="importJsonDraft"
                            class="w-full rounded-xl border border-qs-soft bg-slate-950/[0.025] px-3 py-2.5 font-mono text-xs leading-relaxed text-qs-text shadow-inner placeholder:text-qs-muted focus:border-qs-accent focus:bg-white focus:outline-none focus:ring-2 focus:ring-qs-accent/30"
                            placeholder='[{"text":"Question?","options":{"A":"...","B":"...","C":"...","D":"..."},"correct":"A","topic":"..."}]'
                            spellcheck="false"
                        ></textarea>
                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-qs-text px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="validateImportJson()"
                                :disabled="importValidateBusy"
                            >
                                <svg x-show="!importValidateBusy" class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>
                                <svg x-show="importValidateBusy" x-cloak class="size-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                <span>{{ __('Validate JSON') }}</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-qs-soft bg-white px-3 text-sm font-medium text-qs-muted transition hover:bg-slate-50"
                                @click="importJsonDraft = ''; importValidateMessage = ''; importValidateOk = false;"
                                x-show="importJsonDraft && importJsonDraft.trim().length > 0"
                                x-cloak
                            >
                                <i class="fa-solid fa-eraser text-xs" aria-hidden="true"></i>
                                {{ __('Clear') }}
                            </button>
                        </div>
                        <div
                            x-show="importValidateMessage"
                            x-cloak
                            class="rounded-xl border px-3.5 py-2.5 text-sm leading-relaxed shadow-sm"
                            :class="importValidateOk ? 'border-emerald-200 bg-emerald-50/80 text-emerald-900' : 'border-qs-danger/35 bg-qs-danger-soft text-qs-danger'"
                        >
                            <div class="flex items-start gap-2">
                                <i
                                    class="mt-0.5 fa-solid text-base"
                                    :class="importValidateOk ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-qs-danger'"
                                    aria-hidden="true"
                                ></i>
                                <div class="min-w-0 flex-1 whitespace-pre-wrap" x-text="importValidateMessage"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- In-app AI --}}
                <div x-show="source === 'ai_generate'" x-cloak class="space-y-4 rounded-lg border border-qs-soft bg-white p-4">
                    <p class="text-xs text-qs-muted">{{ __('Upload an outline first to preview suggested topics (you can remove any). Topics are sent to the configured model with your assessment. Defaults: 10 questions, MCQ, moderate difficulty, 1 mark each.') }}</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-qs-muted" for="ai-outline-create">{{ __('Outline file (optional)') }}</label>
                            <input
                                id="ai-outline-create"
                                type="file"
                                name="ai_outline_file"
                                accept=".pdf,.txt,.docx,.csv,application/pdf,text/plain,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/csv,application/csv"
                                class="mt-1 block w-full text-sm text-qs-muted file:me-3 file:rounded-lg file:border-0 file:bg-qs-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-qs-text"
                                @change="onAiOutlineFileSelected($event)"
                            />
                            <div x-show="aiOutlineUploadBusy || aiOutlineUploadProgress !== null" x-cloak class="mt-2 space-y-1">
                                <div class="h-2 w-full overflow-hidden rounded-full bg-qs-soft">
                                    <div
                                        class="h-full rounded-full bg-qs-primary transition-all duration-150 ease-out"
                                        :class="aiOutlineUploadProgress === null && aiOutlineUploadBusy ? 'w-1/3 max-w-[45%] animate-pulse' : ''"
                                        :style="aiOutlineUploadProgress !== null ? 'width: ' + aiOutlineUploadProgress + '%' : ''"
                                    ></div>
                                </div>
                                <p class="text-xs text-qs-muted" x-show="aiOutlineUploadBusy">{{ __('Uploading…') }}</p>
                            </div>
                            <p x-show="aiOutlineUploadMessage" x-cloak class="mt-2 text-xs text-qs-danger" x-text="aiOutlineUploadMessage"></p>
                        </div>
                        @include('examiner.exams.partials.question-type-counts', [
                            'idPrefix' => 'ai-count-create',
                            'hiddenName' => 'ai_question_count',
                            'emptyMessage' => __('AI generation needs at least one auto-gradable question type — MCQ, True/False, or Fill-in-the-blank. Essays are manually graded and must be added by hand.'),
                        ])
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-qs-muted" for="ai-topic-chip-input">{{ __('Topics') }}</label>
                        <input type="hidden" name="ai_topics" :value="aiTopicsSerializedForForm" />
                        <div class="w-full rounded-lg border border-qs-soft bg-white p-2">
                            <p class="mb-1.5 text-xs text-qs-muted">{{ __('Suggested lines from your outline appear as chips. Comma or Enter adds more; use "quotes" if a topic contains a comma.') }}</p>
                            <div class="mb-1.5 flex flex-wrap gap-1.5" x-show="aiTopicTags.length > 0">
                                <template x-for="(tag, idx) in aiTopicTags" :key="'ai-' + idx + ':' + JSON.stringify(tag)">
                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium" :class="aiTopicChipClass(idx)">
                                        <span x-text="tag"></span>
                                        <button type="button" class="opacity-70 hover:opacity-100" :class="aiTopicChipCloseClass(idx)" @click="aiTopicRemove(idx)">×</button>
                                    </span>
                                </template>
                            </div>
                            <input
                                id="ai-topic-chip-input"
                                type="text"
                                x-model="aiTopicInput"
                                @keydown.enter.prevent="aiTopicsAddFromInput()"
                                @keydown.comma.prevent="aiTopicsCommitComma($event)"
                                @blur="aiTopicsAddFromInput()"
                                placeholder="{{ __('Add or edit topics…') }}"
                                class="w-full border-0 bg-transparent p-0 text-sm focus:outline-none focus:ring-0"
                            />
                        </div>
                    </div>
                    <div class="sm:col-span-2 space-y-2">
                        <p class="text-xs text-qs-muted">{{ __('Generated questions use the question types selected for the pool above.') }}</p>
                    </div>
                    <input type="hidden" name="ai_difficulty" value="moderate" />
                    <input type="hidden" name="ai_marks" value="1" />
                    <input type="hidden" name="ai_pregenerated_sections" :value="aiPregeneratedJson" />

                    {{-- Batched AI prep with live progress bar. Keeps the page
                         responsive (and well under timeout limits) even when
                         the lecturer asks for 100+ questions. --}}
                    <div class="sm:col-span-2 space-y-3 rounded-lg border border-qs-soft bg-qs-soft/30 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <p class="text-sm font-semibold text-qs-text">{{ __('Prepare AI questions') }}</p>
                                <p class="text-xs text-qs-muted">{{ __('Generates in small batches with a live progress bar so big sets never hit a timeout.') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="qs-btn-secondary inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold"
                                    @click="prepareAiQuestions()"
                                    :disabled="aiPrepStatus === 'running'"
                                >
                                    <svg x-show="aiPrepStatus !== 'running'" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M12 18v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m16.24 16.24 2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="m16.24 7.76 2.83-2.83"/></svg>
                                    <svg x-show="aiPrepStatus === 'running'" class="size-3.5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" x-cloak><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                                    <span x-show="aiPrepStatus === 'idle' || (aiPrepStatus === 'error' && aiPrepSections.length === 0)">{{ __('Generate now') }}</span>
                                    <span x-show="aiPrepStatus === 'error' && aiPrepSections.length > 0" x-cloak>{{ __('Resume') }}</span>
                                    <span x-show="aiPrepStatus === 'running'" x-cloak>{{ __('Generating…') }}</span>
                                    <span x-show="aiPrepStatus === 'ready'" x-cloak>{{ __('Regenerate') }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-qs-soft px-2.5 py-1.5 text-xs font-medium text-qs-muted hover:bg-qs-card"
                                    x-show="aiPrepStatus !== 'idle'"
                                    @click="resetAiPrep()"
                                    x-cloak
                                >{{ __('Clear') }}</button>
                            </div>
                        </div>

                        <div x-show="aiPrepStatus !== 'idle'" x-cloak class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-qs-muted">
                                    <span x-show="aiPrepStatus === 'running'">{{ __('Preparing questions…') }}</span>
                                    <span x-show="aiPrepStatus === 'ready'" x-cloak>{{ __('All set — submit when ready.') }}</span>
                                    <span x-show="aiPrepStatus === 'error'" x-cloak class="text-qs-danger">{{ __('Generation failed.') }}</span>
                                </span>
                                <span class="font-mono text-[11px] text-qs-muted">
                                    <span x-text="aiPrepDoneCount"></span>/<span x-text="aiPrepTargetCount"></span>
                                    <span class="ms-1" x-text="'(' + aiPrepProgress + '%)'"></span>
                                    <span class="ms-2 inline-flex items-center gap-1"
                                          x-show="aiPrepElapsedSec > 0"
                                          x-cloak
                                          :class="aiPrepIsSlow() ? 'text-amber-700 font-semibold' : ''"
                                          :title="aiPrepIsSlow() ? @js(__('Generation is taking longer than usual.')) : @js(__('Elapsed time since generation started.'))">
                                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                        <span x-text="aiPrepElapsedLabel()"></span>
                                    </span>
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-qs-soft">
                                <div
                                    class="h-full rounded-full transition-all duration-200 ease-out"
                                    :class="aiPrepStatus === 'error' ? 'bg-qs-danger' : (aiPrepStatus === 'ready' ? 'bg-emerald-500' : 'bg-qs-primary')"
                                    :style="'width: ' + aiPrepProgress + '%'"
                                ></div>
                            </div>
                            <p x-show="aiPrepMessage" x-cloak class="text-xs"
                               :class="aiPrepStatus === 'error' ? 'text-qs-danger' : 'text-qs-muted'"
                               x-text="aiPrepMessage"></p>

                            {{-- >2 minutes still running → offer the manual
                                 import path so the lecturer isn't blocked on a
                                 slow provider. --}}
                            <div
                                x-show="aiPrepIsSlow()"
                                x-cloak
                                class="flex flex-col gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 sm:flex-row sm:items-center sm:justify-between"
                                role="status"
                                aria-live="polite"
                            >
                                <p class="min-w-0">
                                    <strong class="font-semibold">{{ __('Taking longer than usual.') }}</strong>
                                    {{ __('Generation has been running for over 2 minutes. You can keep waiting, or switch to "Import JSON" — copy the prompt, run it in your own AI tool (ChatGPT, Claude, etc.), and paste the JSON back. It is often faster for big sets.') }}
                                </p>
                                <button
                                    type="button"
                                    class="shrink-0 inline-flex items-center gap-1.5 rounded-md bg-amber-700 px-3 py-1.5 font-semibold text-white shadow-sm hover:bg-amber-800"
                                    @click="cancelPrepAndSwitchToImport()"
                                >
                                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                    {{ __('Use Import JSON instead') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="ec-schedule">
                <h2 id="ec-schedule" class="text-sm font-semibold text-qs-text">
                    <span x-show="assessmentType !== 'assignment'">{{ __('Scheduling') }}</span>
                    <span x-show="assessmentType === 'assignment'" x-cloak>{{ __('Publish') }}</span>
                </h2>
                <div x-show="assessmentType !== 'assignment'" x-cloak class="grid gap-4 sm:grid-cols-2">
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
                    <span x-show="assessmentType !== 'assignment'">{{ __('Publish immediately if validation passes') }}</span>
                    <span x-show="assessmentType === 'assignment'" x-cloak>{{ __('Publish immediately') }}</span>
                </label>
                <p class="text-xs text-qs-muted" x-show="assessmentType !== 'assignment'">{{ __('Requires an approved question pool and delivery settings. If anything is missing, the assessment is saved as a draft.') }}</p>
                <p class="text-xs text-qs-muted" x-show="assessmentType === 'assignment'" x-cloak>{{ __('Uncheck to save as a draft and publish from the builder when ready. Requires instructions, essay question, due date, and class groups.') }}</p>
                <label x-show="assessmentType !== 'assignment'" class="inline-flex items-center gap-2 text-sm text-qs-text">
                    <input type="checkbox" name="show_correct_answers_in_results" value="1" class="size-4 rounded border-qs-soft text-qs-accent" @checked(old('show_correct_answers_in_results')) />
                    {{ __('Allow students to see correct answers in results (when your school policy permits)') }}
                </label>
            </section>

            <div class="flex flex-wrap gap-3 border-t border-qs-soft pt-4">
                <button
                    type="button"
                    class="qs-btn-primary min-h-[44px] px-5 text-sm font-semibold"
                    @if (count($classroomOptions) === 0) disabled @endif
                    @click="wizardNext()"
                    x-show="assessmentType !== 'assignment'"
                >
                    {{ __('Next') }}
                </button>
                <button
                    type="submit"
                    class="qs-btn-primary min-h-[44px] px-5 text-sm font-semibold"
                    @if (count($classroomOptions) === 0) disabled @endif
                    x-show="assessmentType === 'assignment'"
                    x-cloak
                >
                    {{ __('Create assignment') }}
                </button>
                <a href="{{ route('examiner.exams.index') }}" class="inline-flex min-h-[44px] items-center rounded-lg border border-qs-soft px-4 text-sm font-semibold text-qs-muted hover:bg-qs-card">
                    {{ __('Cancel') }}
                </a>
            </div>
            </div>

            <div x-show="wizardStep === 2" x-cloak class="space-y-6">
                <div x-show="assessmentType === 'assignment'" x-cloak class="rounded-xl border border-sky-200 bg-sky-50/80 px-4 py-4 text-sm text-slate-800">
                    <p class="font-semibold text-slate-900">{{ __('Coursework proctoring') }}</p>
                    <p class="mt-2 leading-relaxed">{{ __('Live camera, audio, and violation auto-submit stay off for assignments. Students type answers in-app with copy and paste blocked in text fields. You can fine-tune release of grades after marking from the builder once questions exist.') }}</p>
                </div>
                <div x-show="assessmentType !== 'assignment'" x-cloak class="space-y-6">
                <p class="text-sm leading-relaxed text-qs-muted">
                    {{ __('Choose proctoring options for this assessment, then continue to the builder to add or review questions.') }}
                </p>
                @include('examiner.exams.partials.proctoring-examiner-panel', [
                    'proctoringPolicy' => $proctoringPolicy,
                    'examProctoringControls' => $examProctoringControls,
                    'variant' => 'embedded',
                ])
                </div>
                <div class="flex flex-wrap gap-3 border-t border-qs-soft pt-4">
                    <button type="button" class="qs-btn-secondary min-h-[44px] px-5 text-sm font-semibold" @click="wizardBack()">
                        {{ __('Back') }}
                    </button>
                    <button type="submit" class="qs-btn-primary min-h-[44px] px-5 text-sm font-semibold" @if (count($classroomOptions) === 0) disabled @endif>
                        {{ __('Save and continue') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function splitCommaSeparatedRespectingQuotesCreate(str) {
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

            function takeFirstCommaSegmentOutsideQuotesCreate(str) {
                const s = String(str);
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
                        return { first: cur.trim(), rest: s.slice(i + 1) };
                    }
                    cur += c;
                }
                return { first: null, rest: s };
            }

            function parseInitialPasteTopicTags(raw) {
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
                return [...new Set(splitCommaSeparatedRespectingQuotesCreate(s))];
            }

            function parseInitialAiTopicTags(raw) {
                if (raw == null || String(raw).trim() === '') {
                    return [];
                }
                const s = String(raw).trim();
                if (s.startsWith('[')) {
                    try {
                        const j = JSON.parse(s);
                        if (Array.isArray(j) && j.every((x) => typeof x === 'string')) {
                            return [...new Set(j.map((t) => t.trim()).filter((t) => t.length > 0))];
                        }
                    } catch (e) {}
                }
                const acc = [];
                s.split(/\r?\n/).forEach((line) => {
                    const t = line.trim();
                    if (!t) {
                        return;
                    }
                    splitCommaSeparatedRespectingQuotesCreate(t).forEach((p) => {
                        if (p && !acc.includes(p)) {
                            acc.push(p);
                        }
                    });
                });
                return acc;
            }

            // Detects an array-of-MCQ items shape (the "ChatGPT-style"
            // payload our validator also accepts). Used by the client-side
            // counter so the live pool-size pill matches what the server
            // will eventually count.
            function looksLikeFlatMcqArray(arr) {
                if (!Array.isArray(arr) || arr.length === 0) return false;
                const first = arr[0];
                return first && typeof first === 'object'
                    && typeof first.text === 'string'
                    && first.options && typeof first.options === 'object'
                    && typeof first.correct !== 'undefined';
            }

            // Pure JS approximation of the server validator's pool counter.
            // Returns { count, invalid, breakdown } so the live pill in the
            // JSON panel can show "12 questions · 8 MCQ, 4 True/False" the
            // moment the lecturer pastes the payload.
            function countPoolFromImportJson(raw) {
                const out = { count: 0, invalid: false, breakdown: { mcq: 0, true_false: 0, fill_blank: 0, essay: 0 } };
                if (!raw || !raw.trim()) {
                    return out;
                }
                let parsed = null;
                try {
                    parsed = JSON.parse(raw);
                } catch (e) {
                    out.invalid = true;
                    return out;
                }
                const tally = (q) => {
                    const t = q && typeof q === 'object' && typeof q.type === 'string' ? q.type.toLowerCase() : null;
                    if (t && out.breakdown[t] !== undefined) {
                        out.breakdown[t] += 1;
                    } else if (q && typeof q === 'object' && q.options && typeof q.text === 'string') {
                        // Flat ChatGPT MCQ shape.
                        out.breakdown.mcq += 1;
                    }
                    out.count += 1;
                };
                if (looksLikeFlatMcqArray(parsed)) {
                    parsed.forEach(tally);
                    return out;
                }
                if (parsed && typeof parsed === 'object' && Array.isArray(parsed.sections)) {
                    parsed.sections.forEach((section) => {
                        const qs = section && Array.isArray(section.questions) ? section.questions : [];
                        qs.forEach(tally);
                    });
                    return out;
                }
                out.invalid = true;
                return out;
            }

            function qsExamCreateForm(cfg) {
                // Seed the selected-types Alpine array from the same defaults
                // the Blade checkbox row uses, so x-model stays in sync with
                // the initial DOM state on first paint.
                const initialSelectedTypes = (() => {
                    const fromOld = Array.isArray(cfg.selectedQuestionTypesInitial)
                        ? cfg.selectedQuestionTypesInitial
                            .map((v) => String(v || '').trim().toLowerCase())
                            .filter((v) => v && v !== 'essay')
                        : null;
                    if (fromOld && fromOld.length > 0) return fromOld;
                    return ['mcq', 'true_false', 'fill_blank'];
                })();
                return {
                    rows: cfg.rows,
                    courseId: cfg.courseId,
                    selectedClassIds: cfg.selectedClassIds,
                    assessmentType: cfg.assessmentType || 'quiz',
                    source: cfg.source,
                    // Reactive mirror of the type-in-pool checkboxes. Every
                    // downstream reactive getter (aiEligibleTypes,
                    // poolSizeAvailable, etc.) reads from this array so
                    // toggling a checkbox immediately re-renders the right
                    // per-type counter fields.
                    selectedQuestionTypes: initialSelectedTypes,
                    pasteTopicTags: parseInitialPasteTopicTags(cfg.pastePromptTopics),
                    pasteTopicInput: '',
                    // `pastePromptCount` is now a derived getter (see below) so
                    // both source paths share `aiTypeCounts` as the single
                    // source of truth for the per-type breakdown.
                    // Per-type breakdown for AI generation. The total
                    // (`aiQuestionCount` getter) is derived from these so the
                    // UI can offer either a single total field or a per-type
                    // grid depending on how many types are selected.
                    aiTypeCounts: (function () {
                        const seed = cfg.aiTypeCountsInitial && typeof cfg.aiTypeCountsInitial === 'object'
                            ? cfg.aiTypeCountsInitial
                            : null;
                        const base = { mcq: 0, true_false: 0, fill_blank: 0 };
                        if (seed) {
                            ['mcq','true_false','fill_blank'].forEach((k) => {
                                const v = parseInt(seed[k], 10);
                                if (!Number.isNaN(v) && v >= 0) {
                                    base[k] = v;
                                }
                            });
                        } else {
                            const fallback = parseInt(cfg.aiQuestionCount, 10);
                            base.mcq = !Number.isNaN(fallback) && fallback > 0 ? fallback : 10;
                        }
                        return base;
                    })(),
                    questionsPerStudent: cfg.questionsPerStudent,
                    // Tracks whether the examiner manually edited the field.
                    // When false we may convenience-sync it to the import/AI
                    // count; once they touch it we leave their value alone.
                    questionsPerStudentDirty: false,
                    importJsonDraft: cfg.importJsonDraft,
                    validateImportUrl: cfg.validateImportUrl,
                    outlineSuggestTopicsUrl: cfg.outlineSuggestTopicsUrl,
                    aiGenerateBatchUrl: cfg.aiGenerateBatchUrl || '',
                    aiGenerateBatchSize: Math.max(1, Math.min(20, parseInt(cfg.aiGenerateBatchSize, 10) || 10)),
                    aiTopicTags: parseInitialAiTopicTags(cfg.aiTopicsInitial),
                    aiTopicInput: '',
                    aiOutlineUploadProgress: null,
                    aiOutlineUploadBusy: false,
                    aiOutlineUploadMessage: '',
                    aiOutlineText: '',
                    aiPrepStatus: 'idle',
                    aiPrepProgress: 0,
                    aiPrepMessage: '',
                    aiPrepDoneCount: 0,
                    aiPrepTargetCount: 0,
                    aiPrepSections: [],
                    aiPrepAbort: false,
                    // Wall-clock timer for AI prep so the lecturer can see how
                    // long generation has taken and we can suggest the import
                    // path if it drags past 2 minutes.
                    aiPrepStartedAt: null,
                    aiPrepElapsedSec: 0,
                    aiPrepTickHandle: null,
                    aiPrepSlowThresholdSec: 120,
                    csrfToken: cfg.csrfToken,
                    importValidateBusy: false,
                    importValidateOk: false,
                    importValidateMessage: '',
                    copyPromptHint: '',
                    wizardStep: cfg.initialWizardStep === 2 ? 2 : 1,
                    // Auto-graded order (mirror of server-side AssessmentQuestionTypes::AUTO_GRADED)
                    aiAutoGradedTypes: ['mcq', 'true_false', 'fill_blank'],
                    aiTypeLabels: {
                        mcq: @json(__('Multiple choice')),
                        true_false: @json(__('True/False')),
                        fill_blank: @json(__('Fill-in-the-blank')),
                    },
                    get aiQuestionCount() {
                        const c = this.aiTypeCounts || {};
                        return this.aiAutoGradedTypes.reduce((acc, t) => {
                            const n = parseInt(c[t], 10);
                            return acc + (Number.isNaN(n) || n < 0 ? 0 : n);
                        }, 0);
                    },
                    // Derived alias so the existing paste-prompt code keeps
                    // working — both flows pull from the same per-type counts.
                    get pastePromptCount() {
                        return this.aiQuestionCount;
                    },
                    aiEligibleTypes() {
                        const selected = this.selectedQuestionTypesForAi();
                        return this.aiAutoGradedTypes.filter((t) => selected.includes(t));
                    },
                    aiTypeLabel(t) {
                        return this.aiTypeLabels[t] || String(t || '').toUpperCase();
                    },

                    // === Pool-size relationship getters ===========================
                    // The "Questions per student" field is only meaningful in
                    // the context of a pool. These getters expose the running
                    // pool size from whichever source the lecturer picked
                    // (paste_json -> count the parsed JSON; ai_generate ->
                    // sum the per-type counters). The view uses them for the
                    // pill near the per-student input and the mismatch hint.
                    get importJsonClientCount() {
                        return countPoolFromImportJson(this.importJsonDraft || '');
                    },
                    get importJsonClientLabel() {
                        const c = this.importJsonClientCount;
                        if (c.invalid) {
                            return @json(__('JSON not parseable yet'));
                        }
                        if (c.count === 0) {
                            return @json(__('Empty pool'));
                        }
                        const parts = [];
                        if (c.breakdown.mcq) parts.push(c.breakdown.mcq + ' ' + this.aiTypeLabel('mcq'));
                        if (c.breakdown.true_false) parts.push(c.breakdown.true_false + ' ' + this.aiTypeLabel('true_false'));
                        if (c.breakdown.fill_blank) parts.push(c.breakdown.fill_blank + ' ' + this.aiTypeLabel('fill_blank'));
                        if (c.breakdown.essay) parts.push(c.breakdown.essay + ' ' + @json(__('Essay')));
                        return c.count + ' ' + (c.count === 1 ? @json(__('question')) : @json(__('questions'))) + (parts.length ? ' · ' + parts.join(', ') : '');
                    },
                    get poolSizeAvailable() {
                        if (this.source === 'paste_json') {
                            return this.importJsonClientCount.invalid ? 0 : this.importJsonClientCount.count;
                        }
                        if (this.source === 'ai_generate') {
                            return this.aiQuestionCount || 0;
                        }
                        return 0;
                    },
                    get poolStatusLabel() {
                        const size = this.poolSizeAvailable;
                        if (size === 0) return '';
                        const per = parseInt(this.questionsPerStudent, 10) || 0;
                        if (per > 0 && per <= size) {
                            return @json(__('Pool of :pool · each student gets :per')).replace(':pool', size).replace(':per', per);
                        }
                        return @json(__('Pool of :pool')).replace(':pool', size);
                    },
                    get poolStatusClass() {
                        const size = this.poolSizeAvailable;
                        if (size === 0) return 'bg-slate-50 text-slate-500 ring-slate-200';
                        const per = parseInt(this.questionsPerStudent, 10) || 0;
                        if (per > 0 && per > size) {
                            return 'bg-amber-50 text-amber-800 ring-amber-200';
                        }
                        return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
                    },
                    get poolMismatchHint() {
                        const size = this.poolSizeAvailable;
                        const per = parseInt(this.questionsPerStudent, 10) || 0;
                        if (size === 0 || per === 0) return '';
                        if (per > size) {
                            return @json(__('Pool only has :pool question(s) — lower the per-student count or add more questions.')).replace(':pool', size);
                        }
                        return '';
                    },

                    init() {
                        this.$watch('courseId', () => this.onCourseChanged());
                        this.onCourseChanged();
                        this.$watch('assessmentType', (type) => {
                            if (type === 'assignment') {
                                this.source = 'later';
                            }
                        });
                        // Deep watch the per-type counts so the AI prep cache
                        // is invalidated when the planned breakdown changes.
                        // We deliberately do NOT auto-sync questionsPerStudent
                        // here — that's the examiner's choice (e.g. generate
                        // 80 questions but serve each student 20 from the
                        // pool). We only seed it once via the `source` watcher
                        // below, and only when the examiner hasn't touched it.
                        this.$watch('aiTypeCounts', () => {
                            if (this.aiPrepStatus === 'ready') {
                                this.resetAiPrep();
                            }
                        }, { deep: true });
                        this.$watch('aiTopicTags', () => {
                            if (this.aiPrepStatus === 'ready') {
                                this.resetAiPrep();
                            }
                        });
                        this.$watch('source', (mode) => {
                            if (this.questionsPerStudentDirty) {
                                return;
                            }
                            if (mode === 'ai_generate') {
                                this.questionsPerStudent = this.clampQuestionCount(this.aiQuestionCount);
                            } else if (mode === 'paste_json') {
                                this.questionsPerStudent = this.clampQuestionCount(this.pastePromptCount);
                            }
                        });
                    },
                    clampQuestionCount(value) {
                        let n = parseInt(value, 10);
                        if (Number.isNaN(n) || n < 1) {
                            n = 1;
                        }
                        return Math.min(500, n);
                    },
                    normalizeClassroomId(id) {
                        const n = parseInt(id, 10);
                        return Number.isNaN(n) ? null : n;
                    },
                    isClassroomSelected(id) {
                        const n = this.normalizeClassroomId(id);
                        if (n === null) {
                            return false;
                        }
                        return this.selectedClassIds.some((x) => this.normalizeClassroomId(x) === n);
                    },
                    toggleClassroom(id, checked) {
                        const n = this.normalizeClassroomId(id);
                        if (n === null) {
                            return;
                        }
                        if (checked) {
                            if (!this.isClassroomSelected(n)) {
                                this.selectedClassIds = [...this.selectedClassIds, n];
                            }
                        } else {
                            this.selectedClassIds = this.selectedClassIds.filter(
                                (x) => this.normalizeClassroomId(x) !== n,
                            );
                        }
                        this.syncClassroomInputs();
                    },
                    onCourseChanged() {
                        const visible = this.filteredClassrooms();
                        const visibleIds = visible.map((r) => this.normalizeClassroomId(r.id)).filter((id) => id !== null);
                        this.selectedClassIds = this.selectedClassIds
                            .map((x) => this.normalizeClassroomId(x))
                            .filter((id) => id !== null && visibleIds.includes(id));
                        if (visible.length === 1) {
                            this.selectedClassIds = [visibleIds[0]];
                        }
                        this.syncClassroomInputs();
                    },
                    handleFormSubmit(event) {
                        this.syncClassroomInputs();
                        if (!this.selectedClassIds || this.selectedClassIds.length === 0) {
                            window.alert(@json(__('Select at least one class group for the chosen course.')));
                            this.wizardStep = 1;
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                            return;
                        }
                        const form = event.target;
                        if (form && !form.checkValidity()) {
                            form.reportValidity();
                            return;
                        }
                        form.submit();
                    },
                    wizardNext() {
                        this.syncClassroomInputs();
                        if (!this.selectedClassIds || this.selectedClassIds.length === 0) {
                            window.alert(@json(__('Select at least one class group for the chosen course.')));
                            return;
                        }
                        if (this.filteredClassrooms().length === 0) {
                            window.alert(@json(__('No class groups are linked to this course. Ask a coordinator to enroll classes in the course first.')));
                            return;
                        }
                        const form = document.getElementById('exam-create-form');
                        if (form && !form.checkValidity()) {
                            form.reportValidity();
                            return;
                        }
                        if (this.assessmentType !== 'assignment') {
                            const typeCbs = form.querySelectorAll('input.qs-qtype-cb:checked');
                            if (!typeCbs.length) {
                                window.alert(@json(__('Select at least one question type for the pool.')));
                                return;
                            }
                        }

                        // When the lecturer asks for many AI questions, force
                        // them to run the batched prep (with progress bar)
                        // before continuing so the final submit never has to
                        // call the LLM in one shot.
                        if (
                            this.assessmentType !== 'assignment' &&
                            this.source === 'ai_generate' &&
                            this.aiPrepStatus !== 'ready'
                        ) {
                            const total = this.clampQuestionCount(this.aiQuestionCount);
                            const threshold = Math.max(this.aiGenerateBatchSize, 15);
                            if (total > threshold || this.aiPrepStatus === 'running') {
                                this.aiPrepMessage =
                                    this.aiPrepStatus === 'running'
                                        ? @json(__('Generation is in progress — wait for it to finish.'))
                                        : @json(__('Click "Generate now" first so we can build your questions in small batches without timing out.'));
                                this.aiPrepStatus = this.aiPrepStatus === 'running' ? 'running' : 'error';
                                const panel = document.getElementById('ai-outline-create');
                                if (panel && panel.scrollIntoView) {
                                    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }
                                return;
                            }
                        }

                        this.wizardStep = 2;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    },
                    wizardBack() {
                        this.wizardStep = 1;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    },
                    get pastePromptTopicsSerialized() {
                        return JSON.stringify(this.pasteTopicTags);
                    },
                    pasteTopicChipClass(idx) {
                        const palettes = [
                            'border-qs-primary/35 bg-qs-primary/10 text-qs-primary',
                            'border-emerald-400/45 bg-emerald-50 text-emerald-900',
                            'border-violet-400/45 bg-violet-50 text-violet-900',
                            'border-amber-400/45 bg-amber-50 text-amber-950',
                            'border-sky-400/45 bg-sky-50 text-sky-950',
                        ];
                        return palettes[idx % palettes.length];
                    },
                    pasteTopicChipCloseClass(idx) {
                        const muted = [
                            'text-qs-primary/80',
                            'text-emerald-900/70',
                            'text-violet-900/70',
                            'text-amber-950/70',
                            'text-sky-950/70',
                        ];
                        return muted[idx % muted.length];
                    },
                    pasteTopicsCommitComma(e) {
                        e.preventDefault();
                        const el = e.target;
                        const raw = String(this.pasteTopicInput || '');
                        const start = typeof el.selectionStart === 'number' ? el.selectionStart : raw.length;
                        const end = typeof el.selectionEnd === 'number' ? el.selectionEnd : start;
                        const synthetic = raw.slice(0, start) + ',' + raw.slice(end);
                        const { first, rest } = takeFirstCommaSegmentOutsideQuotesCreate(synthetic);
                        if (first !== null) {
                            if (first !== '' && !this.pasteTopicTags.includes(first)) {
                                this.pasteTopicTags.push(first);
                            }
                            this.pasteTopicInput = rest.replace(/^\s+/, '');
                        } else {
                            this.pasteTopicInput = synthetic;
                        }
                        this.$nextTick(() => {
                            try {
                                const pos = this.pasteTopicInput.length;
                                el.setSelectionRange(pos, pos);
                            } catch (_) {}
                        });
                    },
                    pasteTopicsAddFromInput() {
                        const v = String(this.pasteTopicInput || '').trim();
                        if (v === '') {
                            return;
                        }
                        splitCommaSeparatedRespectingQuotesCreate(v).forEach((p) => {
                            if (p && !this.pasteTopicTags.includes(p)) {
                                this.pasteTopicTags.push(p);
                            }
                        });
                        this.pasteTopicInput = '';
                    },
                    pasteTopicRemove(idx) {
                        this.pasteTopicTags.splice(idx, 1);
                    },
                    get aiTopicsSerializedForForm() {
                        return this.aiTopicTags.join('\n');
                    },
                    aiTopicChipClass(idx) {
                        const palettes = [
                            'border-qs-primary/35 bg-qs-primary/10 text-qs-primary',
                            'border-emerald-400/45 bg-emerald-50 text-emerald-900',
                            'border-violet-400/45 bg-violet-50 text-violet-900',
                            'border-amber-400/45 bg-amber-50 text-amber-950',
                            'border-sky-400/45 bg-sky-50 text-sky-950',
                        ];
                        return palettes[idx % palettes.length];
                    },
                    aiTopicChipCloseClass(idx) {
                        const muted = [
                            'text-qs-primary/80',
                            'text-emerald-900/70',
                            'text-violet-900/70',
                            'text-amber-950/70',
                            'text-sky-950/70',
                        ];
                        return muted[idx % muted.length];
                    },
                    aiTopicsCommitComma(e) {
                        e.preventDefault();
                        const el = e.target;
                        const raw = String(this.aiTopicInput || '');
                        const start = typeof el.selectionStart === 'number' ? el.selectionStart : raw.length;
                        const end = typeof el.selectionEnd === 'number' ? el.selectionEnd : start;
                        const synthetic = raw.slice(0, start) + ',' + raw.slice(end);
                        const { first, rest } = takeFirstCommaSegmentOutsideQuotesCreate(synthetic);
                        if (first !== null) {
                            if (first !== '' && !this.aiTopicTags.includes(first)) {
                                this.aiTopicTags.push(first);
                            }
                            this.aiTopicInput = rest.replace(/^\s+/, '');
                        } else {
                            this.aiTopicInput = synthetic;
                        }
                        this.$nextTick(() => {
                            try {
                                const pos = this.aiTopicInput.length;
                                el.setSelectionRange(pos, pos);
                            } catch (_) {}
                        });
                    },
                    aiTopicsAddFromInput() {
                        const v = String(this.aiTopicInput || '').trim();
                        if (v === '') {
                            return;
                        }
                        splitCommaSeparatedRespectingQuotesCreate(v).forEach((p) => {
                            if (p && !this.aiTopicTags.includes(p)) {
                                this.aiTopicTags.push(p);
                            }
                        });
                        this.aiTopicInput = '';
                    },
                    aiTopicRemove(idx) {
                        this.aiTopicTags.splice(idx, 1);
                    },
                    onAiOutlineFileSelected(e) {
                        const input = e.target;
                        const file = input.files && input.files[0];
                        this.aiOutlineUploadMessage = '';
                        if (!file) {
                            return;
                        }
                        this.aiOutlineUploadBusy = true;
                        this.aiOutlineUploadProgress = 0;
                        const xhr = new XMLHttpRequest();
                        const fd = new FormData();
                        fd.append('_token', this.csrfToken);
                        fd.append('ai_outline_file', file);
                        xhr.open('POST', this.outlineSuggestTopicsUrl);
                        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.upload.onprogress = (ev) => {
                            if (ev.lengthComputable) {
                                this.aiOutlineUploadProgress = Math.round((ev.loaded / ev.total) * 100);
                            } else {
                                this.aiOutlineUploadProgress = null;
                            }
                        };
                        xhr.onload = () => {
                            this.aiOutlineUploadBusy = false;
                            let data = {};
                            try {
                                data = JSON.parse(xhr.responseText || '{}');
                            } catch (_) {}
                            if (xhr.status >= 200 && xhr.status < 300 && data.ok && Array.isArray(data.topics)) {
                                this.aiOutlineUploadMessage = '';
                                this.aiTopicTags = data.topics
                                    .filter((t) => typeof t === 'string' && t.trim() !== '')
                                    .filter((t, i, a) => a.indexOf(t) === i);
                                this.aiOutlineText = typeof data.outline_text === 'string' ? data.outline_text : '';
                                this.resetAiPrep();
                                this.aiOutlineUploadProgress = 100;
                                window.setTimeout(() => {
                                    this.aiOutlineUploadProgress = null;
                                }, 500);
                            } else {
                                this.aiOutlineUploadMessage =
                                    data.message || @json(__('Could not read topics from that file.'));
                                this.aiOutlineUploadProgress = null;
                            }
                        };
                        xhr.onerror = () => {
                            this.aiOutlineUploadBusy = false;
                            this.aiOutlineUploadMessage = @json(__('Upload failed. Try again.'));
                            this.aiOutlineUploadProgress = null;
                        };
                        xhr.send(fd);
                    },
                    resetAiPrep() {
                        this.aiPrepStopTimer();
                        this.aiPrepStartedAt = null;
                        this.aiPrepElapsedSec = 0;
                        this.aiPrepStatus = 'idle';
                        this.aiPrepProgress = 0;
                        this.aiPrepMessage = '';
                        this.aiPrepDoneCount = 0;
                        this.aiPrepTargetCount = 0;
                        this.aiPrepSections = [];
                        this.aiPrepAbort = false;
                    },
                    aiPrepStartTimer() {
                        this.aiPrepStartedAt = Date.now();
                        this.aiPrepElapsedSec = 0;
                        if (this.aiPrepTickHandle) {
                            clearInterval(this.aiPrepTickHandle);
                        }
                        this.aiPrepTickHandle = setInterval(() => {
                            if (this.aiPrepStartedAt) {
                                this.aiPrepElapsedSec = Math.round((Date.now() - this.aiPrepStartedAt) / 1000);
                            }
                        }, 1000);
                    },
                    aiPrepStopTimer() {
                        if (this.aiPrepTickHandle) {
                            clearInterval(this.aiPrepTickHandle);
                            this.aiPrepTickHandle = null;
                        }
                        if (this.aiPrepStartedAt) {
                            // Freeze the final elapsed value.
                            this.aiPrepElapsedSec = Math.round((Date.now() - this.aiPrepStartedAt) / 1000);
                        }
                    },
                    aiPrepElapsedLabel() {
                        const s = Math.max(0, this.aiPrepElapsedSec | 0);
                        if (s < 60) {
                            return s + 's';
                        }
                        const m = Math.floor(s / 60);
                        const r = s - m * 60;
                        return m + 'm ' + (r < 10 ? '0' : '') + r + 's';
                    },
                    aiPrepIsSlow() {
                        return this.aiPrepStatus === 'running' && this.aiPrepElapsedSec >= this.aiPrepSlowThresholdSec;
                    },
                    cancelPrepAndSwitchToImport() {
                        // Tell the in-flight loop to bail out at the next batch
                        // boundary, then move the lecturer over to the manual
                        // paste-JSON path. Topics already typed for the AI
                        // path are copied over so they don't lose work.
                        this.aiPrepAbort = true;
                        if (this.aiTopicTags && this.aiTopicTags.length > 0 && (!this.pasteTopicTags || this.pasteTopicTags.length === 0)) {
                            this.pasteTopicTags = this.aiTopicTags.slice();
                        }
                        this.aiPrepStopTimer();
                        // Keep already-generated sections so the lecturer can
                        // still resume later if they want; just clear the
                        // running-state UI so the page stops looking busy.
                        if (this.aiPrepStatus === 'running') {
                            this.aiPrepStatus = 'idle';
                            this.aiPrepMessage = '';
                        }
                        this.source = 'paste_json';
                    },
                    get aiPregeneratedJson() {
                        if (this.aiPrepStatus !== 'ready' || this.aiPrepSections.length === 0) {
                            return '';
                        }
                        return JSON.stringify({ sections: this.aiPrepSections });
                    },
                    selectedQuestionTypesForAi() {
                        // Reactive: reads the Alpine state array bound to the
                        // type-in-pool checkboxes via x-model. Toggling any
                        // checkbox now reactively updates everything that
                        // depends on this list (per-type counters, JSON
                        // validation, pool indicator).
                        const list = Array.isArray(this.selectedQuestionTypes) ? this.selectedQuestionTypes : [];
                        return list
                            .map((v) => String(v || '').trim().toLowerCase())
                            .filter((v) => v !== '');
                    },
                    aiQuestionTypesForAi() {
                        const form = document.getElementById('exam-create-form');
                        if (!form) {
                            return null;
                        }
                        const inputs = form.querySelectorAll('input[name="ai_question_types[]"]:checked');
                        if (!inputs.length) {
                            return null;
                        }
                        return Array.from(inputs)
                            .map((cb) => String(cb.value || '').trim().toLowerCase())
                            .filter((v) => v !== '');
                    },
                    async prepareAiQuestions() {
                        if (!this.aiGenerateBatchUrl) {
                            this.aiPrepStopTimer();
                            this.aiPrepStatus = 'error';
                            this.aiPrepMessage = @json(__('AI generation endpoint is not configured.'));
                            return;
                        }

                        this.aiTopicsAddFromInput();

                        const topicsString = this.aiTopicTags.join('\n').trim();
                        const outlineString = (this.aiOutlineText || '').trim();
                        if (topicsString === '' && outlineString === '') {
                            this.aiPrepStatus = 'error';
                            this.aiPrepMessage = @json(__('Add topics or upload an outline before generating.'));
                            return;
                        }

                        const selectedTypes = this.selectedQuestionTypesForAi();
                        if (selectedTypes.length === 0) {
                            this.aiPrepStatus = 'error';
                            this.aiPrepMessage = @json(__('Select at least one question type for the pool before generating.'));
                            return;
                        }

                        // Essay is manually graded — AI only generates the
                        // auto-gradable subset (intersection of selected pool
                        // types and {mcq, true_false, fill_blank}).
                        const aiTypes = this.aiEligibleTypes();
                        if (aiTypes.length === 0) {
                            this.aiPrepStatus = 'error';
                            this.aiPrepMessage = @json(__('AI generation needs at least one auto-gradable question type — MCQ, True/False, or Fill-in-the-blank. Add one to the pool to use AI.'));
                            return;
                        }

                        // Per-type targets and per-type tracking. The sum of
                        // remainingByType equals (total - producedCount) at
                        // every step, so each batch allocates from it without
                        // ever asking for essays or types not in aiTypes.
                        const targetByType = {};
                        aiTypes.forEach((t) => {
                            const n = parseInt(this.aiTypeCounts?.[t], 10);
                            targetByType[t] = !Number.isNaN(n) && n > 0 ? n : 0;
                        });
                        const targetSum = aiTypes.reduce((s, t) => s + targetByType[t], 0);
                        if (targetSum < 1) {
                            this.aiPrepStatus = 'error';
                            this.aiPrepMessage = @json(__('Set at least 1 question for an enabled question type before generating.'));
                            return;
                        }

                        const batchSize = Math.max(1, Math.min(20, this.aiGenerateBatchSize));

                        // Effective total target = per-type sum (capped at 250
                        // to match server validation).
                        const total = Math.max(1, Math.min(250, targetSum));

                        // If the last run failed mid-way and we still have the
                        // partial sections, resume from where we left off so
                        // the lecturer doesn't lose minutes of progress to one
                        // flaky batch.
                        const isResume =
                            this.aiPrepStatus === 'error' &&
                            this.aiPrepSections.length > 0 &&
                            this.aiPrepTargetCount === total;

                        const collectedSections = isResume ? this.aiPrepSections.slice() : [];
                        const existingTexts = [];
                        // producedByType tracks how many of each type we've
                        // already generated so we can allocate the next batch
                        // proportional to what's still missing.
                        const producedByType = { mcq: 0, true_false: 0, fill_blank: 0 };
                        let producedCount = 0;
                        if (isResume) {
                            collectedSections.forEach((section) => {
                                if (!section || !Array.isArray(section.questions)) {
                                    return;
                                }
                                section.questions.forEach((q) => {
                                    if (q && typeof q.question_text === 'string') {
                                        const norm = q.question_text.trim().toLowerCase();
                                        if (norm !== '' && !existingTexts.includes(norm)) {
                                            existingTexts.push(norm);
                                        }
                                        producedCount += 1;
                                        const qt = typeof q.type === 'string' ? q.type.toLowerCase() : '';
                                        if (Object.prototype.hasOwnProperty.call(producedByType, qt)) {
                                            producedByType[qt] += 1;
                                        }
                                    }
                                });
                            });
                        } else {
                            this.aiPrepSections = [];
                        }

                        this.aiPrepDoneCount = producedCount;
                        this.aiPrepTargetCount = total;
                        this.aiPrepProgress = total > 0 ? Math.round((producedCount / total) * 100) : 0;
                        this.aiPrepMessage = '';
                        this.aiPrepStatus = 'running';
                        this.aiPrepAbort = false;
                        this.aiPrepStartTimer();

                        // Allocate up to `slotsLeft` questions across the
                        // currently-undersupplied types proportional to what
                        // each one still needs. Returns an object like
                        // { mcq: 5, true_false: 3, fill_blank: 2 } summing to
                        // exactly `slotsLeft` (or to the remaining total, if
                        // less than `slotsLeft`).
                        const allocateBatch = (slotsLeft) => {
                            const remaining = {};
                            aiTypes.forEach((t) => {
                                remaining[t] = Math.max(0, targetByType[t] - (producedByType[t] || 0));
                            });
                            const eligible = aiTypes.filter((t) => remaining[t] > 0);
                            if (eligible.length === 0) {
                                return {};
                            }
                            const totalRemaining = eligible.reduce((s, t) => s + remaining[t], 0);
                            const want = Math.max(1, Math.min(slotsLeft, totalRemaining));
                            const out = {};
                            let allocated = 0;
                            eligible.forEach((t) => {
                                const share = Math.floor(want * remaining[t] / totalRemaining);
                                out[t] = Math.min(share, remaining[t]);
                                allocated += out[t];
                            });
                            // Distribute leftover by largest unfilled-need first.
                            let leftover = want - allocated;
                            const queue = eligible.slice().sort((a, b) => (remaining[b] - out[b]) - (remaining[a] - out[a]));
                            while (leftover > 0 && queue.length > 0) {
                                const t = queue.shift();
                                if (out[t] < remaining[t]) {
                                    out[t] += 1;
                                    leftover -= 1;
                                    queue.push(t);
                                }
                            }
                            return out;
                        };

                        // The server is allowed to drop duplicate/malformed
                        // individual questions, so a batch may return fewer
                        // than batchSize. We loop until the target is met
                        // instead of capping at totalBatches. A safety cap
                        // (3× expected) prevents runaway loops if the model
                        // keeps producing pure duplicates.
                        const expectedBatches = Math.max(1, Math.ceil(total / batchSize));
                        const maxIters = Math.max(20, expectedBatches * 3);

                        let iter = 0;
                        let batchIndex = Math.floor(producedCount / batchSize);
                        let consecutiveEmptyBatches = 0;

                        while (producedCount < total && iter < maxIters) {
                            iter++;
                            batchIndex++;

                            if (this.aiPrepAbort) {
                                this.aiPrepStopTimer();
                                this.aiPrepStatus = 'error';
                                this.aiPrepMessage = @json(__('Cancelled.'));
                                return;
                            }

                            const remaining = total - producedCount;
                            const slotsLeft = Math.max(1, Math.min(batchSize, remaining));
                            const batchAlloc = allocateBatch(slotsLeft);
                            const thisBatch = Object.values(batchAlloc).reduce((s, n) => s + n, 0);
                            if (thisBatch === 0) {
                                // Already met all per-type targets — done.
                                break;
                            }

                            const fd = new FormData();
                            fd.append('_token', this.csrfToken);
                            fd.append('ai_topics', topicsString);
                            if (outlineString !== '') {
                                fd.append('ai_outline_text', outlineString);
                            }
                            selectedTypes.forEach((t) => fd.append('selected_question_types[]', t));
                            // Tell the server exactly which auto-grade types to
                            // include in the prompt; essay is never in this list.
                            aiTypes.forEach((t) => fd.append('ai_question_types[]', t));
                            // Per-batch breakdown — the prompt builder uses
                            // these counts to ask for an exact mix and the
                            // controller validates sum === batch_count.
                            Object.keys(batchAlloc).forEach((t) => {
                                fd.append('ai_type_counts[' + t + ']', String(batchAlloc[t]));
                            });
                            fd.append('ai_difficulty', 'moderate');
                            fd.append('ai_marks', '1');
                            fd.append('batch_count', String(thisBatch));
                            fd.append('batch_index', String(batchIndex));
                            fd.append('total_count', String(total));
                            existingTexts.slice(-200).forEach((t) => {
                                fd.append('existing_question_texts[]', t);
                            });

                            let resp;
                            try {
                                resp = await fetch(this.aiGenerateBatchUrl, {
                                    method: 'POST',
                                    headers: {
                                        Accept: 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: fd,
                                    credentials: 'same-origin',
                                });
                            } catch (err) {
                                this.aiPrepStopTimer();
                                this.aiPrepStatus = 'error';
                                this.aiPrepMessage = @json(__('Network error while contacting the AI. Try again.'));
                                return;
                            }

                            let data = null;
                            try {
                                data = await resp.json();
                            } catch (_) {
                                data = null;
                            }

                            if (!resp.ok || !data || data.ok !== true || !Array.isArray(data.sections)) {
                                const errs = data && Array.isArray(data.errors) ? data.errors.join(' ') : '';
                                this.aiPrepStopTimer();
                                this.aiPrepStatus = 'error';
                                this.aiPrepMessage =
                                    errs || @json(__('The AI provider returned an error. Try again or change topics.'));
                                return;
                            }

                            let acceptedThisBatch = 0;
                            data.sections.forEach((section) => {
                                if (!section || typeof section !== 'object') {
                                    return;
                                }
                                const cloned = {
                                    title: typeof section.title === 'string' ? section.title : '',
                                    questions: Array.isArray(section.questions) ? section.questions.slice() : [],
                                };
                                cloned.questions.forEach((q) => {
                                    if (q && typeof q.question_text === 'string') {
                                        const norm = q.question_text.trim().toLowerCase();
                                        if (norm !== '' && !existingTexts.includes(norm)) {
                                            existingTexts.push(norm);
                                        }
                                        producedCount += 1;
                                        acceptedThisBatch += 1;
                                        const qt = typeof q.type === 'string' ? q.type.toLowerCase() : '';
                                        if (Object.prototype.hasOwnProperty.call(producedByType, qt)) {
                                            producedByType[qt] += 1;
                                        }
                                    }
                                });
                                if (cloned.questions.length > 0) {
                                    collectedSections.push(cloned);
                                }
                            });

                            this.aiPrepSections = collectedSections.slice();
                            this.aiPrepDoneCount = Math.min(total, producedCount);
                            this.aiPrepProgress = Math.min(
                                100,
                                Math.round((this.aiPrepDoneCount / total) * 100),
                            );

                            // Detect "model only emits duplicates" so we don't
                            // loop forever burning AI credits.
                            if (acceptedThisBatch === 0) {
                                consecutiveEmptyBatches += 1;
                                if (consecutiveEmptyBatches >= 3) {
                                    this.aiPrepStopTimer();
                                    this.aiPrepStatus = 'error';
                                    this.aiPrepMessage = @json(
                                        __('The AI kept returning duplicates of earlier questions. Try changing topics or generating fewer questions.')
                                    );
                                    return;
                                }
                            } else {
                                consecutiveEmptyBatches = 0;
                            }
                        }

                        this.aiPrepDoneCount = Math.min(total, producedCount);
                        this.aiPrepProgress = producedCount >= total ? 100 : this.aiPrepProgress;

                        if (producedCount < total) {
                            this.aiPrepStopTimer();
                            this.aiPrepStatus = 'error';
                            this.aiPrepMessage = @json(__('AI returned fewer questions than requested. Try regenerating or reducing the count.'));
                            return;
                        }

                        this.aiPrepStopTimer();
                        this.aiPrepStatus = 'ready';
                        this.aiPrepMessage = '';
                    },
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
                        const topics =
                            this.pasteTopicTags.length > 0 ? this.pasteTopicTags.join(', ') : 'General knowledge';
                        const eligible = this.aiEligibleTypes();
                        const counts = this.aiTypeCounts || {};
                        let total = this.aiQuestionCount;
                        if (Number.isNaN(total) || total < 1) {
                            total = 10;
                        }
                        total = Math.min(250, total);

                        // Special case: MCQ-only — keep the simple flat array
                        // format the validator already understands so legacy
                        // ChatGPT prompts keep working out of the box.
                        const onlyMcq =
                            eligible.length === 1 && eligible[0] === 'mcq';

                        if (onlyMcq) {
                            const example =
                                '[{"text":"Your question here?","options":{"A":"...","B":"...","C":"...","D":"..."},"correct":"A","topic":"..."}]';
                            return [
                                'Use ONLY these precise topics—do not add or substitute others: ' + topics + '.',
                                'Generate exactly ' +
                                    total +
                                    ' multiple choice quiz questions (MCQ) that clearly align with these topics. Difficulty: moderate.',
                                'For each question provide: question text, exactly 4 options (A, B, C, D), and exactly one correct answer (one letter). Do not include explanations.',
                                'Output format: reply with a JSON array only, no other text before or after.',
                                'Each item in the array must have: "text" (question text), "options" (object with keys "A", "B", "C", "D"), "correct" (one letter A–D), "topic" (one of the listed topics).',
                                'Example shape: ' + example,
                            ].join('\n');
                        }

                        // Multi-type or non-MCQ: emit the full sections schema
                        // QUIZSNAP supports — the validator will read it as-is.
                        const breakdown = eligible
                            .filter((t) => (parseInt(counts[t], 10) || 0) > 0)
                            .map((t) => (parseInt(counts[t], 10) || 0) + ' ' + this.aiTypeLabel(t))
                            .join(', ');
                        const example = JSON.stringify({
                            sections: [
                                {
                                    title: 'Topic 1',
                                    questions: [
                                        {
                                            type: 'mcq',
                                            question_text: 'Your question?',
                                            marks: 1,
                                            options: ['A', 'B', 'C', 'D'],
                                            correct_answer: 'A',
                                            topic: 'Topic 1',
                                        },
                                        {
                                            type: 'true_false',
                                            question_text: 'Statement to evaluate.',
                                            marks: 1,
                                            correct_answer: true,
                                            topic: 'Topic 1',
                                        },
                                        {
                                            type: 'fill_blank',
                                            question_text: 'The capital of France is ___.',
                                            marks: 1,
                                            correct_answer: ['Paris'],
                                            topic: 'Topic 1',
                                        },
                                    ],
                                },
                            ],
                        });
                        return [
                            'Use ONLY these precise topics—do not add or substitute others: ' + topics + '.',
                            'Generate EXACTLY this breakdown of questions, no more and no fewer of any type: ' + breakdown + '. Total = ' + total + '.',
                            'Difficulty: moderate. Default marks per question: 1.',
                            'Do not include any essay or open-ended manually-graded questions. Only the listed auto-gradable types.',
                            'Output format: reply with ONE JSON object only, no markdown fences, no commentary.',
                            'Schema rules:',
                            '- Root object has one key: "sections" (array).',
                            '- Each section has "title" (string) and "questions" (array).',
                            '- MCQ questions: type="mcq", non-empty "options" (string array, at least 2, distinct), correct_answer is exact option text OR a zero-based index.',
                            '- True/False: type="true_false", correct_answer is true or false (boolean).',
                            '- Fill-in-the-blank: type="fill_blank", question_text uses ___ for each blank, correct_answer is an array of strings (one per blank).',
                            '- Add "topic" on each question and use only the listed topics.',
                            'Example shape (mix is illustrative — emit the EXACT breakdown above): ' + example,
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
                        this.importValidateOk = false;
                        this.importValidateBusy = true;

                        // Client-side pre-checks. Catches the three most
                        // common mistakes (empty paste, unparseable JSON,
                        // pool < questions_per_student) without a server
                        // round-trip, so the lecturer gets instant feedback.
                        try {
                            const raw = (this.importJsonDraft || '').trim();
                            if (raw === '') {
                                this.importValidateOk = false;
                                this.importValidateMessage = @json(__('Paste your JSON in the box above first, then try again.'));
                                this.importValidateBusy = false;
                                return;
                            }
                            const counted = countPoolFromImportJson(raw);
                            if (counted.invalid) {
                                this.importValidateOk = false;
                                this.importValidateMessage = @json(__('That doesn’t look like valid JSON. Open it in a JSON viewer (or copy the prompt above and re-run your generator) and try again.'));
                                this.importValidateBusy = false;
                                return;
                            }
                            if (counted.count === 0) {
                                this.importValidateOk = false;
                                this.importValidateMessage = @json(__('The JSON parsed, but no questions were found. Each section should contain a "questions" array with one entry per question.'));
                                this.importValidateBusy = false;
                                return;
                            }

                            // Type mismatch — surface this client-side too
                            // because the wording is more actionable than
                            // the server’s per-question error list.
                            const selectedTypes = this.selectedQuestionTypesForAi();
                            if (selectedTypes.length > 0) {
                                const offending = Object.keys(counted.breakdown).filter(
                                    (t) => counted.breakdown[t] > 0 && !selectedTypes.includes(t),
                                );
                                if (offending.length > 0) {
                                    const offendingLabel = offending.map((t) => this.aiTypeLabel(t)).join(', ');
                                    this.importValidateOk = false;
                                    this.importValidateMessage = @json(__('The JSON contains :type question(s) that are not in your selected pool types. Either tick them in “Question types in pool” above, or remove those questions from the JSON.')).replace(':type', offendingLabel);
                                    this.importValidateBusy = false;
                                    return;
                                }
                            }

                            const perStudent = parseInt(this.questionsPerStudent, 10) || 0;
                            if (perStudent > 0 && counted.count < perStudent) {
                                this.importValidateOk = false;
                                this.importValidateMessage = @json(__('Pool too small: your JSON has :pool question(s) but “Questions per student” is set to :per. Add more questions to the JSON or lower the per-student count.'))
                                    .replace(':pool', counted.count)
                                    .replace(':per', perStudent);
                                this.importValidateBusy = false;
                                return;
                            }

                            // Server validation — also passes the live selected
                            // types and the per-student target so error
                            // messages stay consistent across paths.
                            const body = new FormData();
                            body.append('_token', this.csrfToken);
                            body.append('import_json', raw);
                            selectedTypes.forEach((t) => body.append('selected_question_types[]', t));
                            if (perStudent > 0) {
                                body.append('questions_per_student', String(perStudent));
                            }
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
