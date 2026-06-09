<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
        @include('layouts.partials.viewport')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'QuizSnap'))</title>
    @include('layouts.partials.favicon')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-qs-bg font-sans antialiased text-qs-text">
    <main class="mx-auto flex w-full max-w-full flex-col items-center justify-start overflow-y-auto px-4 py-6 sm:px-6 sm:py-8 md:py-10 [scrollbar-gutter:stable]">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
