<x-layouts.coordinator>
    <x-slot name="title">Programs</x-slot>
    <x-slot name="subtitle">Manage academic programs within your assigned departments</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-qs-muted">{{ __('Department-scoped view') }}</div>
        <a href="{{ route('coordinator.programs.create') }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">
            {{ __('Add program') }}
        </a>
    </div>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
        <div class="qs-table-wrap -mx-1 border-0 bg-transparent sm:mx-0">
            <table class="qs-table">
                <thead>
                    <tr>
                        <th class="text-left">{{ __('Name') }}</th>
                        <th class="text-left">{{ __('Code') }}</th>
                        <th class="text-left">{{ __('Department') }}</th>
                        <th class="text-left">{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($programs as $program)
                        <tr>
                            <td class="font-medium">{{ $program->name }}</td>
                            <td class="text-qs-muted">{{ $program->code }}</td>
                            <td class="text-qs-muted">{{ $program->department?->name }}</td>
                            <td>
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $program->is_active ? 'border-qs-accent/30 bg-qs-accent/20 text-qs-text' : 'bg-qs-card text-qs-muted' }}">
                                    {{ $program->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td>
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('coordinator.programs.edit', $program) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-3 py-2 text-xs font-semibold">
                                        {{ __('Edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('coordinator.programs.toggle-status', $program) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="{{ $program->is_active ? 'qs-btn-danger-sm' : 'qs-btn-primary' }} min-h-[44px] px-3 py-2 text-xs font-semibold">
                                            {{ $program->is_active ? __('Deactivate') : __('Activate') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-qs-muted">{{ __('No programs found in your departments.') }}</td>
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
