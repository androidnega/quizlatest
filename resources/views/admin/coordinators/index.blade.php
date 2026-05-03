<x-layouts.admin>
    <x-slot name="title">Coordinator Management</x-slot>
    <x-slot name="subtitle">Create and assign coordinators to faculties and departments</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <a href="{{ route('admin.coordinators.create') }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">
            Add Coordinator
        </a>
    </div>

    <div class="qs-table-wrap rounded-lg border border-qs-soft">
        <table class="qs-table">
            <thead>
                <tr>
                    <th class="text-left">Name</th>
                    <th class="text-left">Email</th>
                    <th class="text-left">Departments</th>
                    <th class="text-left">Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coordinators as $coordinator)
                    <tr>
                        <td class="text-sm text-qs-text">{{ $coordinator->name }}</td>
                        <td class="text-sm text-qs-muted">{{ $coordinator->email }}</td>
                        <td class="text-sm text-qs-muted">
                            @if ($coordinator->coordinatorAssignments->isEmpty())
                                <span class="text-qs-muted">{{ __('No assignments') }}</span>
                            @else
                                <div class="flex max-w-xs flex-wrap gap-1">
                                    @foreach ($coordinator->coordinatorAssignments as $assignment)
                                        <span class="inline-flex items-center rounded-md border border-qs-soft bg-qs-bg px-2 py-0.5 text-xs">
                                            {{ $assignment->department?->name }} ({{ $assignment->faculty?->name }})
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="text-sm">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $coordinator->is_active ? 'border border-qs-accent/30 bg-qs-accent/20 text-qs-text' : 'bg-qs-card text-qs-muted' }}">
                                {{ $coordinator->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.coordinators.edit', $coordinator) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 py-2 text-sm font-semibold">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-qs-muted">
                            {{ __('No coordinators found. Create one to begin department-level management.') }}
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
