<x-layouts.coordinator>
    <x-slot name="title">{{ __('Assign Examiners to Course') }}</x-slot>
    <x-slot name="subtitle">{{ __('Select a course and assign one or more examiners.') }}</x-slot>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
        <form method="GET" action="{{ route('coordinator.courses.examiners.edit') }}" class="grid gap-3 sm:grid-cols-4 sm:items-end">
            <div class="sm:col-span-3">
                <label for="course_id" class="block text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Course') }}</label>
                <select id="course_id" name="course_id" class="qs-input mt-1 py-2">
                    <option value="">{{ __('Select course') }}</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((int) request('course_id', $selectedCourse?->id) === $course->id)>
                            {{ $course->code }} — {{ $course->title }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex sm:block">
                <button type="submit" class="qs-btn-secondary min-h-[38px] w-full text-sm font-semibold">{{ __('Load') }}</button>
            </div>
        </form>
    </div>

    @if ($selectedCourse)
        <div class="mt-4 rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <div class="mb-3 rounded-lg border border-qs-soft bg-qs-card px-3 py-2.5 text-sm text-qs-muted">
                <span class="font-semibold text-qs-text">{{ __('Selected course') }}:</span>
                {{ $selectedCourse->code }} — {{ $selectedCourse->title }}
            </div>

            <form method="POST" action="{{ route('coordinator.courses.examiners.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="course_id" value="{{ $selectedCourse->id }}">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Examiners') }}</label>
                    <div class="mt-2 max-h-64 overflow-y-auto rounded-lg border border-qs-soft p-2">
                        @forelse ($examiners as $examiner)
                            <label class="flex min-h-[36px] cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm text-qs-muted hover:bg-qs-card">
                                <input
                                    type="checkbox"
                                    name="examiner_ids[]"
                                    value="{{ $examiner->id }}"
                                    @checked(in_array($examiner->id, old('examiner_ids', $assignedExaminerIds), true))
                                    class="size-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40"
                                />
                                <span class="leading-snug">
                                    <span class="font-medium text-qs-text">{{ $examiner->name }}</span>
                                    @if ($examiner->email)
                                        <span class="text-xs text-qs-muted"> — {{ $examiner->email }}</span>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="px-2 py-4 text-center text-sm text-qs-muted">{{ __('No active examiners found in this university.') }}</p>
                        @endforelse
                    </div>
                    @error('examiner_ids')
                        <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                    @enderror
                    @error('examiner_ids.*')
                        <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col-reverse gap-2.5 pt-1 sm:flex-row sm:justify-end">
                    <a href="{{ route('dashboard') }}" class="qs-btn-secondary inline-flex min-h-[38px] items-center justify-center px-4 text-sm font-semibold">{{ __('Back') }}</a>
                    <button type="submit" class="qs-btn-primary min-h-[38px] px-4 text-sm font-semibold">{{ __('Save assignments') }}</button>
                </div>
            </form>
        </div>
    @endif
</x-layouts.coordinator>
