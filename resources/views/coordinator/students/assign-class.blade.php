<x-layouts.coordinator>
    <x-slot name="title">Assign Student Class</x-slot>
    <x-slot name="subtitle">Assign or edit class for selected student</x-slot>

    <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p class="text-sm text-gray-700"><span class="font-semibold">Student:</span> {{ $student->name }}</p>
            <p class="text-sm text-gray-700"><span class="font-semibold">Program:</span> {{ $student->program?->name ?? 'N/A' }}</p>
            <p class="text-sm text-gray-700"><span class="font-semibold">Level:</span> {{ $student->level?->name ?? 'N/A' }}</p>
        </div>

        <form method="POST" action="{{ route('coordinator.students.assign-class.update', $student) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
                <select id="class_id" name="class_id" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Unassigned</option>
                    @foreach ($classes as $classroom)
                        <option value="{{ $classroom->id }}" {{ (int) old('class_id', $student->class_id) === $classroom->id ? 'selected' : '' }}>
                            {{ $classroom->name }} ({{ $classroom->program?->name }} - {{ $classroom->level?->name }})
                        </option>
                    @endforeach
                </select>
                @error('class_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('coordinator.students.index') }}" class="rounded-lg bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Assignment</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
