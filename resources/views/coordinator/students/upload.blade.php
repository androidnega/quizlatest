<x-layouts.coordinator>
    <x-slot name="title">Upload Students CSV</x-slot>
    <x-slot name="subtitle">Upload and preview student records before import</x-slot>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm">
        <form method="POST" action="{{ route('coordinator.students.preview') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-qs-text" for="csv_file">CSV File</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required class="mt-1 block w-full rounded-lg border border-qs-soft bg-qs-bg px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                @error('csv_file')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-qs-text">Name Column</label>
                    <input type="text" name="map_name" value="{{ old('map_name', 'name') }}" class="mt-1 block w-full rounded-lg border border-qs-soft px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">Email Column</label>
                    <input type="text" name="map_email" value="{{ old('map_email', 'email') }}" class="mt-1 block w-full rounded-lg border border-qs-soft px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">Index Number Column (optional)</label>
                    <input type="text" name="map_index_number" value="{{ old('map_index_number', 'index_number') }}" class="mt-1 block w-full rounded-lg border border-qs-soft px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">Program Column</label>
                    <input type="text" name="map_program" value="{{ old('map_program', 'program') }}" class="mt-1 block w-full rounded-lg border border-qs-soft px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">Level Column</label>
                    <input type="text" name="map_level" value="{{ old('map_level', 'level') }}" class="mt-1 block w-full rounded-lg border border-qs-soft px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">Academic Year (for auto index)</label>
                    <input type="text" name="year" value="{{ old('year', now()->year) }}" class="mt-1 block w-full rounded-lg border border-qs-soft px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('coordinator.students.index') }}" class="rounded-lg border border-qs-soft bg-qs-bg px-4 py-2 text-sm text-qs-muted hover:bg-qs-card">Back</a>
                <button type="submit" class="rounded-lg border border-qs-accent bg-qs-accent px-4 py-2 text-sm font-semibold text-qs-text hover:opacity-95">
                    Preview Import
                </button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
