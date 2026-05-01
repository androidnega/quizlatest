<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Coordinator</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-white text-gray-900">
        <div class="min-h-screen flex bg-white">
            <aside class="hidden md:flex md:w-64 md:flex-col border-r border-[#CFAC81] qs-surface">
                <div class="px-6 py-5 border-b border-[#CFAC81]">
                    <h1 class="text-lg font-semibold qs-heading">Coordinator Panel</h1>
                    <p class="text-xs text-gray-600 mt-1">{{ auth()->user()->name }}</p>
                </div>
                <nav class="flex-1 px-4 py-4 space-y-2">
                    <a href="{{ route('coordinator.dashboard') }}" class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('coordinator.dashboard') ? 'bg-[#CFAC81] text-white' : 'text-gray-700 hover:bg-white' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('coordinator.students.index') }}" class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('coordinator.students.*') ? 'bg-[#CFAC81] text-white' : 'text-gray-700 hover:bg-white' }}">
                        Students
                    </a>
                    <a href="{{ route('coordinator.programs.index') }}" class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('coordinator.programs.*') ? 'bg-[#CFAC81] text-white' : 'text-gray-700 hover:bg-white' }}">
                        Programs
                    </a>
                    <a href="{{ route('coordinator.levels.index') }}" class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('coordinator.levels.*') ? 'bg-[#CFAC81] text-white' : 'text-gray-700 hover:bg-white' }}">
                        Levels
                    </a>
                    <a href="{{ route('coordinator.classes.index') }}" class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('coordinator.classes.*') ? 'bg-[#CFAC81] text-white' : 'text-gray-700 hover:bg-white' }}">
                        Classes
                    </a>
                    <a href="{{ route('coordinator.courses.index') }}" class="block px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('coordinator.courses.*') ? 'bg-[#CFAC81] text-white' : 'text-gray-700 hover:bg-white' }}">
                        Courses
                    </a>
                </nav>
            </aside>

            <div class="flex-1">
                <header class="bg-white border-b border-[#CFAC81]">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold qs-heading">{{ $title ?? 'Coordinator Dashboard' }}</h2>
                            @isset($subtitle)
                                <p class="text-sm text-gray-600">{{ $subtitle }}</p>
                            @endisset
                        </div>

                        <div class="flex items-center gap-4">
                            <a href="{{ route('dashboard') }}" class="text-sm qs-link">Home</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-[#CFAC81] border border-[#CFAC81] rounded-md hover:bg-[#b9966f]">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-md border border-[#CFAC81] qs-surface px-4 py-3 text-sm text-gray-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
