<x-layouts.coordinator>
    <x-slot name="title">Exam builder</x-slot>
    <x-slot name="subtitle">{{ $exam->title }}</x-slot>

    <div class="mb-6 flex flex-wrap gap-3 text-sm text-gray-600">
        <span>Course: <strong class="text-gray-900">{{ $exam->course?->code }}</strong></span>
        <span>Duration: <strong class="text-gray-900">{{ $exam->duration_minutes }} min</strong></span>
        <span>Total marks: <strong class="text-gray-900">{{ $exam->total_marks }}</strong></span>
    </div>

    <div class="mb-8 rounded-xl border border-beige bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-sage mb-3">Add section</h3>
        <form method="post" action="{{ route('examiner.exams.sections.store', $exam) }}" class="flex flex-wrap gap-2 items-end">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-600 mb-1">Section title</label>
                <input type="text" name="title" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="e.g. Section A" />
            </div>
            <button type="submit" class="rounded-lg bg-sage px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Add section</button>
        </form>
    </div>

    @forelse ($exam->sections as $section)
        <div class="mb-10 rounded-xl border border-beige bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4 border-b border-beige pb-3">
                <h3 class="text-lg font-semibold text-sage">{{ $section->title }}</h3>
                <span class="text-xs text-gray-500">Order {{ $section->section_order }}</span>
            </div>

            @foreach ($section->questions as $q)
                <div class="mb-4 rounded-lg bg-beige/40 p-4 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="font-medium text-gray-800">{{ $loop->iteration }}. {{ $q->type }}</span>
                        <span class="text-gray-600">{{ $q->marks }} pts</span>
                    </div>
                    <p class="mt-2 text-gray-800 whitespace-pre-wrap">{{ $q->question_text }}</p>
                    @if ($q->isMCQ() && is_array($q->options))
                        <ul class="mt-2 list-disc list-inside text-gray-700">
                            @foreach ($q->options as $idx => $opt)
                                <li>{{ $idx }}: {{ $opt }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

            <div class="mt-4 border-t border-beige pt-4">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">New question in this section</h4>
                <form method="post" action="{{ route('examiner.exams.questions.store', [$exam, $section]) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Type</label>
                        <select name="type" required class="qs-qtype w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            @foreach ($questionTypes as $qt)
                                <option value="{{ $qt }}">{{ $qt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Question text</label>
                        <textarea name="question_text" rows="3" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Marks</label>
                        <input type="number" name="marks" value="1" step="0.01" min="0" required class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm" />
                    </div>

                    <div class="qs-block qs-mcq space-y-2 border border-dashed border-gray-300 rounded-lg p-3">
                        <p class="text-xs text-gray-600">MCQ: enter options, tick correct answer(s).</p>
                        @for ($i = 0; $i < 6; $i++)
                            <div class="flex gap-2 items-center">
                                <span class="text-xs w-6 text-gray-500">{{ $i }}</span>
                                <input type="text" name="options[]" class="flex-1 rounded border border-gray-300 px-2 py-1 text-sm" placeholder="Option {{ $i }}" />
                                <label class="text-xs flex items-center gap-1 whitespace-nowrap">
                                    <input type="checkbox" name="correct_mcq[]" value="{{ $i }}" /> correct
                                </label>
                            </div>
                        @endfor
                    </div>

                    <div class="qs-block qs-tf hidden space-y-2 border border-dashed border-gray-300 rounded-lg p-3">
                        <p class="text-xs text-gray-600">Correct answer</p>
                        <select name="correct_true_false" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="1">True</option>
                            <option value="0">False</option>
                        </select>
                    </div>

                    <div class="qs-block qs-fb hidden space-y-2 border border-dashed border-gray-300 rounded-lg p-3">
                        <p class="text-xs text-gray-600">Acceptable answers (one per line, matched in order for multiple blanks).</p>
                        <textarea name="correct_blanks" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Answer line 1&#10;Answer line 2"></textarea>
                    </div>

                    <div class="qs-block qs-essay hidden border border-dashed border-gray-300 rounded-lg p-3 text-xs text-gray-600">
                        Essay questions are graded manually after submission.
                    </div>

                    <button type="submit" class="rounded-lg bg-camel px-4 py-2 text-sm font-semibold text-white hover:bg-camel/90">Save question</button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-600">Add at least one section, then add questions per section.</p>
    @endforelse

    <div class="mt-8">
        <a href="{{ route('examiner.exams.index') }}" class="text-sm font-medium text-sage hover:underline">← Back to exams</a>
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
