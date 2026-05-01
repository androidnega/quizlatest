<x-layouts.coordinator>
    <x-slot name="title">Programs</x-slot>
    <x-slot name="subtitle">Manage academic programs within your assigned departments</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div class="text-sm text-gray-600">Department-scoped view</div>
        <a href="{{ route('coordinator.programs.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Add Program
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $program->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $program->code }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $program->department?->name }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $program->is_active ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $program->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('coordinator.programs.edit', $program) }}" class="rounded-lg bg-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-300">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('coordinator.programs.toggle-status', $program) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg {{ $program->is_active ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700' }} px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ $program->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No programs found in your departments.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $programs->links() }}
        </div>
    </div>
</x-layouts.coordinator>
