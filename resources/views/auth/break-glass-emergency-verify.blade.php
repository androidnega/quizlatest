<x-guest-layout
    :page-title="__('Verify emergency access')"
    :eyebrow="__('Restricted')"
    :heading="__('Enter verification code')"
    :description="__('A code was sent to the configured owner phone. Codes expire quickly.')"
    content-max="max-w-md"
>
    <form method="POST" action="{{ route('breakglass.emergency.verify') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="otp" :value="__('Six-digit code')" />
            <x-text-input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
            {{ __('Verify and sign in') }}
        </button>
    </form>
</x-guest-layout>
