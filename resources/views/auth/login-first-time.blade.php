<x-guest-layout
    :page-title="__('First-time sign-in')"
    :eyebrow="__('Student access')"
    :heading="__('Verify your index number')"
    :description="__('Enter the index number your coordinator registered. If a phone number is on your record, we send a one-time SMS code. Otherwise, you will be asked for your mobile number first — it is only saved after you verify the code.')"
>
    <form method="POST" action="{{ route('login.first-time.store') }}" class="space-y-6">
        @csrf

        <div>
            <x-input-label for="index_number" :value="__('Index number')" />
            <x-text-input id="index_number" name="index_number" type="text" :value="old('index_number')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('index_number')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('login') }}" class="qs-btn-secondary justify-center px-4 py-2.5 text-sm font-semibold sm:inline-flex sm:w-auto">
                {{ __('Back to sign-in') }}
            </a>
            <button type="submit" class="qs-btn-primary flex-1 justify-center py-2.5 text-sm font-semibold sm:flex-none sm:min-w-[9rem]">
                {{ __('Send code') }}
            </button>
        </div>
    </form>

    <p class="mt-8 border-t border-qs-soft pt-6 text-center text-sm text-qs-muted">
        {{ __('Coordinator or admin?') }}
        <a href="{{ route('staff.login') }}" class="qs-link font-medium text-qs-text">{{ __('Staff sign in') }}</a>
    </p>
</x-guest-layout>
