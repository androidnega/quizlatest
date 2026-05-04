<x-layouts.coordinator>
    <x-slot name="title">Upload Students CSV</x-slot>
    <x-slot name="subtitle">Upload and preview student records before import</x-slot>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <a href="{{ route('coordinator.students.template') }}" class="qs-btn-secondary inline-flex min-h-[44px] flex-1 items-center justify-center px-4 text-sm font-semibold sm:flex-none">
            {{ __('Download CSV template') }}
        </a>
        <a href="{{ route('coordinator.students.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] flex-1 items-center justify-center px-4 text-sm font-semibold sm:flex-none">
            {{ __('Back to students') }}
        </a>
    </div>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm">
        <form method="POST" action="{{ route('coordinator.students.preview') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-qs-text" for="csv_file">{{ __('CSV file') }}</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required class="qs-input mt-1 py-2.5 file:me-3 file:rounded-md file:border-0 file:bg-qs-card file:px-3 file:py-2 file:text-sm file:font-medium file:text-qs-text">
                @error('csv_file')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Index number column (required)') }}</label>
                    <input type="text" name="map_index_number" value="{{ old('map_index_number', 'index_number') }}" class="qs-input mt-1 py-2.5" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Name column (optional)') }}</label>
                    <input type="text" name="map_name" value="{{ old('map_name', 'name') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Phone column (optional)') }}</label>
                    <input type="text" name="map_phone" value="{{ old('map_phone', 'phone') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Email column (optional)') }}</label>
                    <input type="text" name="map_email" value="{{ old('map_email', 'email') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Program column (code or name)') }}</label>
                    <input type="text" name="map_program" value="{{ old('map_program', 'program') }}" class="qs-input mt-1 py-2.5" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Level column') }}</label>
                    <input type="text" name="map_level" value="{{ old('map_level', 'level') }}" class="qs-input mt-1 py-2.5" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Class name column (optional)') }}</label>
                    <input type="text" name="map_class_name" value="{{ old('map_class_name', 'class_name') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-qs-text">{{ __('Academic year (for auto index)') }}</label>
                    <input type="text" name="year" value="{{ old('year', now()->year) }}" class="qs-input mt-1 py-2.5">
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('coordinator.students.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">
                    {{ __('Preview import') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
