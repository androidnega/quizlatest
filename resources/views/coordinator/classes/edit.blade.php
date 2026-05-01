<x-layouts.coordinator>
    <x-slot name="title">Edit Class</x-slot>
    <x-slot name="subtitle">Update class details within your department scope</x-slot>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <form method="POST" action="{{ route('coordinator.classes.update', $classroom) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Class Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $classroom->name) }}" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-600 focus:ring-blue-600" />
                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="program_id" class="block text-sm font-medium text-gray-700">Program</label>
                <select id="program_id" name="program_id" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Select program</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" {{ (int) old('program_id', $classroom->program_id) === $program->id ? 'selected' : '' }}>
                            {{ $program->name }} ({{ $program->department?->name }})
                        </option>
                    @endforeach
                </select>
                @error('program_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="level_id" class="block text-sm font-medium text-gray-700">Level</label>
                <select id="level_id" name="level_id" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Select level</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}" {{ (int) old('level_id', $classroom->level_id) === $level->id ? 'selected' : '' }}>
                            {{ $level->name }}
                        </option>
                    @endforeach
                </select>
                @error('level_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Department</label>
                <input type="text" value="Auto from selected program (coordinator-scoped)" disabled class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 text-gray-600" />
            </div>

            <div class="flex items-center gap-2">
                <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $classroom->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-600" />
                <label for="is_active" class="text-sm text-gray-700">Class is active</label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('coordinator.classes.index') }}" class="rounded-lg bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Update Class</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
