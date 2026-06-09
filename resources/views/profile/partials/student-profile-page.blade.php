@php
    $profileOpen = $errors->has('phone') || $errors->has('profile_photo');
    $passwordOpen = $errors->getBag('updatePassword')->isNotEmpty();
    $yearLabel = $user->classroom?->academicYearStruct?->name
        ?? (filled($user->classroom?->academic_year) ? (string) $user->classroom->academic_year : null);
    $initials = \Illuminate\Support\Str::of((string) $user->name)
        ->trim()
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn ($w) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($w, 0, 1)))
        ->implode('');
@endphp

<div
    class="qs-profile-page"
    x-data="{ editProfile: @json($profileOpen), editPassword: @json($passwordOpen), editPhoto: @json($errors->has('profile_photo')) }"
>
    {{-- Hero --}}
    <section class="qs-profile-hero" aria-labelledby="qs-profile-hero-name">
        <div class="qs-profile-hero__bg" aria-hidden="true"></div>
        <div class="qs-profile-hero__inner">
            <div class="qs-profile-hero__photo">
                @if (filled($user->face_image_path))
                    <img src="{{ route('profile.face-image') }}" alt="" width="96" height="96" />
                @else
                    <span aria-hidden="true">{{ $initials }}</span>
                @endif
            </div>
            <div class="qs-profile-hero__body">
                <p class="qs-profile-hero__eyebrow">{{ __('Student profile') }}</p>
                <h2 id="qs-profile-hero-name" class="qs-profile-hero__name">{{ $user->name }}</h2>
                <div class="qs-profile-hero__chips">
                    @if (filled($user->index_number))
                        <span class="qs-profile-chip">
                            <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
                            <span class="font-mono">{{ $user->index_number }}</span>
                        </span>
                    @endif
                    @if (filled($user->classroom?->name))
                        <span class="qs-profile-chip">
                            <i class="fa-solid fa-users" aria-hidden="true"></i>
                            {{ $user->classroom->name }}
                        </span>
                    @endif
                    <span class="qs-profile-chip qs-profile-chip--status {{ $user->is_active ? 'is-on' : 'is-off' }}">
                        <span class="qs-profile-chip__dot" aria-hidden="true"></span>
                        {{ $user->is_active ? __('Active') : __('Inactive') }}
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- Read-only notice --}}
    <div class="qs-profile-note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>{{ __('If any academic information is wrong, contact your coordinator. You cannot change class, programme, or index number here.') }}</span>
    </div>

    {{-- Profile photo --}}
    <section class="qs-profile-card" aria-labelledby="qs-profile-photo-heading">
        <div class="qs-profile-card__head">
            <div>
                <h3 id="qs-profile-photo-heading" class="qs-profile-card__title">{{ __('Profile photo') }}</h3>
                <p class="qs-profile-card__sub" x-show="! editPhoto">{{ __('The photo your school sees on your account.') }}</p>
                <p class="qs-profile-card__sub" x-show="editPhoto" x-cloak>{{ __('Upload a clear photo of yourself. Crop it so your head fits inside the frame before saving.') }}</p>
            </div>
            <span class="qs-profile-card__icon qs-profile-card__icon--photo" aria-hidden="true">
                <i class="fa-solid fa-camera"></i>
            </span>
        </div>

        {{-- Read-only display --}}
        <div class="qs-profile-row" x-show="! editPhoto">
            <div class="qs-profile-photo-display" aria-hidden="true">
                @if (filled($user->face_image_path))
                    <img src="{{ route('profile.face-image') }}" alt="" width="64" height="64" />
                @else
                    <span>{{ $initials }}</span>
                @endif
            </div>
            <div class="qs-profile-row__main">
                <p class="qs-profile-row__label">{{ __('Profile photo') }}</p>
                <p class="qs-profile-row__value">{{ filled($user->face_image_path) ? __('Photo uploaded') : __('No photo yet') }}</p>
            </div>
            <button type="button" class="qs-profile-btn qs-profile-btn--ghost" @click="editPhoto = true">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                {{ filled($user->face_image_path) ? __('Change photo') : __('Add photo') }}
            </button>
        </div>

        {{-- Editor (cropping UI) --}}
        <div x-show="editPhoto" x-cloak>
            @include('profile.partials.student-profile-photo-crop')
            <div class="qs-profile-photo-actions">
                <button type="button" class="qs-profile-btn qs-profile-btn--ghost" @click="editPhoto = false">{{ __('Done') }}</button>
            </div>
        </div>
    </section>

    {{-- Account --}}
    <section class="qs-profile-card" aria-labelledby="qs-profile-account-heading">
        <div class="qs-profile-card__head">
            <div>
                <h3 id="qs-profile-account-heading" class="qs-profile-card__title">{{ __('Account') }}</h3>
                <p class="qs-profile-card__sub">{{ __('Your identity on QuizSnap.') }}</p>
            </div>
            <span class="qs-profile-card__icon qs-profile-card__icon--account" aria-hidden="true">
                <i class="fa-solid fa-user"></i>
            </span>
        </div>
        <dl class="qs-profile-grid">
            <div class="qs-profile-field">
                <dt>{{ __('Full name') }}</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Index number') }}</dt>
                <dd class="font-mono">{{ $user->index_number ?? '—' }}</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Email') }}</dt>
                <dd class="break-all">{{ filled($user->email) ? $user->email : '—' }}</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Account status') }}</dt>
                <dd>{{ $user->is_active ? __('Active') : __('Inactive') }}</dd>
            </div>
            <div class="qs-profile-field qs-profile-field--wide">
                <dt>{{ __('Member since') }}</dt>
                <dd>{{ $user->created_at?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Academic --}}
    <section class="qs-profile-card" aria-labelledby="qs-profile-academic-heading">
        <div class="qs-profile-card__head">
            <div>
                <h3 id="qs-profile-academic-heading" class="qs-profile-card__title">{{ __('Academic placement') }}</h3>
                <p class="qs-profile-card__sub">{{ __('Managed by your school. Read-only.') }}</p>
            </div>
            <span class="qs-profile-card__icon qs-profile-card__icon--academic" aria-hidden="true">
                <i class="fa-solid fa-graduation-cap"></i>
            </span>
        </div>
        <dl class="qs-profile-grid">
            <div class="qs-profile-field">
                <dt>{{ __('University') }}</dt>
                <dd>{{ $user->university?->name ?? '—' }}</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Faculty') }}</dt>
                <dd>{{ $user->program?->department?->faculty?->name ?? '—' }}</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Department') }}</dt>
                <dd>{{ $user->program?->department?->name ?? '—' }}</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Program') }}</dt>
                <dd>{{ $user->program?->name ?? '—' }} @if ($user->program?->code) <span class="text-slate-500">({{ $user->program->code }})</span> @endif</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Level') }}</dt>
                <dd>{{ $user->level?->name ?? $user->level?->code ?? '—' }}</dd>
            </div>
            <div class="qs-profile-field">
                <dt>{{ __('Class') }}</dt>
                <dd>{{ $user->classroom?->name ?? '—' }}</dd>
            </div>
            <div class="qs-profile-field qs-profile-field--wide">
                <dt>{{ __('Academic year') }}</dt>
                <dd>{{ $yearLabel ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Contact --}}
    <section class="qs-profile-card" aria-labelledby="qs-profile-contact-heading">
        <div class="qs-profile-card__head">
            <div>
                <h3 id="qs-profile-contact-heading" class="qs-profile-card__title">{{ __('Contact details') }}</h3>
                <p class="qs-profile-card__sub">{{ __('You can update your phone number. Other changes go through your coordinator.') }}</p>
            </div>
            <span class="qs-profile-card__icon qs-profile-card__icon--contact" aria-hidden="true">
                <i class="fa-solid fa-phone"></i>
            </span>
        </div>

        <div class="qs-profile-row" x-show="! editProfile">
            <div class="qs-profile-row__main">
                <p class="qs-profile-row__label">{{ __('Mobile phone') }}</p>
                <p class="qs-profile-row__value">{{ $user->phone !== null && $user->phone !== '' ? $user->phone : '—' }}</p>
            </div>
            <button type="button" class="qs-profile-btn qs-profile-btn--primary" @click="editProfile = true">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                {{ __('Edit phone') }}
            </button>
        </div>

        <div class="qs-profile-form" x-show="editProfile" x-cloak>
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
                    <button type="submit" class="qs-profile-btn qs-profile-btn--primary">{{ __('Save') }}</button>
                    <button type="button" class="qs-profile-btn qs-profile-btn--ghost" @click="editProfile = false">{{ __('Cancel') }}</button>
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
    <section class="qs-profile-card" aria-labelledby="qs-profile-password-heading">
        <div class="qs-profile-card__head">
            <div>
                <h3 id="qs-profile-password-heading" class="qs-profile-card__title">{{ __('Password') }}</h3>
                <p class="qs-profile-card__sub">{{ __('Use a strong password you do not reuse elsewhere.') }}</p>
            </div>
            <span class="qs-profile-card__icon qs-profile-card__icon--password" aria-hidden="true">
                <i class="fa-solid fa-lock"></i>
            </span>
        </div>

        <div class="qs-profile-row" x-show="! editPassword">
            <div class="qs-profile-row__main">
                <p class="qs-profile-row__label">{{ __('Password') }}</p>
                <p class="qs-profile-row__value">••••••••</p>
            </div>
            <button type="button" class="qs-profile-btn qs-profile-btn--ghost" @click="editPassword = true">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                {{ __('Change password') }}
            </button>
        </div>

        <div class="qs-profile-form" x-show="editPassword" x-cloak>
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
                    <button type="submit" class="qs-profile-btn qs-profile-btn--primary">{{ __('Update password') }}</button>
                    <button type="button" class="qs-profile-btn qs-profile-btn--ghost" @click="editPassword = false">{{ __('Cancel') }}</button>
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

    {{-- Danger zone --}}
    <section class="qs-profile-card qs-profile-card--danger" aria-label="{{ __('Delete Account') }}">
        @include('profile.partials.delete-user-form')
    </section>
</div>
