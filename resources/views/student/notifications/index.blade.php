<x-layouts.student>
    <x-slot name="title">{{ __('Notifications') }}</x-slot>
    <x-slot name="subtitle">{{ __('Timely updates from your assessments and results.') }}</x-slot>

    @php
        $tz = config('app.timezone');

        $resolveMeta = static function (string $id): array {
            $kind = strtok($id, ':');

            return match ($kind) {
                'newpub' => ['icon' => 'fa-bullhorn', 'tone' => 'assessments'],
                'due' => ['icon' => 'fa-hourglass-half', 'tone' => 'assignments'],
                'start' => ['icon' => 'fa-circle-play', 'tone' => 'assessments'],
                'held' => ['icon' => 'fa-eye', 'tone' => 'notices'],
                'pend' => ['icon' => 'fa-pen-to-square', 'tone' => 'results'],
                'auto' => ['icon' => 'fa-circle-exclamation', 'tone' => 'assignments'],
                'noclass' => ['icon' => 'fa-user-graduate', 'tone' => 'notices'],
                default => ['icon' => 'fa-bell', 'tone' => 'slate'],
            };
        };

        $unread = (int) ($unreadCount ?? 0);
        $total = count($notices);
    @endphp

    <div class="mx-auto w-full max-w-3xl space-y-5 pb-6">
        {{-- Header card --}}
        <section class="qs-notif-header">
            <div class="qs-notif-header__main">
                <span class="qs-notif-header__icon" aria-hidden="true">
                    <i class="fa-solid fa-bell"></i>
                </span>
                <div class="qs-notif-header__body">
                    <p class="qs-notif-header__eyebrow">{{ __('Inbox') }}</p>
                    <h2 class="qs-notif-header__title">
                        @if ($unread > 0)
                            {{ trans_choice('{1} :count new notification|[2,*] :count new notifications', $unread, ['count' => $unread]) }}
                        @elseif ($total > 0)
                            {{ __('You are all caught up.') }}
                        @else
                            {{ __('Nothing new right now.') }}
                        @endif
                    </h2>
                    <p class="qs-notif-header__hint">{{ __('These messages are generated from your own activity. Nothing here is sent by other students.') }}</p>
                </div>
            </div>
            @if ($total > 0)
                <span class="qs-notif-header__count" aria-label="{{ __('Total notifications') }}">
                    {{ $total }}
                </span>
            @endif
        </section>

        @if ($notices === [])
            <div class="qs-notif-empty">
                <span class="qs-notif-empty__icon" aria-hidden="true">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
                <p class="qs-notif-empty__title">{{ __('You are all caught up') }}</p>
                <p class="qs-notif-empty__text">{{ __('No notices right now. We will let you know when something needs your attention.') }}</p>
            </div>
        @else
            <ul class="qs-notif-list">
                @foreach ($notices as $n)
                    @php
                        $meta = $resolveMeta((string) ($n['id'] ?? ''));
                        $when = isset($n['at']) ? \Illuminate\Support\Carbon::parse($n['at'])->timezone($tz) : null;
                        $isUnread = (bool) ($n['is_unread'] ?? false);
                    @endphp
                    <li class="qs-notif-item qs-notif-item--{{ $meta['tone'] }} {{ $isUnread ? 'is-unread' : '' }}">
                        <span class="qs-notif-item__icon" aria-hidden="true">
                            <i class="fa-solid {{ $meta['icon'] }}"></i>
                        </span>
                        <div class="qs-notif-item__body">
                            <div class="qs-notif-item__head">
                                <p class="qs-notif-item__title">
                                    {{ $n['title'] }}
                                    @if ($isUnread)
                                        <span class="qs-notif-item__badge" aria-label="{{ __('Unread') }}">{{ __('NEW') }}</span>
                                    @endif
                                </p>
                                @if ($when)
                                    <time class="qs-notif-item__time" datetime="{{ $n['at'] }}">
                                        {{ $when->diffForHumans() }}
                                    </time>
                                @endif
                            </div>
                            @if (! empty($n['body']))
                                <p class="qs-notif-item__text">{{ $n['body'] }}</p>
                            @endif
                            <div class="qs-notif-item__foot">
                                @if ($when)
                                    <span class="qs-notif-item__stamp">{{ $when->format('M j, Y · H:i') }}</span>
                                @endif
                                @if (! empty($n['href']))
                                    <a href="{{ $n['href'] }}" class="qs-notif-item__cta">
                                        {{ __('Open') }}
                                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.student>
