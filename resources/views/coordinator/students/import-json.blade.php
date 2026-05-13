<x-layouts.coordinator>
    <x-slot name="title">{{ __('Import students JSON') }}</x-slot>
    <x-slot name="subtitle">{{ __('Upload a JSON file to create or update student records.') }}</x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200/80 bg-rose-50/80 px-4 py-3 text-sm text-rose-900">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('json_import_errors'))
        <div class="mb-4 rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">{{ __('Rows skipped with warnings') }}</p>
            <ul class="mt-2 list-disc space-y-1 ps-5 text-xs">
                @foreach (session('json_import_errors') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
        <p class="font-semibold text-slate-800">{{ __('JSON shape') }}</p>
        <p class="mt-1">{{ __('Use either a root array or { "students": [...] }.') }}</p>
        <p class="mt-1">{{ __('Each row should include: name, index_number, program_code (or program_name), level_code (or level_name), optional phone, optional class_name, optional is_active.') }}</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('coordinator.students.import-json') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <div>
                <label for="json_file" class="block text-sm font-medium text-slate-800">{{ __('JSON file') }}</label>
                <input type="file" name="json_file" id="json_file" accept=".json,application/json,text/plain" required class="qs-input mt-1 py-2.5 file:me-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-800">
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('coordinator.students.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-5 text-sm font-semibold">{{ __('Import JSON') }}</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
