<x-guest-layout
    :page-title="__('Student sign-in')"
    :eyebrow="__('Student access')"
    :heading="__('Student sign-in')"
    :description="__('Enter the index number your coordinator registered. We will take you to password sign-in or first-time verification next.')"
>
    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="index_number" :value="__('Index number')" />
            <x-text-input id="index_number" name="index_number" type="text" :value="old('index_number')" required autofocus autocomplete="username" placeholder="BC/ITS/24/047" />
            <x-input-error :messages="$errors->get('index_number')" class="mt-2" />
        </div>

        <button type="submit" class="qs-btn-primary w-full justify-center">
            {{ __('Continue') }}
        </button>
    </form>

    <p class="qs-auth-help">
        <a href="{{ route('student.password-reset.request') }}" class="qs-link">{{ __('Forgot password?') }}</a>
    </p>
</x-guest-layout>
