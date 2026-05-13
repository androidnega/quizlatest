@if ($user->role === 'student')
    <x-layouts.student>
        <x-slot name="title">{{ __('Profile') }}</x-slot>
        <x-slot name="subtitle">{{ __('Account details and security') }}</x-slot>

        @include('profile.partials.student-profile-page')
    </x-layouts.student>
@else
    <x-app-layout>
        <x-slot name="header">
            <h2 class="qs-heading text-xl font-semibold leading-tight">
                {{ __('Profile') }}
            </h2>
        </x-slot>

        <div class="py-12">
            @include('profile.partials.edit-stack')
        </div>
    </x-app-layout>
@endif
