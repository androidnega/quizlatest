<x-layouts.admin>
    <x-slot name="title">{{ __('Manage users') }}</x-slot>
    <x-slot name="subtitle">{{ __('Search and manage staff accounts (admins, coordinators, and examiners). Students are managed by coordinators.') }}</x-slot>

    <div class="mb-6">
        <a href="{{ route('admin.users.create') }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Create staff account') }}</a>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <label for="q" class="text-xs font-medium text-qs-muted">{{ __('Search') }}</label>
            <input
                id="q"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('Name, email, or index number') }}"
                class="qs-input min-h-[44px] w-full max-w-md rounded-lg border border-qs-soft bg-qs-card px-3 text-sm text-qs-text"
            />
        </div>
        <div class="flex flex-col gap-1">
            <label for="role" class="text-xs font-medium text-qs-muted">{{ __('Role') }}</label>
            <select
                id="role"
                name="role"
                class="qs-input min-h-[44px] min-w-[10rem] rounded-lg border border-qs-soft bg-qs-card px-3 text-sm text-qs-text"
            >
                <option value="">{{ __('All staff roles') }}</option>
                <option value="admin" @selected($roleFilter === 'admin')>{{ __('Admin') }}</option>
                <option value="coordinator" @selected($roleFilter === 'coordinator')>{{ __('Coordinator') }}</option>
                <option value="examiner" @selected($roleFilter === 'examiner')>{{ __('Examiner') }}</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Apply') }}</button>
            @if ($search !== '' || $roleFilter !== '')
                <a href="{{ route('admin.users.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Clear') }}</a>
            @endif
        </div>
    </form>

    <div class="qs-table-wrap rounded-lg border border-qs-soft">
        <table class="qs-table">
            <thead>
                <tr>
                    <th class="text-left">{{ __('Name') }}</th>
                    <th class="text-left">{{ __('Login') }}</th>
                    <th class="text-left">{{ __('Role') }}</th>
                    <th class="text-left">{{ __('University') }}</th>
                    <th class="text-left">{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td class="text-sm text-qs-text">{{ $u->name }}</td>
                        <td class="text-sm text-qs-muted">
                            @if ($u->role === 'student')
                                {{ $u->index_number ?? '—' }}
                            @else
                                {{ $u->email ?? '—' }}
                            @endif
                        </td>
                        <td class="text-sm text-qs-muted">
                            <span class="inline-flex rounded-md border border-qs-soft bg-qs-bg px-2 py-0.5 text-xs font-medium capitalize">{{ $u->role }}</span>
                            @if ($u->isSuperAdmin())
                                <span class="ml-1 text-xs text-qs-primary">{{ __('Super') }}</span>
                            @endif
                        </td>
                        <td class="text-sm text-qs-muted">{{ $u->university?->name ?? '—' }}</td>
                        <td class="text-sm">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $u->is_active ? 'border border-qs-accent/30 bg-qs-accent/20 text-qs-text' : 'bg-qs-card text-qs-muted' }}">
                                {{ $u->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.users.edit', $u) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 py-2 text-sm font-semibold">{{ __('Manage') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-qs-muted">
                            {{ __('No users match your filters.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</x-layouts.admin>
