<x-layouts.coordinator>
    <x-slot name="title">Upload Students CSV</x-slot>
    <x-slot name="subtitle">Upload and preview student records before import</x-slot>

    <div class="rounded-xl border border-beige bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('coordinator.students.preview') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-sage" for="csv_file">CSV File</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required class="mt-1 block w-full rounded-lg border border-camel bg-white px-3 py-2 text-sm focus:border-camel focus:ring-camel">
                @error('csv_file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-sage">Name Column</label>
                    <input type="text" name="map_name" value="{{ old('map_name', 'name') }}" class="mt-1 block w-full rounded-lg border border-camel px-3 py-2 text-sm focus:border-camel focus:ring-camel" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-sage">Email Column</label>
                    <input type="text" name="map_email" value="{{ old('map_email', 'email') }}" class="mt-1 block w-full rounded-lg border border-camel px-3 py-2 text-sm focus:border-camel focus:ring-camel" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-sage">Index Number Column (optional)</label>
                    <input type="text" name="map_index_number" value="{{ old('map_index_number', 'index_number') }}" class="mt-1 block w-full rounded-lg border border-camel px-3 py-2 text-sm focus:border-camel focus:ring-camel">
                </div>
                <div>
                    <label class="block text-sm font-medium text-sage">Program Column</label>
                    <input type="text" name="map_program" value="{{ old('map_program', 'program') }}" class="mt-1 block w-full rounded-lg border border-camel px-3 py-2 text-sm focus:border-camel focus:ring-camel" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-sage">Level Column</label>
                    <input type="text" name="map_level" value="{{ old('map_level', 'level') }}" class="mt-1 block w-full rounded-lg border border-camel px-3 py-2 text-sm focus:border-camel focus:ring-camel" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-sage">Academic Year (for auto index)</label>
                    <input type="text" name="year" value="{{ old('year', now()->year) }}" class="mt-1 block w-full rounded-lg border border-camel px-3 py-2 text-sm focus:border-camel focus:ring-camel">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('coordinator.students.index') }}" class="rounded-lg border border-camel bg-white px-4 py-2 text-sm text-gray-700 hover:bg-beige">Back</a>
                <button type="submit" class="rounded-lg border border-camel bg-camel px-4 py-2 text-sm font-semibold text-white hover:bg-camel/90">
                    Preview Import
                </button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
