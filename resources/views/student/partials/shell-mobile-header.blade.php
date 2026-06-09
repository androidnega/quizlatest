@php
    $nc = $navContext ?? [];
    $dashActive = $nc['dashActive'] ?? false;
    $workActive = $nc['workActive'] ?? false;
    $assignmentsActive = $nc['assignmentsActive'] ?? false;
    $resultsActive = $nc['resultsActive'] ?? false;
    $helpActive = $nc['helpActive'] ?? false;
    $notificationsActive = $nc['notificationsActive'] ?? false;
    $materialsActive = $nc['materialsActive'] ?? false;
    $showMaterialsNav = $nc['showMaterialsNav'] ?? false;
    $materialsHref = $nc['materialsHref'] ?? route('student.practice.materials.index');
    $studentWorkHref = $nc['studentWorkHref'] ?? route('student.work.index');
    $headerNoticeCount = (int) ($nc['headerNoticeCount'] ?? 0);

    $menuLinks = [
        ['href' => route('dashboard'), 'label' => __('Home'), 'icon' => 'fa-house', 'active' => $dashActive],
        ['href' => $studentWorkHref, 'label' => __('Assessments'), 'icon' => 'fa-clipboard-list', 'active' => $workActive],
        ['href' => route('student.assignments.index'), 'label' => __('Assignments'), 'icon' => 'fa-file-pen', 'active' => $assignmentsActive],
        ['href' => route('student.results.index'), 'label' => __('Results'), 'icon' => 'fa-square-poll-vertical', 'active' => $resultsActive],
    ];
    if ($showMaterialsNav) {
        $menuLinks[] = ['href' => $materialsHref, 'label' => __('Materials'), 'icon' => 'fa-folder-open', 'active' => $materialsActive];
    }
    $menuLinks[] = ['href' => route('student.notifications.index'), 'label' => __('Notifications'), 'icon' => 'fa-bell', 'active' => $notificationsActive, 'badge' => $headerNoticeCount];
    $menuLinks[] = ['href' => route('student.help'), 'label' => __('Help'), 'icon' => 'fa-circle-question', 'active' => $helpActive];
@endphp

<div
    class="qs-std-mobile-header lg:hidden"
    x-data="{ menuOpen: false }"
    @keydown.escape.window="menuOpen = false"
>
    <div class="qs-std-mobile-header__bar">
        <a href="{{ route('dashboard') }}" class="qs-std-mobile-header__logo">
            <x-brand-logo class="text-lg" interactive />
        </a>
        <div class="qs-std-mobile-header__actions">
            @include('student.partials.shell-notification-bell')
            <button
                type="button"
                class="qs-std-mobile-header__menu-btn"
                @click="menuOpen = true"
                :aria-expanded="menuOpen ? 'true' : 'false'"
                aria-controls="qs-std-mobile-menu"
                aria-label="{{ __('Open menu') }}"
            >
                <i class="fa-solid fa-bars text-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div
        x-show="menuOpen"
        x-cloak
        id="qs-std-mobile-menu"
        class="qs-std-mobile-menu"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Student menu') }}"
    >
        <div class="qs-std-mobile-menu__backdrop" @click="menuOpen = false" aria-hidden="true"></div>
        <nav class="qs-std-mobile-menu__sheet">
            <div class="qs-std-mobile-menu__head">
                <p class="qs-std-mobile-menu__title">{{ __('Menu') }}</p>
                <button
                    type="button"
                    class="qs-std-mobile-header__menu-btn"
                    @click="menuOpen = false"
                    aria-label="{{ __('Close menu') }}"
                >
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>
            <ul class="qs-std-mobile-menu__list">
                @foreach ($menuLinks as $link)
                    <li>
                        <a
                            href="{{ $link['href'] }}"
                            class="qs-std-mobile-menu__link {{ ($link['active'] ?? false) ? 'is-active' : '' }}"
                            @click="menuOpen = false"
                        >
                            <i class="fa-solid {{ $link['icon'] }} w-5 text-center text-[15px] opacity-80" aria-hidden="true"></i>
                            <span class="min-w-0 flex-1">{{ $link['label'] }}</span>
                            @if (! empty($link['badge']) && (int) $link['badge'] > 0)
                                <span class="qs-std-mobile-menu__badge">{{ min(99, (int) $link['badge']) }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-right text-[10px] opacity-40" aria-hidden="true"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="qs-std-mobile-menu__foot">
                <x-ui.shell-profile-menu />
            </div>
        </nav>
    </div>
</div>
