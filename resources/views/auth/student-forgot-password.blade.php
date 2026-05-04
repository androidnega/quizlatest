<x-guest-layout
    :page-title="__('Reset password')"
    :eyebrow="__('Students')"
    :heading="__('Reset with phone')"
    :description="__('Enter your index number or the phone number on your student account. We send a one-time code by SMS only.')"
>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ url('/student/forgot-password') }}" class="space-y-6">
        @csrf

        <div>
            <x-input-label for="identifier" :value="__('Index number or phone')" />
            <x-text-input id="identifier" name="identifier" type="text" :value="old('identifier')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
        </div>

        <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
            {{ __('Send code') }}
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-qs-muted">
        <a href="{{ route('login') }}" class="qs-link font-medium text-qs-text">{{ __('Back to sign-in') }}</a>
    </p>
</x-guest-layout>
