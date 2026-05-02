<x-layouts.coordinator>
    <x-slot name="title">Create Program</x-slot>
    <x-slot name="subtitle">Add a program in your assigned department</x-slot>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <form method="POST" action="{{ route('coordinator.programs.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="department_id" value="{{ $departmentId }}">

            <div>
                <label for="name" class="block text-sm font-medium text-qs-muted">Program Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 block w-full rounded-lg border-qs-soft focus:border-qs-accent focus:ring-qs-accent/40" />
                @error('name')
                    <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="code" class="block text-sm font-medium text-qs-muted">Program Code</label>
                <input id="code" name="code" type="text" value="{{ old('code') }}" required class="mt-1 block w-full rounded-lg border-qs-soft focus:border-qs-accent focus:ring-qs-accent/40" />
                @error('code')
                    <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-qs-muted">Department</label>
                <input type="text" value="{{ $department?->name ?? 'N/A' }}" disabled class="mt-1 block w-full rounded-lg border-qs-soft bg-qs-card text-qs-muted" />
            </div>

            <div class="flex items-center gap-2">
                <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                <label for="is_active" class="text-sm text-qs-muted">Program is active</label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('coordinator.programs.index') }}" class="rounded-lg bg-qs-card px-4 py-2 text-sm text-qs-muted hover:bg-qs-soft">Cancel</a>
                <button type="submit" class="qs-btn-primary text-sm">Create Program</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
