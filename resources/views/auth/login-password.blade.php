<x-guest-layout
    :page-title="__('Enter password')"
    :eyebrow="__('Student access')"
    :heading="__('Welcome back')"
    :description="__('Enter the password for your student account.')"
>
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <div class="mb-6 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text shadow-sm">
        <p class="text-qs-muted">{{ __('Index number') }}</p>
        <p class="mt-1 font-semibold text-qs-text">{{ $index_number }}</p>
    </div>

    <form method="POST" action="{{ route('login.password') }}" class="space-y-6">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" name="password" type="password" required autofocus autocomplete="current-password" placeholder="{{ __('Enter your password') }}" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label class="flex items-center gap-2 text-sm text-qs-text">
            <input type="checkbox" name="remember" value="1" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" @checked(old('remember')) />
            <span>{{ __('Remember this device') }}</span>
        </label>

        <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
            {{ __('Sign in') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-qs-muted">
        <a href="{{ route('login', ['restart' => 1]) }}" class="qs-link font-medium text-qs-text">{{ __('Use a different index number') }}</a>
    </p>

    <p class="mt-4 text-center text-sm text-qs-muted">
        <a href="{{ route('student.password-reset.request') }}" class="qs-link font-medium text-qs-text">{{ __('Forgot password?') }}</a>
    </p>
</x-guest-layout>
