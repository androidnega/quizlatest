<x-layouts.student>
    <x-slot name="title">{{ __('Generate practice quiz') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6 py-2">
            @if ($errors->any())
                <div class="rounded-xl border border-qs-danger/35 bg-qs-danger-soft px-4 py-3 text-sm text-qs-danger">
                    <ul class="list-disc ps-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($materialRows->isEmpty())
                <p class="text-sm text-qs-muted">{{ __('You need an enrolled class with linked courses and at least one uploaded material.') }}</p>
            @else
                <form method="POST" action="{{ route('student.practice.quizzes.store') }}" class="space-y-5 rounded-xl border border-qs-soft bg-qs-bg p-6">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-qs-muted">{{ __('Material') }}</label>
                        <select name="course_material_id" required class="qs-input mt-2 w-full py-2.5">
                            @foreach ($materialRows as $m)
                                <option value="{{ $m->id }}">{{ $m->course?->code ?? '' }} — {{ $m->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-qs-muted">{{ __('Question style') }}</label>
                            <select name="quiz_type" class="qs-input mt-2 w-full py-2.5">
                                <option value="mixed">{{ __('Mixed') }}</option>
                                <option value="mcq">{{ __('Multiple choice') }}</option>
                                <option value="true_false">{{ __('True / false') }}</option>
                                <option value="fill_blank">{{ __('Fill in the blank') }}</option>
                                <option value="essay">{{ __('Includes essays') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-qs-muted">{{ __('Difficulty') }}</label>
                            <select name="difficulty" class="qs-input mt-2 w-full py-2.5">
                                <option value="easy">{{ __('Easy') }}</option>
                                <option value="medium" selected>{{ __('Medium') }}</option>
                                <option value="hard">{{ __('Hard') }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-qs-muted">{{ __('Number of questions') }}</label>
                        <input type="number" name="question_count" min="1" max="30" value="5" required class="qs-input mt-2 w-full max-w-xs py-2.5" />
                    </div>
                    <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Generate') }}</button>
                </form>
            @endif
    </div>
</x-layouts.student>
