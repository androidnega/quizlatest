<x-layouts.coordinator>
    <x-slot name="title">Levels</x-slot>
    <x-slot name="subtitle">Activate or deactivate predefined academic levels</x-slot>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Level</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($levels as $level)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $level->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $level->code }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $level->is_active ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $level->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('coordinator.levels.toggle-status', $level) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg {{ $level->is_active ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700' }} px-3 py-1.5 text-xs font-semibold text-white">
                                        {{ $level->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No levels found for your institution.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.coordinator>
