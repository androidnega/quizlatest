<x-layouts.admin>
    <x-slot name="title">{{ __('Manage account') }}</x-slot>
    <x-slot name="subtitle">{{ $account->name }} · {{ ucfirst($account->role) }}</x-slot>

    @php
        $field = 'min-h-[44px] w-full max-w-xl rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/25';
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.users.index', request()->only(['q', 'role'])) }}" class="text-sm font-medium text-qs-primary hover:underline">{{ __('← Back to manage users') }}</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-qs-accent/30 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm" role="status">
            {{ session('status') }}
        </div>
    @endif
    @if (session('generated_password'))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm" role="status">
            <p class="font-semibold">{{ __('Generated password') }}: <span class="font-mono tracking-wide">{{ session('generated_password') }}</span></p>
            <p class="mt-1 text-xs">{{ __('Copy it now. This value is shown once.') }}</p>
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Account') }}</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-slate-500">{{ __('Name') }}</dt><dd class="font-medium text-slate-900">{{ $account->name ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Role') }}</dt><dd class="font-medium text-slate-900">{{ ucfirst($account->role) }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('Status') }}</dt><dd class="font-medium {{ $account->is_active ? 'text-emerald-700' : 'text-slate-600' }}">{{ $account->is_active ? __('Active') : __('Inactive') }}</dd></div>
                </dl>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Sign-in') }}</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="text-slate-500">{{ __('Email / staff username') }}</dt><dd class="font-medium text-slate-900">{{ $account->email ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">{{ __('University') }}</dt><dd class="font-medium text-slate-900">{{ $account->university?->name ?? '—' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>

    <details class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm" @if ($errors->any()) open @endif>
        <summary class="flex cursor-pointer items-center justify-between px-5 py-4 text-sm font-semibold text-slate-800">
            <span>{{ __('Edit account') }}</span>
            <i class="fa-solid fa-pen text-xs text-slate-400" aria-hidden="true"></i>
        </summary>
        <div class="border-t border-slate-100 p-6">
            <form method="POST" action="{{ route('admin.users.update', $account) }}">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                <div>
                    <label for="name" class="mb-1 block text-xs font-medium text-slate-600">{{ __('Name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $account->name) }}" required class="{{ $field }}" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-xs font-medium text-slate-600">{{ __('Email / staff username') }}</label>
                    <input id="email" name="email" type="text" value="{{ old('email', $account->email) }}" class="{{ $field }}" autocomplete="off" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Used as the staff sign-in username on the staff login page.') }}</p>
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="h-4 w-4 rounded border-slate-300 bg-white text-teal-600 focus:ring-teal-500/30" @checked(old('is_active', $account->is_active)) />
                    <label for="is_active" class="text-sm text-slate-800">{{ __('Account active') }}</label>
                </div>
                @error('is_active')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div class="border-t border-slate-200 pt-4">
                    <p class="mb-2 text-sm font-medium text-slate-800">{{ __('Reset password') }}</p>
                    <p class="mb-3 text-xs text-slate-500">{{ __('Optional. Generate a new password for this user on save.') }}</p>
                    <div class="max-w-xl rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <input type="hidden" name="generate_password" value="0" />
                        <label class="inline-flex cursor-pointer items-start gap-3">
                            <input
                                type="checkbox"
                                name="generate_password"
                                value="1"
                                class="mt-1 h-4 w-4 rounded border-slate-300 bg-white text-teal-600 focus:ring-teal-500/30"
                                @checked((string) old('generate_password', '0') === '1')
                            />
                            <span>
                                <span class="block text-sm font-medium text-slate-800">{{ __('Generate new password on save') }}</span>
                                <span class="block text-xs text-slate-500">{{ __('A random password will be shown once after update.') }}</span>
                            </span>
                        </label>
                    </div>
                    @error('generate_password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                </div>

                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="submit" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-5 text-sm font-semibold">{{ __('Save changes') }}</button>
                    <a href="{{ route('admin.users.index', request()->only(['q', 'role'])) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-5 text-sm font-semibold">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </details>
</x-layouts.admin>
