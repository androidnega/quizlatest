<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Quiz hero demo') }} — {{ config('app.name', 'QuizSnap') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-qs-text antialiased">
    <main class="mx-auto flex max-w-xl flex-col items-center justify-center px-4 py-16">
        <x-online-quiz-hero class="w-full" heading-id="oq-hero-demo-heading" />
    </main>
</body>
</html>
