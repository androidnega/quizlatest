<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} — {{ config('app.name', 'QuizSnap') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-qs-text antialiased">
    <main class="mx-auto flex max-w-lg flex-col items-center px-6 py-16 text-center sm:py-24">
        <x-brand-logo class="mb-8 text-2xl sm:text-3xl" :href="url('/')" />

        <svg class="mb-6 h-24 w-24 text-qs-primary/90" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="12" y="16" width="56" height="64" rx="6" stroke="currentColor" stroke-width="3" fill="#fff"/>
            <path d="M24 32h32M24 44h24M24 56h28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            <circle cx="68" cy="28" r="14" fill="#faf7f2" stroke="currentColor" stroke-width="2"/>
            <path d="M62 28l4 4 8-8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <p class="text-xs font-semibold uppercase tracking-wider text-qs-muted">{{ $code }}</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-qs-text sm:text-3xl">{{ $heading }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-qs-muted sm:text-base">{{ $message }}</p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="qs-btn-primary min-h-[44px] px-5 py-2.5 text-sm font-semibold">{{ __('Go to dashboard') }}</a>
            @endauth
            <a href="{{ url('/') }}" class="qs-btn-secondary min-h-[44px] px-5 py-2.5 text-sm font-semibold">{{ __('Home') }}</a>
            @guest
                <a href="{{ route('login') }}" class="qs-btn-secondary min-h-[44px] px-5 py-2.5 text-sm font-semibold">{{ __('Student login') }}</a>
            @endguest
        </div>
    </main>
</body>
</html>
