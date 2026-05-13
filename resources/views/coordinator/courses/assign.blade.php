<x-layouts.coordinator>
    <x-slot name="title">Assign Courses to Class</x-slot>
    <x-slot name="subtitle">Select a class and assign multiple courses in the same department</x-slot>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
        <form method="GET" action="{{ route('coordinator.courses.assign.edit') }}" class="grid gap-3 sm:grid-cols-4 sm:items-end">
            <div class="sm:col-span-3">
                <label for="class_id" class="block text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Class') }}</label>
                <select id="class_id" name="class_id" class="qs-input mt-1 py-2">
                    <option value="">{{ __('Select class') }}</option>
                    @foreach ($classes as $classroom)
                        <option value="{{ $classroom->id }}" {{ (int) request('class_id', $selectedClass?->id) === $classroom->id ? 'selected' : '' }}>
                            {{ $classroom->name }} ({{ $classroom->program?->name }} - {{ $classroom->level?->name }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex sm:block">
                <button type="submit" class="qs-btn-secondary min-h-[38px] w-full text-sm font-semibold">{{ __('Load') }}</button>
            </div>
        </form>
    </div>

    @if ($selectedClass)
        <div class="mt-4 rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <div class="mb-3 rounded-lg border border-qs-soft bg-qs-card px-3 py-2.5 text-sm text-qs-muted">
                <span class="font-semibold text-qs-text">{{ __('Selected class') }}:</span> {{ $selectedClass->name }}
                ({{ $selectedClass->program?->name }} - {{ $selectedClass->level?->name }})
            </div>

            <form method="POST" action="{{ route('coordinator.courses.assign.update') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Courses') }}</label>
                    <div class="mt-2 max-h-64 overflow-y-auto rounded-lg border border-qs-soft p-2">
                        @forelse ($courses as $course)
                            <label class="flex min-h-[36px] cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm text-qs-muted hover:bg-qs-card">
                                <input type="checkbox" name="course_ids[]" value="{{ $course->id }}" {{ in_array($course->id, old('course_ids', $assignedCourseIds), true) ? 'checked' : '' }} class="size-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                                <span class="leading-snug">{{ $course->code }} — {{ $course->title }}</span>
                            </label>
                        @empty
                            <p class="px-2 py-4 text-center text-sm text-qs-muted">{{ __('No courses available in your departments.') }}</p>
                        @endforelse
                    </div>
                    @error('course_ids')
                        <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                    @enderror
                    @error('course_ids.*')
                        <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col-reverse gap-2.5 pt-1 sm:flex-row sm:justify-end">
                    <a href="{{ route('coordinator.courses.index') }}" class="qs-btn-secondary inline-flex min-h-[38px] items-center justify-center px-4 text-sm font-semibold">{{ __('Back') }}</a>
                    <button type="submit" class="qs-btn-primary min-h-[38px] px-4 text-sm font-semibold">{{ __('Save assignments') }}</button>
                </div>
            </form>
        </div>
    @endif
</x-layouts.coordinator>
