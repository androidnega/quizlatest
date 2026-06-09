<x-guest-layout
    :page-title="__('Enter password')"
    :eyebrow="__('Student access')"
    :heading="__('Welcome back')"
    :description="__('Enter the password for your student account.')"
>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="qs-auth-chip">
        <span class="qs-auth-chip__icon" aria-hidden="true">
            <i class="fa-solid fa-id-badge"></i>
        </span>
        <div class="qs-auth-chip__body">
            <p class="qs-auth-chip__label">{{ __('Index number') }}</p>
            <p class="qs-auth-chip__value">{{ $index_number }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('login.password') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" name="password" type="password" required autofocus autocomplete="current-password" placeholder="{{ __('Enter your password') }}" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label class="qs-auth-remember">
            <input type="checkbox" name="remember" value="1" @checked(old('remember')) />
            <span>{{ __('Remember this device') }}</span>
        </label>

        <button type="submit" class="qs-btn-primary w-full justify-center">
            {{ __('Sign in') }}
        </button>
    </form>

    <p class="qs-auth-help">
        <a href="{{ route('login', ['restart' => 1]) }}" class="qs-link">{{ __('Use a different index number') }}</a>
    </p>

    <p class="qs-auth-help">
        <a href="{{ route('student.password-reset.request') }}" class="qs-link">{{ __('Forgot password?') }}</a>
    </p>
</x-guest-layout>
