<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Admin</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            sage: '#70866F',
                            camel: '#CFAC81',
                            beige: '#EBE6DE',
                        }
                    }
                }
            }
        </script>
    </head>
    <body class="font-sans antialiased bg-white text-gray-900">
        <div class="min-h-screen flex bg-white">
            <aside class="hidden md:flex md:w-64 md:flex-col border-r border-beige bg-white">
                <div class="px-6 py-5 border-b border-beige">
                    <h1 class="text-lg font-semibold text-sage">QUIZSNAP Admin</h1>
                    <p class="text-xs text-gray-600 mt-1">{{ auth()->user()->name }}</p>
                </div>
                <nav class="flex-1 px-4 py-4 space-y-2">
                    <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-camel text-white shadow-sm' : 'text-gray-700 hover:bg-beige' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('admin.universities.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.universities.*') ? 'bg-camel text-white shadow-sm' : 'text-gray-700 hover:bg-beige' }}">
                        Universities
                    </a>
                    <a href="{{ route('admin.coordinators.index') }}" class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('admin.coordinators.*') ? 'bg-camel text-white shadow-sm' : 'text-gray-700 hover:bg-beige' }}">
                        Coordinators
                    </a>
                </nav>
            </aside>

            <div class="flex-1">
                <header class="bg-white border-b border-beige">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-sage">{{ $title ?? 'Admin Panel' }}</h2>
                            @isset($subtitle)
                                <p class="text-sm text-gray-600">{{ $subtitle }}</p>
                            @endisset
                        </div>

                        <div class="flex items-center gap-4">
                            <a href="{{ route('dashboard') }}" class="text-sm text-sage hover:text-sage/80">Student View</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-3 py-2 text-xs font-semibold uppercase tracking-wider text-white bg-camel border border-camel rounded-lg hover:bg-camel/90">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-lg border border-camel bg-beige px-4 py-3 text-sm text-gray-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
