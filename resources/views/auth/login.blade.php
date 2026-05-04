<x-guest-layout
    :page-title="__('Student sign-in')"
    :eyebrow="__('Student access')"
    :heading="__('Sign in to QUIZSNAP')"
    :description="__('Use your index number or the phone number on your account with the password you chose during setup. First-time students start below with their index number.')"
>
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="space-y-8">
        <section>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">{{ __('Returning students') }}</h2>
            <form method="POST" action="{{ url('/login') }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <x-input-label for="identifier" :value="__('Index number or phone')" />
                    <x-text-input id="identifier" name="identifier" type="text" :value="old('identifier')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" name="password" type="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <label class="flex items-center gap-2 text-sm text-qs-text">
                    <input type="checkbox" name="remember" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" @checked(old('remember')) />
                    <span>{{ __('Remember this device') }}</span>
                </label>

                <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
                    {{ __('Sign in') }}
                </button>
                <p class="text-center text-sm text-qs-muted">
                    <a href="{{ route('student.password-reset.request') }}" class="qs-link font-medium text-qs-text">{{ __('Forgot password?') }}</a>
                </p>
            </form>
        </section>

        <div class="border-t border-qs-soft pt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">{{ __('First-time sign-in') }}</h2>
            <p class="mt-2 text-sm text-qs-muted">
                {{ __('If your school just added you, verify your index number with a one-time code, then complete your profile, face enrollment, and password.') }}
            </p>
            <a href="{{ route('login.first-time') }}" class="qs-btn-secondary mt-4 inline-flex w-full justify-center py-2.5 text-sm font-semibold">
                {{ __('Start first-time setup') }}
            </a>
        </div>
    </div>

    <p class="mt-8 border-t border-qs-soft pt-6 text-center text-sm text-qs-muted">
        {{ __('Coordinator or admin?') }}
        <a href="{{ route('staff.login') }}" class="qs-link font-medium text-qs-text">{{ __('Staff sign in') }}</a>
    </p>
</x-guest-layout>
