@props([
    'settingsHref' => null,
])

@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $parts = \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->explode(' ')->filter();
    $initials = $parts->take(2)->map(fn ($p) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $p, 0, 1)))->implode('');
    if ($initials === '') {
        $initials = '?';
    }
    $showPortraitAvatar = $user->role !== 'student' && filled($user->face_image_path ?? null);
    $avatarSrc = $showPortraitAvatar && \Illuminate\Support\Facades\Route::has('profile.face-image') ? route('profile.face-image') : null;
    $profileHref = \Illuminate\Support\Facades\Route::has('profile.edit') ? route('profile.edit') : '#';
@endphp

<div
    class="relative shrink-0"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="inline-flex min-h-[44px] min-w-[44px] max-w-full items-center gap-2 rounded-xl border border-qs-soft bg-qs-bg px-2 py-1.5 text-left text-qs-text transition hover:bg-qs-card focus:outline-none focus:ring-2 focus:ring-qs-primary/25 md:min-h-0 md:min-w-0 md:px-2 md:py-1"
        @click="open = ! open"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="menu"
        aria-controls="shell-profile-menu-panel"
    >
        <span class="relative h-9 w-9 shrink-0 overflow-hidden rounded-full border border-qs-soft bg-qs-card md:h-10 md:w-10">
            @if ($avatarSrc)
                <img src="{{ $avatarSrc }}" alt="" class="h-full w-full object-cover" width="40" height="40" loading="lazy" decoding="async" />
            @else
                <span class="flex h-full w-full items-center justify-center text-[11px] font-semibold tracking-tight text-qs-muted md:text-xs">{{ $initials }}</span>
            @endif
        </span>
        <span class="hidden min-w-0 max-w-[10rem] truncate text-sm font-medium text-qs-text lg:block">{{ $user->name }}</span>
        <svg class="hidden h-4 w-4 shrink-0 text-qs-muted sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="open = false"
        id="shell-profile-menu-panel"
        role="menu"
        class="absolute right-0 z-[60] mt-1 w-56 origin-top-right rounded-xl border border-qs-soft bg-qs-bg py-1 shadow-lg ring-1 ring-black/5"
    >
        <a
            href="{{ $profileHref }}"
            role="menuitem"
            class="block px-4 py-2.5 text-sm text-qs-text hover:bg-qs-card"
            @click="open = false"
        >{{ __('Profile') }}</a>

        @if ($settingsHref)
            <a
                href="{{ $settingsHref }}"
                role="menuitem"
                class="block px-4 py-2.5 text-sm text-qs-text hover:bg-qs-card"
                @click="open = false"
            >{{ __('Settings') }}</a>
        @endif

        <div class="my-1 border-t border-qs-soft" role="separator"></div>

        <form method="POST" action="{{ route('logout') }}" role="none">
            @csrf
            <button
                type="submit"
                role="menuitem"
                class="block w-full px-4 py-2.5 text-left text-sm font-medium text-qs-danger hover:bg-qs-danger-soft"
            >{{ __('Log out') }}</button>
        </form>
    </div>
</div>
