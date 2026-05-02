<x-layouts.coordinator>
    <x-slot name="title">Assign Student Class</x-slot>
    <x-slot name="subtitle">Assign or edit class for selected student</x-slot>

    <div class="rounded-xl bg-qs-bg p-6 shadow-sm">
        <div class="mb-4 rounded-lg border border-qs-soft bg-qs-card p-4">
            <p class="text-sm text-qs-muted"><span class="font-semibold">Student:</span> {{ $student->name }}</p>
            <p class="text-sm text-qs-muted"><span class="font-semibold">Program:</span> {{ $student->program?->name ?? 'N/A' }}</p>
            <p class="text-sm text-qs-muted"><span class="font-semibold">Level:</span> {{ $student->level?->name ?? 'N/A' }}</p>
        </div>

        <form method="POST" action="{{ route('coordinator.students.assign-class.update', $student) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="class_id" class="block text-sm font-medium text-qs-muted">Class</label>
                <select id="class_id" name="class_id" class="mt-1 block w-full rounded-lg border border-qs-soft bg-qs-bg px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                    <option value="">Unassigned</option>
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

            <div class="flex justify-end gap-2">
                <a href="{{ route('coordinator.students.index') }}" class="rounded-lg bg-qs-card px-4 py-2 text-sm text-qs-muted hover:bg-qs-soft">Cancel</a>
                <button type="submit" class="qs-btn-primary text-sm">Save Assignment</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
