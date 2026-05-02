<x-layouts.admin>
    <x-slot name="title">Coordinator Management</x-slot>
    <x-slot name="subtitle">Create and assign coordinators to faculties and departments</x-slot>

    <div class="mb-6 flex items-center justify-end">
        <a href="{{ route('admin.coordinators.create') }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-qs-accent border border-qs-accent rounded-md hover:opacity-95">
            Add Coordinator
        </a>
    </div>

    <div class="qs-surface rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-qs-soft">
            <thead class="bg-white">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Departments</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider qs-heading">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-qs-soft bg-[#FBF9EB]">
                @forelse ($coordinators as $coordinator)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $coordinator->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $coordinator->email }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @if ($coordinator->coordinatorAssignments->isEmpty())
                                <span class="text-gray-500">No assignments</span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($coordinator->coordinatorAssignments as $assignment)
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs bg-white border border-qs-soft">
                                            {{ $assignment->department?->name }} ({{ $assignment->faculty?->name }})
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex px-2 py-1 rounded-full text-xs {{ $coordinator->is_active ? 'bg-qs-accent text-white' : 'bg-gray-200 text-gray-700' }}">
                                {{ $coordinator->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.coordinators.edit', $coordinator) }}" class="text-sm qs-link font-medium">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-600">
                            No coordinators found. Create one to begin department-level management.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $coordinators->links() }}
    </div>
</x-layouts.admin>
