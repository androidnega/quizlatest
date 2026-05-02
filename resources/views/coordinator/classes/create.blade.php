<x-layouts.coordinator>
    <x-slot name="title">Create Class</x-slot>
    <x-slot name="subtitle">Add a class in your assigned department scope</x-slot>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <form method="POST" action="{{ route('coordinator.classes.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-qs-muted">Class Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 block w-full rounded-lg border-qs-soft focus:border-qs-accent focus:ring-qs-accent/40" />
                @error('name')
                    <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="program_id" class="block text-sm font-medium text-qs-muted">Program</label>
                <select id="program_id" name="program_id" required class="mt-1 block w-full rounded-lg border-qs-soft focus:border-qs-accent focus:ring-qs-accent/40">
                    <option value="">Select program</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" {{ (int) old('program_id') === $program->id ? 'selected' : '' }}>
                            {{ $program->name }} ({{ $program->department?->name }})
                        </option>
                    @endforeach
                </select>
                @error('program_id')
                    <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="level_id" class="block text-sm font-medium text-qs-muted">Level</label>
                <select id="level_id" name="level_id" required class="mt-1 block w-full rounded-lg border-qs-soft focus:border-qs-accent focus:ring-qs-accent/40">
                    <option value="">Select level</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}" {{ (int) old('level_id') === $level->id ? 'selected' : '' }}>
                            {{ $level->name }}
                        </option>
                    @endforeach
                </select>
                @error('level_id')
                    <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-qs-muted">Department</label>
                <input type="text" value="Auto from selected program (coordinator-scoped)" disabled class="mt-1 block w-full rounded-lg border-qs-soft bg-qs-card text-qs-muted" />
            </div>

            <div class="flex items-center gap-2">
                <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                <label for="is_active" class="text-sm text-qs-muted">Class is active</label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('coordinator.classes.index') }}" class="rounded-lg bg-qs-card px-4 py-2 text-sm text-qs-muted hover:bg-qs-soft">Cancel</a>
                <button type="submit" class="qs-btn-primary text-sm">Create Class</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
