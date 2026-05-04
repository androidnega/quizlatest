<x-guest-layout
    :page-title="__('Emergency access')"
    :eyebrow="__('Restricted')"
    :heading="__('Emergency owner access')"
    :description="__('For disaster recovery only. All use is logged. If you are not authorized, leave this page.')"
    content-max="max-w-md"
>
    <form method="POST" action="{{ route('breakglass.emergency.store') }}" class="space-y-5" autocomplete="off">
        @csrf

        <div>
            <x-input-label for="privileged_username" :value="__('Privileged username')" />
            <x-text-input id="privileged_username" name="privileged_username" type="text" :value="old('privileged_username')" required autofocus />
            <x-input-error :messages="$errors->get('privileged_username')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="emergency_secret" :value="__('Emergency secret')" />
            <x-text-input id="emergency_secret" name="emergency_secret" type="password" required />
            <x-input-error :messages="$errors->get('emergency_secret')" class="mt-2" />
        </div>

        <button type="submit" class="qs-btn-primary w-full justify-center py-2.5 text-sm font-semibold">
            {{ __('Continue') }}
        </button>
    </form>
</x-guest-layout>
