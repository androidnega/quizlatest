<x-guest-layout
    :page-title="__('Add your phone')"
    :eyebrow="__('Student access')"
    :heading="__('Mobile number for verification')"
    :description="__('Your coordinator did not add a phone yet. Enter your Ghana mobile number — we will send a one-time code by SMS only. Your number is saved only after you verify the code.')"
>
    <form method="POST" action="{{ route('login.first-time.phone.store') }}" class="space-y-6">
        @csrf

        <div>
            <x-input-label for="phone" :value="__('Mobile phone')" />
            <x-text-input id="phone" name="phone" type="tel" :value="old('phone')" required autofocus autocomplete="tel" placeholder="+233 24 000 0000" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="{{ route('login', ['restart' => 1]) }}" class="qs-btn-secondary justify-center px-4 py-2.5 text-sm font-semibold sm:inline-flex sm:w-auto">
                {{ __('Back') }}
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
