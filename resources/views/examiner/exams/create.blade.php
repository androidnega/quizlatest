<x-layouts.examiner>
    <x-slot name="title">{{ __('Create Assessment') }}</x-slot>
    <x-slot name="subtitle">{{ __('Start with the basic assessment details. You will add sections, questions, rules, and publishing settings in the builder.') }}</x-slot>

    <div
        x-data="{ step: 1, maxStep: 4 }"
        class="w-full max-w-none rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm"
    >
        <div class="mb-5">
            <div class="mb-2 flex flex-wrap gap-2">
                <span :class="step >= 1 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 1') }} · {{ __('Scope') }}</span>
                <span :class="step >= 2 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 2') }} · {{ __('Type & title') }}</span>
                <span :class="step >= 3 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 3') }} · {{ __('Timing') }}</span>
                <span :class="step >= 4 ? 'bg-emerald-100 text-emerald-900 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'" class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold">{{ __('Step 4') }} · {{ __('Proctoring') }}</span>
            </div>
            <p class="text-xs text-slate-500">{{ __('Use Next to complete the setup quickly, then continue in Builder.') }}</p>
        </div>

        <form method="post" action="{{ route('examiner.exams.store') }}" class="space-y-4">
            @csrf
            <section x-show="step === 1" x-cloak class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Course') }}</label>
                    <select name="course_id" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm">
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected((int) old('course_id') === (int) $course->id)>{{ $course->code }} — {{ $course->title }}</option>
                        @endforeach
                    </select>
                    @error('course_id')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section x-show="step === 2" x-cloak class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Assessment type') }}</label>
                    <select name="assessment_type" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm">
                        @foreach (['quiz' => 'Quiz', 'mid' => 'Mid', 'exam' => 'Exam', 'assignment' => 'Assignment'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('assessment_type', 'exam') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('assessment_type')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                    @error('title')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Description (optional)') }}</label>
                    <textarea name="description" rows="4" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" placeholder="{{ __('Any extra instructions or context for this assessment.') }}">{{ old('description') }}</textarea>
                </div>
            </section>

            <section x-show="step === 3" x-cloak class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Duration (minutes)') }}</label>
                    <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 30) }}" min="1" max="600" required class="w-full max-w-sm rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                </div>
                <p class="text-xs text-slate-500">{{ __('You are creating a draft shell. Sections, questions, rules, and publish settings come next in Builder.') }}</p>
            </section>

            <section x-show="step === 4" x-cloak class="space-y-4">
                <p class="text-xs text-slate-500">{{ __('Choose allowed proctoring options for this assessment. These options are capped by super admin policy.') }}</p>
                <label class="inline-flex min-h-[44px] w-full items-center gap-2 rounded-lg border border-qs-soft px-3 py-2 text-sm text-qs-text">
                    <input type="checkbox" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                        @checked($proctoringPolicy['allow_exam_start_snapshot']) @disabled(true) />
                    {{ __('Exam start verification photo') }}
                    <span class="ml-auto text-[11px] text-qs-muted">{{ $proctoringPolicy['allow_exam_start_snapshot'] ? __('Required by admin') : __('Disabled by admin') }}</span>
                </label>
                <label class="inline-flex min-h-[44px] w-full items-center gap-2 rounded-lg border border-qs-soft px-3 py-2 text-sm text-qs-text">
                    <input type="checkbox" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                        @checked($proctoringPolicy['allow_camera_monitoring']) @disabled(true) />
                    {{ __('Proctoring camera during exam') }}
                    <span class="ml-auto text-[11px] text-qs-muted">{{ $proctoringPolicy['allow_camera_monitoring'] ? __('Required by admin') : __('Disabled by admin') }}</span>
                </label>
                <label class="inline-flex min-h-[44px] w-full items-center gap-2 rounded-lg border border-qs-soft px-3 py-2 text-sm text-qs-text">
                    <input type="checkbox" name="enable_phone" value="1" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                        @checked(old('enable_phone', true)) @disabled(! $proctoringPolicy['allow_phone']) />
                    {{ __('Phone detection') }}
                </label>
                <label class="inline-flex min-h-[44px] w-full items-center gap-2 rounded-lg border border-qs-soft px-3 py-2 text-sm text-qs-text">
                    <input type="checkbox" name="enable_fullscreen" value="1" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                        @checked(old('enable_fullscreen', true)) @disabled(! $proctoringPolicy['allow_fullscreen']) />
                    {{ __('Fullscreen enforcement') }}
                </label>
                <label class="inline-flex min-h-[44px] w-full items-center gap-2 rounded-lg border border-qs-soft px-3 py-2 text-sm text-qs-text">
                    <input type="checkbox" name="enable_auto_submit" value="1" class="size-4 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                        @checked(old('enable_auto_submit', true)) @disabled(! $proctoringPolicy['allow_auto_submit']) />
                    {{ __('Auto submit on severe violations') }}
                </label>
            </section>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="button" x-show="step > 1" @click="step = Math.max(1, step - 1)" class="rounded-lg border border-qs-soft px-4 py-2 text-sm font-semibold text-qs-muted hover:bg-qs-card">
                    {{ __('Back') }}
                </button>
                <button type="button" x-show="step < maxStep" @click="step = Math.min(maxStep, step + 1)" class="rounded-lg border border-qs-soft bg-white px-4 py-2 text-sm font-semibold text-qs-text hover:bg-qs-card">
                    {{ __('Next') }}
                </button>
                <button type="submit" x-show="step === maxStep" class="rounded-lg bg-qs-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-95">
                    {{ __('Save & Continue to Builder') }}
                </button>
                <a href="{{ route('examiner.exams.index') }}" class="rounded-lg border border-qs-soft px-4 py-2 text-sm font-semibold text-qs-muted hover:bg-qs-card">
                    {{ __('Cancel') }}
                </a>
            </div>
        </form>
    </div>
</x-layouts.examiner>
