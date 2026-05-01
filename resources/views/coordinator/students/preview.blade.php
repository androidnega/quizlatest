<x-layouts.coordinator>
    <x-slot name="title">CSV Preview</x-slot>
    <x-slot name="subtitle">Validate records before final import</x-slot>

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-beige bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-600">Rows Parsed</p>
            <p class="mt-1 text-2xl font-semibold text-sage">{{ count($previewRows) }}</p>
        </div>
        <div class="rounded-xl border border-beige bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-600">Valid Rows</p>
            <p class="mt-1 text-2xl font-semibold text-sage">{{ $validCount }}</p>
        </div>
        <div class="rounded-xl border border-beige bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-600">Invalid Rows</p>
            <p class="mt-1 text-2xl font-semibold {{ $invalidCount > 0 ? 'text-red-600' : 'text-sage' }}">{{ $invalidCount }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-beige bg-white shadow-sm">
        <table class="min-w-full divide-y divide-beige">
            <thead class="bg-beige/60">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Row</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Index</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Program</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Level</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Validation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-beige">
                @foreach ($previewRows as $row)
                    <tr class="{{ empty($row['errors']) ? 'hover:bg-beige/20' : 'bg-red-50' }}">
                        <td class="px-4 py-3 text-sm">{{ $row['row_number'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['name'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['email'] }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['index_number'] ?: 'Auto-generate' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['program'] ?: 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $row['level'] ?: 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if (empty($row['errors']))
                                <span class="inline-flex rounded-full bg-camel px-2 py-1 text-xs text-white">Valid</span>
                            @else
                                <ul class="list-disc ps-4 text-xs text-red-700">
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
        <a href="{{ route('coordinator.students.upload') }}" class="rounded-lg border border-camel bg-white px-4 py-2 text-sm text-gray-700 hover:bg-beige">Back to Upload</a>

        <form method="POST" action="{{ route('coordinator.students.import') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-camel bg-camel px-4 py-2 text-sm font-semibold text-white hover:bg-camel/90" {{ $validCount === 0 ? 'disabled' : '' }}>
                Import Valid Rows ({{ $validCount }})
            </button>
        </form>
    </div>
</x-layouts.coordinator>
