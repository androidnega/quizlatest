<x-layouts.coordinator>
    <x-slot name="title">CSV Preview</x-slot>
    <x-slot name="subtitle">Validate records before final import</x-slot>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <p class="text-sm text-qs-muted">Rows Parsed</p>
            <p class="mt-1 text-2xl font-semibold text-qs-text">{{ count($previewRows) }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <p class="text-sm text-qs-muted">Valid Rows</p>
            <p class="mt-1 text-2xl font-semibold text-qs-text">{{ $validCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-4 shadow-sm">
            <p class="text-sm text-qs-muted">Invalid Rows</p>
            <p class="mt-1 text-2xl font-semibold {{ $invalidCount > 0 ? 'text-qs-danger' : 'text-qs-text' }}">{{ $invalidCount }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-qs-soft bg-qs-bg shadow-sm">
        <table class="min-w-full divide-y divide-beige">
            <thead class="bg-qs-soft/30">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Row</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Index</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Program</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Level</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Validation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-beige">
                @foreach ($previewRows as $row)
                    <tr class="{{ empty($row['errors']) ? 'hover:bg-qs-card' : 'bg-qs-danger-soft' }}">
                        <td class="px-4 py-3 text-sm">{{ $row['row_number'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['name'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['email'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['index_number'] ?: 'Auto-generate' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['program'] ?: 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['level'] ?: 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if (empty($row['errors']))
                                <span class="inline-flex rounded-full bg-qs-accent px-2 py-1 text-xs text-qs-text">Valid</span>
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

    <div class="mt-5 flex items-center justify-between">
        <a href="{{ route('coordinator.students.upload') }}" class="rounded-lg border border-qs-accent bg-qs-bg px-4 py-2 text-sm text-qs-muted hover:bg-qs-card">Back to Upload</a>

        <form method="POST" action="{{ route('coordinator.students.import') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-qs-accent bg-qs-accent px-4 py-2 text-sm font-semibold text-qs-text hover:opacity-95" {{ $validCount === 0 ? 'disabled' : '' }}>
                Import Valid Rows ({{ $validCount }})
            </button>
        </form>
    </div>
</x-layouts.coordinator>
