<x-layouts.coordinator>
    <x-slot name="title">{{ __('Upload into :name', ['name' => $classroom->name]) }}</x-slot>
    <x-slot name="subtitle">{{ __('Only roster columns — everyone is assigned to this class.') }}</x-slot>

    <nav class="mb-5 min-w-0" aria-label="{{ __('Roster upload actions') }}">
        <div class="flex min-w-0 flex-col divide-y divide-slate-200/80 rounded-2xl border border-slate-200/90 bg-slate-50/90 p-0 sm:flex-row sm:items-stretch sm:divide-x sm:divide-y-0">
            <a
                href="{{ route('coordinator.classes.show', $classroom) }}"
                class="flex min-h-[44px] items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-900 sm:flex-1 sm:justify-start"
            >
                <i class="fa-solid fa-chevron-left text-[10px] text-slate-400" aria-hidden="true"></i>
                <span>{{ __('Back to class') }}</span>
            </a>
            <a
                href="{{ route('coordinator.classes.students.template', $classroom) }}"
                class="flex min-h-[44px] items-center justify-center gap-2 px-4 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-900 sm:flex-1 sm:justify-start"
            >
                <i class="fa-solid fa-download text-[12px] text-slate-400" aria-hidden="true"></i>
                <span>{{ __('CSV template') }}</span>
            </a>
        </div>
    </nav>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <p><span class="font-semibold text-slate-800">{{ __('Program') }}:</span> {{ $classroom->program?->name }} ({{ $classroom->program?->code }})</p>
            <p class="mt-1"><span class="font-semibold text-slate-800">{{ __('Level') }}:</span> {{ $classroom->level?->name }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ __('Do not include program, level, or class columns — they are ignored when importing here.') }}</p>
        </div>

        <form method="POST" action="{{ route('coordinator.classes.students.preview', $classroom) }}" enctype="multipart/form-data" class="space-y-5">
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
                    <label class="block text-sm font-medium text-slate-800">{{ __('Index number column') }}</label>
                    <input type="text" name="map_index_number" value="{{ old('map_index_number', 'index_number') }}" class="qs-input mt-1 py-2.5" required>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Leave cells blank to auto-generate indices.') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Name column (optional)') }}</label>
                    <input type="text" name="map_name" value="{{ old('map_name', 'name') }}" class="qs-input mt-1 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-800">{{ __('Phone column (optional)') }}</label>
                    <input type="text" name="map_phone" value="{{ old('map_phone', 'phone') }}" class="qs-input mt-1 py-2.5">
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                <a href="{{ route('coordinator.classes.show', $classroom) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-5 text-sm font-semibold">{{ __('Preview import') }}</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
