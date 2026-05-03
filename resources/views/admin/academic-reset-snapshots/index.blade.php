<x-layouts.admin>
    <x-slot name="title">{{ __('Academic reset snapshots') }}</x-slot>
    <x-slot name="subtitle">{{ __('Audit trail of coordinator previews and applied resets') }}</x-slot>

    <div class="qs-surface overflow-x-auto rounded-xl">
        <table class="min-w-full text-sm">
            <thead class="border-b border-qs-soft bg-qs-card text-left text-xs uppercase text-qs-muted">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">{{ __('Department') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Initiator') }}</th>
                    <th class="px-4 py-3">{{ __('Classes') }}</th>
                    <th class="px-4 py-3">{{ __('Students') }}</th>
                    <th class="px-4 py-3">{{ __('Applied') }}</th>
                    <th class="px-4 py-3">{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($snapshots as $row)
                    <tr class="border-b border-qs-soft hover:bg-qs-card">
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->id }}</td>
                        <td class="px-4 py-3">{{ $row->department?->name }}</td>
                        <td class="px-4 py-3">{{ $row->reset_type }}</td>
                        <td class="px-4 py-3">{{ $row->initiator?->email }}</td>
                        <td class="px-4 py-3">{{ $row->summary['class_count'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->summary['student_count'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->applied_at ? __('Yes') : __('No') }}</td>
                        <td class="px-4 py-3 text-qs-muted">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-qs-muted">{{ __('No snapshots yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $snapshots->links() }}
    </div>
</x-layouts.admin>
