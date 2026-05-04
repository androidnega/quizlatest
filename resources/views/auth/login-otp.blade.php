<x-guest-layout
    :page-title="__('Verify code')"
    :eyebrow="__('Verification')"
    :heading="__('Enter your one-time code')"
    :description="__('Check the SMS on your phone. The code expires in a few minutes.')"
>
    <form method="POST" action="{{ url('/login/otp') }}" class="space-y-6">
        @csrf

        <div>
            <x-input-label for="otp" :value="__('One-time code')" />
            <x-text-input
                id="otp"
                name="otp"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                class="text-center text-lg tracking-[0.35em] placeholder:tracking-normal"
                placeholder="••••••"
                required
                autofocus
                autocomplete="one-time-code"
            />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('login.first-time') }}" class="qs-btn-secondary justify-center px-4 py-2.5 text-sm font-semibold sm:inline-flex sm:w-auto">
                {{ __('Back') }}
            </a>
            <button type="submit" class="qs-btn-primary flex-1 justify-center py-2.5 text-sm font-semibold sm:flex-none sm:min-w-[9rem]">
                {{ __('Sign in') }}
            </button>
        </div>
    </form>

    <p class="mt-8 border-t border-qs-soft pt-6 text-center text-sm text-qs-muted">
        <a href="{{ route('login') }}" class="qs-link font-medium text-qs-text">{{ __('Already finished setup? Sign in with password') }}</a>
    </p>

    <p class="mt-4 text-center text-sm text-qs-muted">
        {{ __('Coordinator or admin?') }}
        <a href="{{ route('staff.login') }}" class="qs-link font-medium text-qs-text">{{ __('Staff sign in') }}</a>
    </p>
</x-guest-layout>
