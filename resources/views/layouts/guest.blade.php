<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <body class="font-sans antialiased bg-qs-bg text-qs-text">
        <div class="relative flex min-h-screen flex-col bg-qs-bg">
            @if ($showHeader)
                <header class="relative z-10 border-b border-qs-soft bg-qs-bg">
                    <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
                        <x-brand-logo class="text-xl sm:text-2xl" :href="route('home')" />
                        <a href="{{ route('home') }}" class="qs-btn-secondary text-sm font-semibold normal-case tracking-normal">
                            {{ __('Back to home') }}
                        </a>
                    </div>
                </header>
            @endif

            <main class="relative z-10 flex flex-1 flex-col items-center justify-center px-6 py-12 sm:py-16">
                <div class="w-full {{ $contentMax }}">
                    <div @class([
                        'rounded-2xl border border-qs-soft bg-qs-bg shadow-sm',
                        'p-5 sm:p-6' => $compact,
                        'p-8 sm:p-10' => ! $compact,
                    ])>
                        @if ($heading)
                            <header @class(['mb-4' => $compact, 'mb-8' => ! $compact])>
                                @if ($eyebrow)
                                    <p class="text-xs font-semibold uppercase tracking-wider text-qs-muted">{{ $eyebrow }}</p>
                                @endif
                                <h1 @class([
                                    'font-semibold tracking-tight text-qs-text',
                                    'mt-2' => $eyebrow,
                                    'text-xl sm:text-2xl sm:leading-snug' => $compact,
                                    'text-2xl sm:text-[1.65rem] sm:leading-snug' => ! $compact,
                                ])>
                                    {{ $heading }}
                                </h1>
                                @if ($description)
                                    <p @class([
                                        'text-sm leading-relaxed text-qs-muted',
                                        'mt-2' => $compact,
                                        'mt-3' => ! $compact,
                                    ])>{{ $description }}</p>
                                @endif
                            </header>
                        @endif

                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
