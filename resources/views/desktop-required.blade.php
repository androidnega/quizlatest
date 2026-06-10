<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('layouts.partials.viewport')
    <meta name="robots" content="noindex">
    <title>{{ __('Desktop required') }} — {{ config('app.name', 'QuizSnap') }}</title>
    @include('layouts.partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .qs-locked-bg {
            background:
                radial-gradient(60% 50% at 80% 0%, rgba(86, 174, 187, 0.10) 0%, rgba(86, 174, 187, 0) 70%),
                radial-gradient(50% 40% at 0% 100%, rgba(228, 111, 46, 0.06) 0%, rgba(228, 111, 46, 0) 70%),
                #ffffff;
        }
        .qs-locked-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: rgba(86, 174, 187, 0.10);
            color: var(--qs-primary);
            box-shadow: inset 0 0 0 1px rgba(86, 174, 187, 0.18);
        }
        .qs-locked-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.32rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid rgba(15, 52, 58, 0.10);
            background: #fff;
            color: var(--qs-muted);
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .qs-locked-step {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            text-align: left;
            padding: 0.85rem 1rem;
            border-radius: 0.85rem;
            border: 1px solid rgba(15, 52, 58, 0.08);
            background: #fff;
        }
        .qs-locked-step__num {
            flex: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.6rem;
            height: 1.6rem;
            border-radius: 9999px;
            background: rgba(86, 174, 187, 0.12);
            color: var(--qs-primary);
            font-size: 0.78rem;
            font-weight: 700;
        }
    </style>
</head>
<body class="min-h-screen qs-locked-bg font-sans text-qs-text antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl flex-col items-center justify-center px-5 py-12 text-center sm:py-16">
        <x-brand-logo class="mb-8 text-2xl sm:text-3xl" :href="url('/')" />

        <span class="qs-locked-pill">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-[var(--qs-primary)]"></span>
            {{ __('Desktop only') }}
        </span>

        <div class="qs-locked-icon mt-6">
            <i class="fa-solid fa-desktop text-2xl" aria-hidden="true"></i>
        </div>

        <h1 class="mt-6 text-balance text-2xl font-semibold tracking-tight text-qs-text sm:text-3xl">
            {{ __('Quizzes are desktop-only for now') }}
        </h1>
        <p class="mx-auto mt-3 max-w-md text-pretty text-sm leading-relaxed text-qs-muted sm:text-base">
            {{ $message ?? __('QuizSnap exams can only be taken on a desktop or laptop computer right now. The mobile attempt experience is on the way.') }}
        </p>

        <div class="mt-8 grid w-full gap-2.5 sm:gap-3">
            <div class="qs-locked-step">
                <span class="qs-locked-step__num">1</span>
                <div>
                    <p class="text-sm font-semibold text-qs-text">{{ __('Open this exam on a desktop or laptop') }}</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-qs-muted">{{ __('Your school’s computer lab, a personal laptop, or a campus workstation all work.') }}</p>
                </div>
            </div>
            <div class="qs-locked-step">
                <span class="qs-locked-step__num">2</span>
                <div>
                    <p class="text-sm font-semibold text-qs-text">{{ __('Use a modern browser') }}</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-qs-muted">{{ __('Chrome, Edge, Firefox, or Safari — kept up to date.') }}</p>
                </div>
            </div>
            <div class="qs-locked-step">
                <span class="qs-locked-step__num">3</span>
                <div>
                    <p class="text-sm font-semibold text-qs-text">{{ __('Sign in with your student credentials') }}</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-qs-muted">{{ __('Use the index number and password your coordinator registered for you.') }}</p>
                </div>
            </div>
        </div>

        <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="qs-btn-primary min-h-[44px] px-5 py-2.5 text-sm font-semibold">
                    <i class="fa-solid fa-gauge-high mr-2 text-xs" aria-hidden="true"></i>
                    {{ __('Go to dashboard') }}
                </a>
            @endauth
            <a href="{{ url('/') }}" class="qs-btn-secondary min-h-[44px] px-5 py-2.5 text-sm font-semibold">{{ __('Home') }}</a>
            @guest
                <a href="{{ route('login') }}" class="qs-btn-primary min-h-[44px] px-5 py-2.5 text-sm font-semibold">{{ __('Student login') }}</a>
            @endguest
        </div>

        <p class="mt-10 text-xs text-qs-muted">
            {{ __('You can still browse your dashboard and other pages on mobile — only the quiz attempt itself is desktop-only.') }}
        </p>
    </main>
</body>
</html>
