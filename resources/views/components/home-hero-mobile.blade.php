@php
    $mobileHeroImage = asset('images/home/quizsnap-homepage-hero-mobile-assessments-banner.jpg');
@endphp

<div
    {{ $attributes->merge([
        'class' => 'home-hero-mobile w-full min-w-0',
        'data-home-hero-mobile' => '1',
    ]) }}
>
    <p class="sr-only">
        {{ __('Secure school assessments promotional banner with quizzes, exams, and results.') }}
    </p>

    <figure class="w-full overflow-hidden rounded-2xl shadow-lg shadow-qs-text/10 ring-1 ring-qs-soft/80 sm:rounded-3xl">
        <img
            src="{{ $mobileHeroImage }}"
            alt=""
            width="1024"
            height="576"
            decoding="async"
            fetchpriority="high"
            sizes="100vw"
            class="block h-auto w-full max-w-full object-contain object-center"
        />
    </figure>

    <div class="mt-6 flex justify-center sm:mt-8">
        <a href="{{ route('login') }}" class="qs-btn-primary min-h-[48px] w-full max-w-sm px-6 py-3 text-center text-sm font-semibold sm:text-base">
            {{ __('Student login') }}
        </a>
    </div>
</div>
