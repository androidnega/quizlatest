<x-layouts.coordinator>
    <x-slot name="title">Levels</x-slot>
    <x-slot name="subtitle">Activate or deactivate predefined academic levels</x-slot>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-qs-card">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Level</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-qs-muted">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($levels as $level)
                        <tr class="hover:bg-qs-card">
                            <td class="px-4 py-3 text-sm text-qs-text">{{ $level->name }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $level->code }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $level->is_active ? 'bg-qs-accent/20 text-qs-text border border-qs-accent/30' : 'bg-qs-card text-qs-muted' }}">
                                    {{ $level->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('coordinator.levels.toggle-status', $level) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="{{ $level->is_active ? 'qs-btn-danger-sm' : 'qs-btn-primary px-3 py-1.5 text-xs' }}">
                                        {{ $level->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-qs-muted">No levels found for your institution.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.coordinator>
