<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">
            {{ __('Home') }}
        </h2>
    </x-slot>

    <div class="overflow-x-hidden py-10">
        <div class="mx-auto w-full max-w-lg px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-qs-soft bg-qs-card p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-qs-text">{{ __('Welcome') }}</h3>
                <p class="mt-2 text-sm text-qs-muted">{{ __('Choose where to continue.') }}</p>

                <ul class="mt-6 space-y-3">
                    @if (auth()->user()->role === 'admin')
                        <li>
                            <a href="{{ route('dashboard') }}" class="qs-btn-primary flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg text-center text-sm font-semibold">
                                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                                {{ __('Admin dashboard') }}
                            </a>
                        </li>
                    @endif
                    @if (auth()->user()->role === 'coordinator')
                        <li>
                            <a href="{{ route('dashboard') }}" class="qs-btn-primary flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg text-center text-sm font-semibold">
                                <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                                {{ __('Coordinator dashboard') }}
                            </a>
                        </li>
                    @endif
                    <li>
                        <a href="{{ route('profile.edit') }}" class="qs-btn-secondary flex min-h-[44px] w-full items-center justify-center gap-2 rounded-lg border border-qs-soft text-center text-sm font-semibold">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            {{ __('Profile') }}
                        </a>
                    </li>
                </ul>

                @if (! in_array(auth()->user()->role, ['admin', 'coordinator'], true))
                    <p class="mt-6 text-sm text-qs-muted">{{ __('If you expected another workspace, contact your administrator.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
