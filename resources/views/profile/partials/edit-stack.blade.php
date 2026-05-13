<div class="mx-auto w-full min-w-0 max-w-2xl space-y-6 md:max-w-3xl xl:max-w-4xl">
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
