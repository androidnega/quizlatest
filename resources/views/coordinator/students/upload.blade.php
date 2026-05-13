<x-layouts.coordinator>
    <x-slot name="title">{{ __('Upload students CSV') }}</x-slot>
    <x-slot name="subtitle">{{ __('Directory-wide import — includes program, level, and optional class columns.') }}</x-slot>

    <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700">
        <p class="font-semibold text-slate-900">{{ __('Adding students to one class') }}</p>
        <p class="mt-1 text-xs text-slate-600">
            {{ __('Use') }}
            <a href="{{ route('coordinator.classes.index') }}" class="font-semibold text-slate-800 underline-offset-2 hover:underline">{{ __('Classes') }}</a>
            {{ __('→ open the group → Upload roster. Download that page’s CSV template (columns: index, name, phone — program and level follow the class automatically).') }}
        </p>
        <p class="mt-2 text-xs text-slate-500">
            {{ __('This screen is for spreadsheet imports that mention program / level / class per row across many cohorts.') }}
        </p>
    </div>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <a href="{{ route('coordinator.students.template') }}" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 sm:flex-none">
            {{ __('Download directory CSV template') }}
        </a>
        <a href="{{ route('coordinator.students.index') }}" class="inline-flex min-h-[44px] flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 sm:flex-none">
            {{ __('Back to directory') }}
        </a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('coordinator.students.preview') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-800" for="csv_file">{{ __('CSV file') }}</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" required class="qs-input mt-1 py-2.5 file:me-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-800">
                @error('csv_file')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Index number column (required)') }}</label>
                    <input type="text" name="map_index_number" value="{{ old('map_index_number', 'index_number') }}" class="qs-input mt-1 py-2.5" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Name column (optional)') }}</label>
                    <input type="text" name="map_name" value="{{ old('map_name', 'name') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Phone column (optional)') }}</label>
                    <input type="text" name="map_phone" value="{{ old('map_phone', 'phone') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Program column (code or name)') }}</label>
                    <input type="text" name="map_program" value="{{ old('map_program', 'program') }}" class="qs-input mt-1 py-2.5" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Level column') }}</label>
                    <input type="text" name="map_level" value="{{ old('map_level', 'level') }}" class="qs-input mt-1 py-2.5" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Class name column (optional)') }}</label>
                    <input type="text" name="map_class_name" value="{{ old('map_class_name', 'class_name') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Academic year (for auto index)') }}</label>
                    <input type="text" name="year" value="{{ old('year', now()->year) }}" class="qs-input mt-1 py-2.5">
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('coordinator.students.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('Cancel') }}</a>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">
                    {{ __('Preview import') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
