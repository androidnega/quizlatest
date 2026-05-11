@php($staffAcademicPeriodBadge = null)
<x-layouts.examiner>
    <x-slot name="title">{{ __('Create assessment') }}</x-slot>
    <x-slot name="subtitle">{{ __('Set up class groups, optional JSON import, and scheduling. You can refine everything later in the builder.') }}</x-slot>

    <div
        class="w-full max-w-none space-y-6 rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm sm:p-6 lg:p-8"
        x-data='{
            rows: @json($classroomOptions),
            courseId: {{ (int) (old('course_id', request('course_id')) ?: ($courses->first()->id ?? 0)) }},
            selectedClassIds: @json(array_values(array_map('intval', old('classroom_ids', [])))),
            source: @json(old('question_source', 'later')),
            filteredClassrooms() {
                const cid = parseInt(this.courseId, 10) || 0;
                if (!cid) return [];
                return this.rows.filter((r) => (r.course_ids || []).includes(cid));
            },
            syncClassroomInputs() {
                const mount = document.getElementById("classroom-ids-mount");
                if (!mount) return;
                mount.innerHTML = "";
                this.selectedClassIds.forEach((id) => {
                    const inp = document.createElement("input");
                    inp.type = "hidden";
                    inp.name = "classroom_ids[]";
                    inp.value = String(id);
                    mount.appendChild(inp);
                });
            }
        }'
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

        <form method="post" action="{{ route('examiner.exams.store') }}" class="space-y-8" @submit="syncClassroomInputs()">
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
                        <input type="number" name="questions_per_student" value="{{ old('questions_per_student', 10) }}" min="1" max="500" class="qs-input mt-1 w-full py-2.5" :required="source === 'paste_json'" />
                        <p class="mt-1 text-xs text-qs-muted">{{ __('Required when importing JSON. Each student draws this many from the approved pool.') }}</p>
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
                <p class="text-xs leading-snug text-qs-muted">{{ __('By default the assessment is saved as a draft with no questions yet—you add them in the workspace. Optionally paste builder JSON now.') }}</p>
                <input type="hidden" name="question_source" :value="source" />
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="source === 'later' ? 'border-qs-accent bg-qs-accent/10' : 'border-qs-soft bg-white'">
                        <input type="radio" value="later" x-model="source" class="size-4 border-qs-soft text-qs-accent" />
                        <span class="font-medium text-qs-text">{{ __('Later') }}</span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm" :class="source === 'paste_json' ? 'border-qs-accent bg-qs-accent/10' : 'border-qs-soft bg-white'">
                        <input type="radio" value="paste_json" x-model="source" class="size-4 border-qs-soft text-qs-accent" />
                        {{ __('Paste JSON') }}
                    </label>
                </div>

                <div x-show="source === 'paste_json'" x-cloak class="space-y-3">
                    <p class="text-xs leading-relaxed text-qs-muted">
                        {{ __('Paste the same JSON shape used in the assessment builder (root object with a sections array). You can also paste a flat ChatGPT-style MCQ array; it is converted on save.') }}
                    </p>
                    <label class="mb-1 block text-sm font-medium text-qs-muted" for="import-json-field">{{ __('Question JSON') }}</label>
                    <textarea
                        id="import-json-field"
                        name="import_json"
                        rows="14"
                        class="w-full rounded-lg border border-qs-soft bg-white px-3 py-2 font-mono text-xs text-qs-text placeholder:text-qs-muted"
                        placeholder='{"sections":[{"title":"Section A","questions":[...]}]}'
                    >{{ old('import_json') }}</textarea>
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
</x-layouts.examiner>
