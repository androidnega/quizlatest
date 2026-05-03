<x-layouts.coordinator>
    <x-slot name="title">Levels</x-slot>
    <x-slot name="subtitle">Activate or deactivate predefined academic levels</x-slot>

    <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
        <div class="qs-table-wrap -mx-1 border-0 bg-transparent sm:mx-0">
            <table class="qs-table">
                <thead>
                    <tr>
                        <th class="text-left">{{ __('Level') }}</th>
                        <th class="text-left">{{ __('Code') }}</th>
                        <th class="text-left">{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($levels as $level)
                        <tr>
                            <td class="font-medium">{{ $level->name }}</td>
                            <td class="text-qs-muted">{{ $level->code }}</td>
                            <td>
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $level->is_active ? 'border-qs-accent/30 bg-qs-accent/20 text-qs-text' : 'bg-qs-card text-qs-muted' }}">
                                    {{ $level->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('coordinator.levels.toggle-status', $level) }}" class="inline-flex justify-end">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="{{ $level->is_active ? 'qs-btn-danger-sm' : 'qs-btn-primary' }} min-h-[44px] px-3 py-2 text-xs font-semibold">
                                        {{ $level->is_active ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-10 text-center text-sm text-qs-muted">{{ __('No levels found for your institution.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.coordinator>
