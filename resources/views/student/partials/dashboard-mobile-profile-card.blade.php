@php
    $profileCardBg = app(\App\Services\StudentDashboardBrandingService::class)->bannerUrl();
@endphp

<section class="qs-std-profile-banner relative mb-5 w-full min-w-0 max-w-none lg:hidden" aria-label="{{ __('Student profile') }}">
    <div class="relative min-h-[13.5rem] w-full overflow-hidden rounded-2xl sm:min-h-[215px] sm:rounded-[2rem]">
        <div
            class="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style="background-image: url('{{ $profileCardBg }}');"
            role="img"
            aria-hidden="true"
        ></div>
        <div class="qs-std-profile-banner__gradient absolute inset-0" aria-hidden="true"></div>

        <div class="relative z-10 flex min-h-[13.5rem] w-full min-w-0 items-center gap-4 px-5 pb-6 pt-6 text-white sm:min-h-[215px]">
            <div class="flex aspect-square h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[1.4rem] border-2 border-white/35 bg-white/15 text-xl font-bold">
                @if (filled($user->face_image_path))
                    <img src="{{ route('profile.face-image') }}" alt="" class="h-full w-full object-cover" />
                @else
                    {{ $initials }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-lg font-semibold leading-tight">{{ $lastName }}</p>
                <p class="mt-1 truncate text-sm text-white/90">
                    @if ($user->class_id === null)
                        {{ __('No class assigned') }}
                    @else
                        {{ $mobileProfileSubline ?: __('Student') }}
                    @endif
                </p>
                {{-- Year hierarchy intentionally omitted on the student
                     dashboard — coordinators and super admins are the
                     audience for academic-year context. --}}
            </div>
        </div>
    </div>
</section>
