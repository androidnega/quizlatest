<x-guest-layout
    :page-title="__('Verify code')"
    :eyebrow="__('Password reset')"
    :heading="__('Enter the code from SMS')"
    :description="__('Use the 6-digit code we sent to your phone. It expires in a few minutes.')"
>
    <form method="POST" action="{{ url('/student/forgot-password/otp') }}" class="space-y-6">
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

        <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
            {{ __('Continue') }}
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-qs-muted">
        <a href="{{ route('student.password-reset.request') }}" class="qs-link font-medium text-qs-text">{{ __('Start over') }}</a>
    </p>
</x-guest-layout>
