@php
    /** @var \App\Models\Quiz $exam */
    $isAssignment = $exam->isAssignment();
@endphp
@if ($isAssignment)
    <section class="rounded-xl border border-sky-200 bg-sky-50/60 p-4 shadow-sm sm:p-5" aria-labelledby="assignment-coursework-heading">
        <h2 id="assignment-coursework-heading" class="text-sm font-semibold text-slate-900">{{ __('Coursework (assignment)') }}</h2>
        <p class="mt-1 text-xs leading-relaxed text-slate-700">
            {{ __('This assessment is coursework: students type responses in the app with copy and paste blocked for text fields. Live camera proctoring stays off unless your institution later adds an explicit exception.') }}
        </p>
        <dl class="mt-4 grid gap-2 text-xs text-slate-800 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                <dt class="font-medium text-slate-500">{{ __('Due') }}</dt>
                <dd class="mt-0.5 font-semibold text-slate-900">
                    {{ $exam->due_at?->timezone(config('app.timezone'))->format('M j, Y · H:i') ?? '—' }}
                </dd>
            </div>
            <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                <dt class="font-medium text-slate-500">{{ __('Grades visible to students') }}</dt>
                <dd class="mt-0.5 font-semibold text-slate-900">
                    @if ($exam->grades_released_at)
                        {{ __('Yes') }} · {{ $exam->grades_released_at->timezone(config('app.timezone'))->format('M j, Y · H:i') }}
                    @else
                        {{ __('Not yet — release when ready') }}
                    @endif
                </dd>
            </div>
        </dl>

        @if ($canEditSchedule)
            <form method="post" action="{{ route('examiner.exams.schedule.update', $exam) }}" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-white p-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-title">{{ __('Title') }}</label>
                    <input id="assignment-title" type="text" name="title" value="{{ old('title', $exam->title) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-instructions">{{ __('Instructions') }}</label>
                    <textarea id="assignment-instructions" name="description" rows="5" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>{{ old('description', $exam->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-due">{{ __('Due date') }}</label>
                    <input id="assignment-due" type="datetime-local" name="due_at" value="{{ old('due_at', $exam->due_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}" class="mt-1 w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">{{ __('Save coursework details') }}</button>
                </div>
            </form>
        @endif

        @if ($exam->status === 'published' && ! $exam->grades_released_at)
            <form method="post" action="{{ route('examiner.exams.release-assignment-grades', $exam) }}" class="mt-4" onsubmit="return confirm(@js(__('Release grades and feedback to students for this assignment?')));">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                    {{ __('Release grades to students') }}
                </button>
            </form>
        @endif

        <p class="mt-3 text-[11px] leading-relaxed text-slate-600">
            {{ __('Typed responses: copy and paste is blocked by default. Manual grading and per-question feedback use the same essay grading queue as other assessments.') }}
        </p>
    </section>
@endif
