<x-guest-layout
    :page-title="__('Add your phone')"
    :show-header="false"
    :eyebrow="__('Student access')"
    :heading="__('Mobile number for verification')"
    :description="__('Your coordinator did not add a phone yet. Enter your Ghana mobile number — we will send a one-time code by SMS only. Your number is saved only after you verify the code.')"
>
    <form method="POST" action="{{ route('login.first-time.phone.store') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="phone" :value="__('Mobile phone')" />
            <x-text-input id="phone" name="phone" type="tel" :value="old('phone')" required autofocus autocomplete="tel" placeholder="+233 24 000 0000" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div class="qs-auth-actions">
            <a href="{{ route('login', ['restart' => 1]) }}" class="qs-btn-secondary justify-center">
                {{ __('Back') }}
            </a>
            <button type="submit" class="qs-btn-primary flex-1 justify-center sm:flex-none sm:min-w-[8rem]">
                {{ __('Send code') }}
            </button>
        </div>
    </form>

    <p class="qs-auth-help">
        {{ __('Coordinator or admin?') }}
    </p>
</x-guest-layout>
