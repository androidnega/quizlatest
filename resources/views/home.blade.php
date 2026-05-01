<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'QUIZSNAP') }} - Homepage</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-white antialiased">
    <div class="min-h-screen">
        <header class="border-b border-white/10">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">
                <h1 class="text-xl font-bold tracking-tight">QUIZSNAP</h1>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Go to Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Login</a>
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-16">
            <section class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-blue-400">Smart Exam Proctoring for Universities</p>
                    <h2 class="mt-4 text-4xl font-bold leading-tight md:text-5xl">Secure online assessments made simple for every role.</h2>
                    <p class="mt-6 max-w-xl text-base text-gray-300">
                        QUIZSNAP helps Admins manage institutions, Coordinators organize students and courses, and Students take monitored exams with confidence.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700">Open Unified Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-700">Sign In</a>
                        @endauth
                    </div>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 shadow-2xl">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl bg-white/10 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-300">Exam Integrity</p>
                            <p class="mt-2 text-lg font-semibold">Protects assessment credibility with continuous proctoring oversight.</p>
                        </div>
                        <div class="rounded-xl bg-white/10 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-300">Real-Time Monitoring</p>
                            <p class="mt-2 text-lg font-semibold">Tracks exam behavior live and supports fast incident response.</p>
                        </div>
                        <div class="rounded-xl bg-white/10 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-300">Trusted Experience</p>
                            <p class="mt-2 text-lg font-semibold">Creates a fair environment where genuine performance stands out.</p>
                        </div>
                        <div class="rounded-xl bg-white/10 p-4">
                            <p class="text-xs uppercase tracking-wide text-gray-300">Smart Visibility</p>
                            <p class="mt-2 text-lg font-semibold">Clear alerts and exam insights help teams make confident decisions.</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
