<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $app = config('app.name', 'QUIZSNAP');
            $docTitle = $pageTitle ?? $heading;
            $fullTitle = $docTitle ? $docTitle.' — '.$app : $app.' — '.__('Sign in');
        @endphp
        <title>{{ $fullTitle }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-qs-bg text-qs-text">
        <div class="relative flex min-h-screen flex-col bg-qs-bg">
            <header class="relative z-10 border-b border-qs-soft bg-qs-bg">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
                    <a href="{{ route('home') }}" class="text-xl font-semibold tracking-tight text-qs-text transition hover:opacity-90">
                        {{ config('app.name', 'QUIZSNAP') }}
                    </a>
                    <a href="{{ route('home') }}" class="qs-btn-secondary text-sm font-semibold normal-case tracking-normal">
                        {{ __('Back to home') }}
                    </a>
                </div>
            </header>

            <main class="relative z-10 flex flex-1 flex-col items-center justify-center px-6 py-12 sm:py-16">
                <div class="w-full {{ $contentMax }}">
                    <div class="rounded-2xl border border-qs-soft bg-qs-bg p-8 shadow-sm sm:p-10">
                        @if ($heading)
                            <header class="mb-8">
                                @if ($eyebrow)
                                    <p class="text-xs font-semibold uppercase tracking-wider text-qs-muted">{{ $eyebrow }}</p>
                                @endif
                                <h1 class="{{ $eyebrow ? 'mt-2' : '' }} text-2xl font-semibold tracking-tight text-qs-text sm:text-[1.65rem] sm:leading-snug">
                                    {{ $heading }}
                                </h1>
                                @if ($description)
                                    <p class="mt-3 text-sm leading-relaxed text-qs-muted">{{ $description }}</p>
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
