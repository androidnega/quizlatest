<x-guest-layout
    :page-title="__('Staff portal')"
    :eyebrow="__('Staff access')"
    :heading="__('Sign in to the staff portal')"
    :description="__('Use the email or username and password issued by your institution.')"
    content-max="max-w-md"
>
    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('staff.login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email or username')" />
            <x-text-input id="email" name="email" type="text" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" name="password" type="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex cursor-pointer items-center gap-2">
            <input id="remember_me" type="checkbox" class="rounded border-qs-soft text-qs-primary shadow-sm focus:ring-qs-primary/40" name="remember">
            <span class="text-sm text-qs-text">{{ __('Remember me') }}</span>
        </label>

        <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
            {{ __('Continue') }}
        </button>
    </form>

    <p class="mt-8 border-t border-qs-soft pt-6 text-center text-sm text-qs-muted">
        {{ __('Taking an exam?') }}
        <a href="{{ route('login') }}" class="qs-link font-medium text-qs-text">{{ __('Student sign in') }}</a>
    </p>
</x-guest-layout>
