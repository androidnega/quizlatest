<x-app-layout>
    <x-slot name="header">
        <h2 class="qs-heading text-xl font-semibold leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="qs-card sm:p-8">
                <div class="max-w-xl">
                    @if ($user->role === 'student')
                        @include('profile.partials.update-profile-information-form-student')
                    @else
                        @include('profile.partials.update-profile-information-form')
                    @endif
                </div>
            </div>

            <div class="qs-card sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="qs-card sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
