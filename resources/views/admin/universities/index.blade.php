<x-layouts.admin>
    <x-slot name="title">University Management</x-slot>
    <x-slot name="subtitle">Create and maintain institutions on the platform</x-slot>

    <div class="mb-6 flex items-center justify-end">
        <a href="{{ route('admin.universities.create') }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-qs-accent border border-qs-accent rounded-md hover:opacity-95">
            Add University
        </a>
    </div>

    <div class="qs-surface rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-qs-soft">
            <thead class="bg-white">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Created</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-qs-soft bg-[#FBF9EB]">
                @forelse ($universities as $university)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $university->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $university->code ?? 'N/A' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $university->is_active ? 'bg-qs-accent text-white' : 'bg-gray-200 text-gray-700' }}">
                                {{ $university->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $university->created_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.universities.edit', $university) }}" class="text-sm qs-link font-medium">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-600">
                            No universities found. Create your first university to begin.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $universities->links() }}
    </div>
</x-layouts.admin>
