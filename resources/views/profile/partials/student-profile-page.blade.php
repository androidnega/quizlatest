@php
    $profileOpen = $errors->has('name') || $errors->has('phone');
    $passwordOpen = $errors->getBag('updatePassword')->isNotEmpty();
@endphp

<div
    class="w-full min-w-0 space-y-5 pb-2 text-slate-950 md:space-y-6"
    x-data="{ editProfile: @json($profileOpen), editPassword: @json($passwordOpen) }"
>
    {{-- Programme (read-only) --}}
    <section class="rounded-[1.25rem] border border-teal-900/50 bg-gradient-to-br from-teal-950 via-slate-950 to-slate-900 px-5 py-5 shadow-lg shadow-slate-950/25 sm:px-6 sm:py-6">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-500/15 text-teal-100" aria-hidden="true">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold uppercase tracking-wider text-teal-100">{{ __('Your programme') }}</h2>
                <p class="mt-1 text-xs leading-relaxed text-teal-200/85">{{ __('Placement is managed by your coordinator. Contact them if something looks wrong.') }}</p>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium text-teal-300/75">{{ __('University') }}</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->university?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-teal-300/75">{{ __('Faculty') }}</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->program?->department?->faculty?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-teal-300/75">{{ __('Department') }}</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->program?->department?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-teal-300/75">{{ __('Program') }}</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->program?->name ?? '—' }} @if ($user->program?->code) <span class="text-teal-100/80">({{ $user->program->code }})</span> @endif</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-teal-300/75">{{ __('Level') }}</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->level?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-teal-300/75">{{ __('Class') }}</dt>
                        <dd class="mt-0.5 font-medium text-white">{{ $user->classroom?->name ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-teal-300/75">{{ __('Index number') }}</dt>
                        <dd class="mt-0.5 font-mono text-sm font-semibold text-teal-50">{{ $user->index_number ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    {{-- Profile details --}}
    <section class="rounded-[1.25rem] border border-slate-200 bg-white px-5 py-5 sm:px-6 sm:py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Profile details') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Name and phone you use for this account.') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="hidden rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 sm:inline-flex"
                    x-show="editProfile"
                    x-cloak
                    @click="editProfile = false"
                >{{ __('Cancel') }}</button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl bg-[var(--qs-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-95"
                    x-show="! editProfile"
                    @click="editProfile = true"
                >{{ __('Edit') }}</button>
            </div>
        </div>

        <div class="mt-5 space-y-4" x-show="! editProfile">
            <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Name') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $user->name }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Mobile phone') }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $user->phone !== null && $user->phone !== '' ? $user->phone : '—' }}</p>
            </div>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-5" x-show="editProfile" x-cloak>
            <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
                @csrf
                @method('patch')

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full max-w-lg rounded-xl border-slate-200" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="phone" :value="__('Mobile phone')" />
                    <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full max-w-lg rounded-xl border-slate-200" :value="old('phone', $user->phone)" autocomplete="tel" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Used for SMS verification when your school enables it.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                    <button type="button" class="text-sm font-semibold text-slate-600 hover:text-slate-900 sm:hidden" @click="editProfile = false">{{ __('Cancel') }}</button>
                    @if (session('status') === 'profile-updated')
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 2500)"
                            class="text-sm font-medium text-emerald-700"
                        >{{ __('Saved.') }}</p>
                    @endif
                </div>
            </form>
        </div>
    </section>

    {{-- Password --}}
    <section class="rounded-[1.25rem] border border-slate-200 bg-white px-5 py-5 sm:px-6 sm:py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Password') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Use a strong password you don’t reuse elsewhere.') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="hidden rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 sm:inline-flex"
                    x-show="editPassword"
                    x-cloak
                    @click="editPassword = false"
                >{{ __('Cancel') }}</button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 transition hover:border-[var(--qs-primary)]/40 hover:bg-[var(--qs-primary)]/5"
                    x-show="! editPassword"
                    @click="editPassword = true"
                >{{ __('Change password') }}</button>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-100 bg-violet-50/90 px-4 py-3 text-sm text-violet-950" x-show="! editPassword">
            <p class="flex items-start gap-2">
                <i class="fa-solid fa-lock mt-0.5 shrink-0 text-violet-600" aria-hidden="true"></i>
                <span>{{ __('Your password is hidden. Choose “Change password” to update it.') }}</span>
            </p>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-5" x-show="editPassword" x-cloak>
            <form method="post" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                @method('put')

                <div>
                    <x-input-label for="update_password_current_password" :value="__('Current password')" />
                    <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full max-w-lg rounded-xl border-slate-200" autocomplete="current-password" />
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="update_password_password" :value="__('New password')" />
                    <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full max-w-lg rounded-xl border-slate-200" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="update_password_password_confirmation" :value="__('Confirm new password')" />
                    <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full max-w-lg rounded-xl border-slate-200" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('Update password') }}</x-primary-button>
                    <button type="button" class="text-sm font-semibold text-slate-600 hover:text-slate-900 sm:hidden" @click="editPassword = false">{{ __('Cancel') }}</button>
                    @if (session('status') === 'password-updated')
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 2500)"
                            class="text-sm font-medium text-emerald-700"
                        >{{ __('Password updated.') }}</p>
                    @endif
                </div>
            </form>
        </div>
    </section>

    {{-- Delete account --}}
    <section class="rounded-[1.25rem] border border-rose-200/90 bg-rose-50/60 px-5 py-5 sm:px-6 sm:py-6">
        @include('profile.partials.delete-user-form')
    </section>
</div>
