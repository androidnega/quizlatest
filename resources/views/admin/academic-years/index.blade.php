<x-layouts.admin>
    <x-slot name="title">{{ __('Academic years') }}</x-slot>
    <x-slot name="subtitle">{{ __('Terms roll up under each year; only one active year per university.') }}</x-slot>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
        <a href="{{ route('admin.academic-years.create') }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">
            {{ __('Add academic year') }}
        </a>
    </div>

    <div class="qs-table-wrap rounded-lg border border-qs-soft">
        <table class="qs-table">
            <thead>
                <tr>
                    <th class="text-left">{{ __('University') }}</th>
                    <th class="text-left">{{ __('Name') }}</th>
                    <th class="text-left">{{ __('Period') }}</th>
                    <th class="text-left">{{ __('Status') }}</th>
                    <th class="text-left">{{ __('Terms') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($academicYears as $year)
                    <tr>
                        <td class="text-sm text-qs-text">{{ $year->university?->name }}</td>
                        <td class="text-sm font-medium text-qs-text">{{ $year->name }}</td>
                        <td class="text-sm text-qs-muted">{{ $year->start_date?->format('Y-m-d') }} → {{ $year->end_date?->format('Y-m-d') }}</td>
                        <td class="text-sm">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $year->is_active ? 'border border-qs-accent/30 bg-qs-accent/20 text-qs-text' : 'bg-qs-card text-qs-muted' }}">
                                {{ $year->status }}{{ $year->is_active ? ' · '.__('Active flag') : '' }}
                            </span>
                        </td>
                        <td class="text-sm text-qs-muted">{{ $year->terms->count() }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.academic-years.edit', $year) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 py-2 text-sm font-semibold">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-qs-muted">
                            {{ __('No academic years yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $academicYears->links() }}
    </div>
</x-layouts.admin>
