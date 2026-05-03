<x-layouts.admin>
    <x-slot name="title">University Management</x-slot>
    <x-slot name="subtitle">Create and maintain institutions on the platform</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <a href="{{ route('admin.universities.create') }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">
            Add University
        </a>
    </div>

    <div class="qs-table-wrap rounded-lg border border-qs-soft">
        <table class="qs-table">
            <thead>
                <tr>
                    <th class="text-left">Name</th>
                    <th class="text-left">Code</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Created</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($universities as $university)
                    <tr>
                        <td class="text-sm text-qs-text">{{ $university->name }}</td>
                        <td class="text-sm text-qs-muted">{{ $university->code ?? 'N/A' }}</td>
                        <td class="text-sm">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $university->is_active ? 'border border-qs-accent/30 bg-qs-accent/20 text-qs-text' : 'bg-qs-card text-qs-muted' }}">
                                {{ $university->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-sm text-qs-muted">{{ $university->created_at?->format('Y-m-d') }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.universities.edit', $university) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 py-2 text-sm font-semibold">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-qs-muted">
                            {{ __('No universities found. Create your first university to begin.') }}
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
