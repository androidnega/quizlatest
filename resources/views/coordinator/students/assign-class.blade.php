<x-layouts.coordinator>
    <x-slot name="title">Assign Student Class</x-slot>
    <x-slot name="subtitle">Assign or edit class for selected student</x-slot>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm">
        <div class="mb-4 rounded-lg border border-qs-soft bg-qs-card p-4">
            <p class="text-sm text-qs-muted"><span class="font-semibold text-qs-text">{{ __('Student') }}:</span> {{ $student->name }}</p>
            <p class="text-sm text-qs-muted"><span class="font-semibold text-qs-text">{{ __('Program') }}:</span> {{ $student->program?->name ?? 'N/A' }}</p>
            <p class="text-sm text-qs-muted"><span class="font-semibold text-qs-text">{{ __('Level') }}:</span> {{ $student->level?->name ?? 'N/A' }}</p>
        </div>

        <form method="POST" action="{{ route('coordinator.students.assign-class.update', $student) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="class_id" class="block text-sm font-medium text-qs-muted">{{ __('Class') }}</label>
                <select id="class_id" name="class_id" class="qs-input mt-1 py-2.5">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($classes as $classroom)
                        <option value="{{ $classroom->id }}" {{ (int) old('class_id', $student->class_id) === $classroom->id ? 'selected' : '' }}>
                            {{ $classroom->name }} ({{ $classroom->program?->name }} - {{ $classroom->level?->name }})
                        </option>
                    @endforeach
                </select>
                @error('class_id')
                    <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                <a href="{{ route('coordinator.students.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Save assignment') }}</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
