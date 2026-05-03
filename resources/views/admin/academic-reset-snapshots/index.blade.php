<x-layouts.admin>
    <x-slot name="title">{{ __('Academic reset snapshots') }}</x-slot>
    <x-slot name="subtitle">{{ __('Audit trail of coordinator previews and applied resets') }}</x-slot>

    <div class="qs-table-wrap rounded-xl border border-qs-soft">
        <table class="qs-table">
            <thead>
                <tr>
                    <th class="text-left">ID</th>
                    <th class="text-left">{{ __('Department') }}</th>
                    <th class="text-left">{{ __('Academic year') }}</th>
                    <th class="text-left">{{ __('Type') }}</th>
                    <th class="text-left">{{ __('Initiator') }}</th>
                    <th class="text-left">{{ __('Classes') }}</th>
                    <th class="text-left">{{ __('Students') }}</th>
                    <th class="text-left">{{ __('Applied') }}</th>
                    <th class="text-left">{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($snapshots as $row)
                    <tr>
                        <td class="font-mono text-xs text-qs-text">{{ $row->id }}</td>
                        <td class="text-sm text-qs-text">{{ $row->department?->name }}</td>
                        <td class="text-sm text-qs-muted">{{ $row->academicYear?->name ?? ($row->summary['academic_year_name'] ?? '—') }}</td>
                        <td class="text-sm text-qs-muted">{{ $row->reset_type }}</td>
                        <td class="text-sm text-qs-muted">{{ $row->initiator?->email }}</td>
                        <td class="text-sm text-qs-text">{{ $row->summary['class_count'] ?? '—' }}</td>
                        <td class="text-sm text-qs-text">{{ $row->summary['student_count'] ?? '—' }}</td>
                        <td class="text-sm text-qs-text">{{ $row->applied_at ? __('Yes') : __('No') }}</td>
                        <td class="text-sm text-qs-muted">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-sm text-qs-muted">{{ __('No snapshots yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $snapshots->links() }}
    </div>
</x-layouts.admin>
