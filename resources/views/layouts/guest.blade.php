<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        @include('layouts.partials.viewport')
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $app = config('app.name', 'QuizSnap');
            $docTitle = $pageTitle ?? $heading;
            $fullTitle = $docTitle ? $docTitle.' — '.$app : $app.' — '.__('Sign in');
        @endphp
        <title>{{ $fullTitle }}</title>
        @include('layouts.partials.favicon')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased qs-auth-page">
        <div class="qs-auth-shell">
            @if ($showHeader)
                <header class="qs-auth-topbar">
                    <div class="qs-auth-topbar__inner">
                        <x-brand-logo class="text-base sm:text-lg" :href="route('home')" />
                        <a href="{{ route('home') }}" class="qs-auth-topbar__link">
                            <i class="fa-solid fa-arrow-left text-[10px]" aria-hidden="true"></i>
                            {{ __('Back to home') }}
                        </a>
                    </div>
                </header>
            @endif

            <main class="qs-auth-main">
                <div class="qs-auth-stage {{ $contentMax }}">
                    <div @class([
                        'qs-auth-card',
                        'qs-auth-card--compact' => $compact,
                    ])>
                        @if ($heading)
                            <header class="qs-auth-card__head">
                                <span class="qs-auth-mark" aria-hidden="true">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </span>
                                @if ($eyebrow)
                                    <p class="qs-auth-eyebrow">{{ $eyebrow }}</p>
                                @endif
                                <h1 class="qs-auth-heading">{{ $heading }}</h1>
                                @if ($description)
                                    <p class="qs-auth-description">{{ $description }}</p>
                                @endif
                            </header>
                        @endif

                        {{ $slot }}
                    </div>

                    <p class="qs-auth-foot">
                        &copy; {{ now()->year }} {{ $app }} · {{ __('Secure student access') }}
                    </p>
                </div>
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
