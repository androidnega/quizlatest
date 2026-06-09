<x-guest-layout
    :page-title="__('New password')"
    :eyebrow="__('Password reset')"
    :heading="__('Choose a new password')"
    :description="__('Your phone was verified. Pick a strong password you have not used here before.')"
>
    <form method="POST" action="{{ url('/student/reset-password') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input id="password" name="password" type="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
        </div>

        <button type="submit" class="qs-btn-primary w-full justify-center">
            {{ __('Save password') }}
        </button>
    </form>
</x-guest-layout>
