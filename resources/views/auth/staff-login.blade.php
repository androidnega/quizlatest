<x-guest-layout
    :page-title="__('Staff sign in')"
    :heading="__('Staff sign in')"
    :show-header="false"
    content-max="max-w-sm"
    :compact="true"
>
    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('staff.login') }}" class="space-y-3">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email or username')" />
            <x-text-input id="email" name="email" type="text" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" name="password" type="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <label for="remember_me" class="flex cursor-pointer items-center gap-2">
            <input id="remember_me" type="checkbox" class="rounded border-qs-soft text-qs-primary shadow-sm focus:ring-qs-primary/40" name="remember">
            <span class="text-sm text-qs-text">{{ __('Remember me') }}</span>
        </label>

        <button type="submit" class="qs-btn-primary w-full justify-center py-2 text-sm font-semibold">
            {{ __('Continue') }}
        </button>
    </form>
</x-guest-layout>
