<x-layouts.coordinator>
    <x-slot name="title">CSV Preview</x-slot>
    <x-slot name="subtitle">Validate records before final import</x-slot>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <p class="text-sm text-qs-muted">{{ __('Rows parsed') }}</p>
            <p class="mt-1 text-2xl font-semibold text-qs-text">{{ count($previewRows) }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <p class="text-sm text-qs-muted">{{ __('Valid rows') }}</p>
            <p class="mt-1 text-2xl font-semibold text-qs-text">{{ $validCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <p class="text-sm text-qs-muted">{{ __('Invalid rows') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $invalidCount > 0 ? 'text-qs-danger' : 'text-qs-text' }}">{{ $invalidCount }}</p>
        </div>
    </div>

    <div class="qs-table-wrap shadow-sm">
        <table class="qs-table">
            <thead>
                <tr>
                    <th class="text-left">{{ __('Row') }}</th>
                    <th class="text-left">{{ __('Name') }}</th>
                    <th class="text-left">{{ __('Phone') }}</th>
                    <th class="text-left">{{ __('Index') }}</th>
                    <th class="text-left">{{ __('Class') }}</th>
                    <th class="text-left">{{ __('Program') }}</th>
                    <th class="text-left">{{ __('Level') }}</th>
                    <th class="text-left">{{ __('Validation results') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($previewRows as $row)
                    <tr class="{{ empty($row['errors']) ? '' : 'bg-qs-danger-soft' }}">
                        <td>{{ $row['row_number'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['phone'] ?? '—' }}</td>
                        <td>{{ $row['index_number'] ?: __('Auto-generate') }}</td>
                        <td>{{ $row['class_name'] !== '' ? $row['class_name'] : '—' }}</td>
                        <td>{{ $row['program'] ?: 'N/A' }}</td>
                        <td>{{ $row['level'] ?: 'N/A' }}</td>
                        <td>
                            @if (empty($row['errors']))
                                <span class="inline-flex rounded-full border border-qs-accent/30 bg-qs-accent/20 px-2 py-0.5 text-xs font-medium text-qs-text">{{ __('Valid') }}</span>
                            @else
                                <ul class="list-disc ps-4 text-xs text-qs-danger">
                                    @foreach ($row['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('coordinator.students.upload') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Back to upload') }}</a>

        <form method="POST" action="{{ route('coordinator.students.import') }}" class="w-full sm:w-auto">
            @csrf
            <button type="submit" class="qs-btn-primary min-h-[44px] w-full px-4 text-sm font-semibold sm:w-auto" {{ $validCount === 0 ? 'disabled' : '' }}>
                {{ __('Import valid rows') }} ({{ $validCount }})
            </button>
        </form>
    </div>
</x-layouts.coordinator>
