@php
    $profileOpen = $errors->has('phone');
    $passwordOpen = $errors->getBag('updatePassword')->isNotEmpty();
    $yearLabel = $user->classroom?->academicYearStruct?->name
        ?? (filled($user->classroom?->academic_year) ? (string) $user->classroom->academic_year : null);
@endphp

<div
    class="w-full min-w-0 space-y-4 pb-6 text-slate-950 md:space-y-5"
    x-data="{ editProfile: @json($profileOpen), editPassword: @json($passwordOpen) }"
>
    <div class="rounded-xl border border-amber-200 bg-amber-50/80 px-4 py-3 text-sm text-amber-950">
        <p class="flex gap-2">
            <i class="fa-solid fa-circle-info mt-0.5 shrink-0 text-amber-700" aria-hidden="true"></i>
            <span>{{ __('If any academic information is wrong, contact your coordinator. You cannot change class, programme, or index number here.') }}</span>
        </p>
    </div>

    {{-- Account overview (read-only identity) --}}
    <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 sm:px-5" aria-labelledby="student-account-overview">
        <h2 id="student-account-overview" class="text-sm font-semibold text-slate-900">{{ __('Account') }}</h2>
        <div class="mt-4 flex flex-wrap items-start gap-4">
            @if (filled($user->face_image_path))
                <div class="shrink-0">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Photo') }}</p>
                    <img
                        src="{{ route('profile.face-image') }}"
                        alt=""
                        class="mt-1 h-20 w-20 rounded-xl border border-slate-200 object-cover"
                        width="80"
                        height="80"
                    />
                </div>
            @endif
            <dl class="grid min-w-0 flex-1 gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Full name') }}</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Index number') }}</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-slate-900">{{ $user->index_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Email') }}</dt>
                    <dd class="mt-0.5 break-all text-slate-900">{{ filled($user->email) ? $user->email : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Account status') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-900">{{ $user->is_active ? __('Active') : __('Inactive') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Member since') }}</dt>
                    <dd class="mt-0.5 text-slate-900">{{ $user->created_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- Academic placement (read-only) --}}
    <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 sm:px-5" aria-labelledby="student-academic-heading">
        <h2 id="student-academic-heading" class="text-sm font-semibold text-slate-900">{{ __('Academic placement') }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ __('Managed by your school. Read-only.') }}</p>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('University') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->university?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Faculty') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->program?->department?->faculty?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Department') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->program?->department?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Program') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->program?->name ?? '—' }} @if ($user->program?->code) <span class="text-slate-500">({{ $user->program->code }})</span> @endif</dd>
            </div>
            <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Level') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->level?->name ?? $user->level?->code ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Class') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $user->classroom?->name ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Academic year') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $yearLabel ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Contact you can edit --}}
    <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 sm:px-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Contact details') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('You can update your phone number. Other changes go through your coordinator.') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="hidden rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex"
                    x-show="editProfile"
                    x-cloak
                    @click="editProfile = false"
                >{{ __('Cancel') }}</button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                    x-show="! editProfile"
                    @click="editProfile = true"
                >{{ __('Edit phone') }}</button>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-3" x-show="! editProfile">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Mobile phone') }}</p>
            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $user->phone !== null && $user->phone !== '' ? $user->phone : '—' }}</p>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4" x-show="editProfile" x-cloak>
            <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('patch')

                <div>
                    <x-input-label for="phone" :value="__('Mobile phone')" />
                    <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full max-w-lg rounded-lg border-slate-200" :value="old('phone', $user->phone)" autocomplete="tel" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('Used when your school sends SMS for verification.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                    <button type="button" class="text-xs font-semibold text-slate-600 hover:text-slate-900 sm:hidden" @click="editProfile = false">{{ __('Cancel') }}</button>
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
    <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 sm:px-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Password') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Use a strong password you do not reuse elsewhere.') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="hidden rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex"
                    x-show="editPassword"
                    x-cloak
                    @click="editPassword = false"
                >{{ __('Cancel') }}</button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                    x-show="! editPassword"
                    @click="editPassword = true"
                >{{ __('Change password') }}</button>
            </div>
        </div>

        <div class="mt-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2.5 text-xs text-slate-700" x-show="! editPassword">
            <p class="flex items-start gap-2">
                <i class="fa-solid fa-lock mt-0.5 shrink-0 text-slate-500" aria-hidden="true"></i>
                <span>{{ __('Your password is hidden. Choose “Change password” to update it.') }}</span>
            </p>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4" x-show="editPassword" x-cloak>
            <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                @method('put')

                <div>
                    <x-input-label for="update_password_current_password" :value="__('Current password')" />
                    <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full max-w-lg rounded-lg border-slate-200" autocomplete="current-password" />
                    <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="update_password_password" :value="__('New password')" />
                    <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full max-w-lg rounded-lg border-slate-200" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="update_password_password_confirmation" :value="__('Confirm new password')" />
                    <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full max-w-lg rounded-lg border-slate-200" autocomplete="new-password" />
                    <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('Update password') }}</x-primary-button>
                    <button type="button" class="text-xs font-semibold text-slate-600 hover:text-slate-900 sm:hidden" @click="editPassword = false">{{ __('Cancel') }}</button>
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

    <section class="rounded-xl border border-rose-200 bg-rose-50/50 px-4 py-4 sm:px-5">
        @include('profile.partials.delete-user-form')
    </section>
</div>
