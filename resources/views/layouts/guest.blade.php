<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-qs-bg text-qs-text">
        <div class="flex min-h-screen flex-col items-center bg-qs-bg pt-6 sm:justify-center sm:pt-0">
            <div>
                <a href="/">
                    <x-application-logo class="h-20 w-20 fill-current text-qs-text" />
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden border border-qs-soft bg-qs-card px-6 py-6 shadow-sm sm:max-w-md sm:rounded-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
