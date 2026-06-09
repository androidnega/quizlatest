@php
    $notices = $studentHeaderNotices ?? [];
    $noticeCount = (int) ($studentNoticeCount ?? count($notices));
@endphp

<div
    class="relative"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="qs-std-shell-icon-btn relative"
        @click="open = ! open"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="true"
        aria-controls="student-notification-panel"
        aria-label="{{ __('Notifications') }}"
    >
        <i class="fa-solid fa-bell text-base" aria-hidden="true"></i>
        @if ($noticeCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#EF3340] px-1 text-[10px] font-bold leading-none text-white tabular-nums">
                {{ $noticeCount > 99 ? '99+' : $noticeCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        id="student-notification-panel"
        role="region"
        aria-label="{{ __('Recent notifications') }}"
        class="qs-std-notification-panel absolute end-0 top-[calc(100%+0.5rem)] z-50 w-[min(100vw-2rem,22rem)] overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-lg"
        x-transition
    >
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-semibold text-slate-900">{{ __('Notifications') }}</p>
            <a
                href="{{ route('student.notifications.index') }}"
                class="text-xs font-medium text-sky-700 hover:underline"
                @click="open = false"
            >
                {{ __('View all') }}
            </a>
        </div>

        @if ($notices === [])
            <p class="px-4 py-6 text-center text-sm text-slate-600">{{ __('You are all caught up.') }}</p>
        @else
            <ul class="max-h-[min(20rem,50vh)] overflow-y-auto divide-y divide-slate-100">
                @foreach ($notices as $n)
                    @php
                        $noticeHref = $n['href'] ?? route('student.notifications.index');
                        $unread = (bool) ($n['is_unread'] ?? false);
                    @endphp
                    <li>
                        <a
                            href="{{ $noticeHref }}"
                            class="block px-4 py-3 transition-colors hover:bg-slate-50 {{ $unread ? 'bg-sky-50/40' : '' }}"
                            @click="open = false"
                        >
                            <p class="flex items-center gap-2 text-sm font-medium text-slate-900">
                                @if ($unread)
                                    <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-sky-500" aria-hidden="true"></span>
                                @endif
                                <span class="truncate">{{ $n['title'] }}</span>
                            </p>
                            @if (($n['body'] ?? '') !== '')
                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-600">{{ $n['body'] }}</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
