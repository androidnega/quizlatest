<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Admin</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-qs-bg text-qs-text">
        <div class="flex min-h-screen bg-qs-bg">
            <aside class="hidden border-r border-qs-soft bg-qs-bg md:flex md:w-64 md:flex-col">
                <div class="border-b border-qs-soft px-6 py-5">
                    <h1 class="text-lg font-semibold text-qs-text">QUIZSNAP Admin</h1>
                    <p class="mt-1 text-xs text-qs-soft">{{ auth()->user()->name }}</p>
                </div>
                <nav class="flex-1 space-y-1 px-4 py-4">
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'bg-qs-accent text-white shadow-sm' : 'text-qs-text hover:bg-qs-card' }} block rounded-lg px-3 py-2 text-sm font-medium">
                        Dashboard
                    </a>
                    <a href="{{ route('admin.universities.index') }}" class="{{ request()->routeIs('admin.universities.*') ? 'bg-qs-accent text-white shadow-sm' : 'text-qs-text hover:bg-qs-card' }} block rounded-lg px-3 py-2 text-sm font-medium">
                        Universities
                    </a>
                    <a href="{{ route('admin.coordinators.index') }}" class="{{ request()->routeIs('admin.coordinators.*') ? 'bg-qs-accent text-white shadow-sm' : 'text-qs-text hover:bg-qs-card' }} block rounded-lg px-3 py-2 text-sm font-medium">
                        Coordinators
                    </a>
                    <a href="{{ route('admin.settings.index') }}" class="{{ request()->routeIs('admin.settings.*') ? 'bg-qs-accent text-white shadow-sm' : 'text-qs-text hover:bg-qs-card' }} block rounded-lg px-3 py-2 text-sm font-medium">
                        Settings
                    </a>
                </nav>
            </aside>

            <div class="flex-1">
                <header class="border-b border-qs-soft bg-qs-bg">
                    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div>
                            <h2 class="text-xl font-semibold text-qs-text">{{ $title ?? 'Admin Panel' }}</h2>
                            @isset($subtitle)
                                <p class="text-sm text-qs-soft">{{ $subtitle }}</p>
                            @endisset
                        </div>

                        <div class="flex items-center gap-4">
                            <a href="{{ route('dashboard') }}" class="text-sm text-qs-text underline-offset-2 hover:underline">Student View</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="qs-btn-primary text-xs font-semibold uppercase tracking-wider">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
