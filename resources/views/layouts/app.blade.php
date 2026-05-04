<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'QuizSnap') }}</title>
        @include('layouts.partials.favicon')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-qs-bg text-qs-text">
        <div class="min-h-screen bg-qs-bg">
            @include('layouts.navigation')

            @isset($header)
                <header class="border-b border-qs-soft bg-qs-bg">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
