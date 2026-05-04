<section>
    <header>
        <h2 class="text-lg font-medium text-qs-text">
            {{ __('Profile information') }}
        </h2>

        <p class="mt-1 text-sm text-qs-muted">
            {{ __('Update your name and contact details. Academic placement is managed by your coordinator.') }}
        </p>
    </header>

    <div class="mt-6 rounded-xl border border-qs-soft bg-qs-card p-4 text-sm text-qs-text">
        <h3 class="font-semibold text-qs-text">{{ __('Your programme') }}</h3>
        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
            <div>
                <dt class="text-qs-muted">{{ __('University') }}</dt>
                <dd>{{ $user->university?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-qs-muted">{{ __('Faculty') }}</dt>
                <dd>{{ $user->program?->department?->faculty?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-qs-muted">{{ __('Department') }}</dt>
                <dd>{{ $user->program?->department?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-qs-muted">{{ __('Program') }}</dt>
                <dd>{{ $user->program?->name ?? '—' }} @if ($user->program?->code) ({{ $user->program->code }}) @endif</dd>
            </div>
            <div>
                <dt class="text-qs-muted">{{ __('Level') }}</dt>
                <dd>{{ $user->level?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-qs-muted">{{ __('Class') }}</dt>
                <dd>{{ $user->classroom?->name ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-qs-muted">{{ __('Index number') }}</dt>
                <dd class="font-mono">{{ $user->index_number ?? '—' }}</dd>
            </div>
        </dl>
        @if ($user->face_image_path)
            <div class="mt-4">
                <p class="text-qs-muted">{{ __('Portrait on file') }}</p>
                <img src="{{ route('profile.face-image') }}" alt="" class="mt-2 h-24 w-24 rounded-lg border border-qs-soft object-cover" />
            </div>
        @endif
    </div>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="phone" :value="__('Mobile phone')" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" />
            <p class="mt-1 text-xs text-qs-muted">{{ __('Used for SMS verification when your school enables it.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email (optional)')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" autocomplete="email" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-qs-muted"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
