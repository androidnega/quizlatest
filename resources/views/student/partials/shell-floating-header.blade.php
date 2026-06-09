<header class="qs-std-shell-header hidden shrink-0 lg:block">
    <div class="qs-std-page-wrap qs-std-shell-header__wrap">
        <div class="qs-std-shell-header__bar">
            <div class="qs-std-shell-header__brand">
                <x-brand-logo
                    class="qs-std-shell-brand text-lg sm:text-xl"
                    interactive
                    :href="route('dashboard')"
                />
            </div>

            <div class="qs-std-shell-header__nav hidden min-w-0 flex-1 justify-center lg:flex">
                @include('student.partials.shell-pill-nav', $navContext ?? [])
            </div>

            <div class="qs-std-shell-header__actions shrink-0">
                @include('student.partials.shell-notification-bell')
                <x-ui.shell-profile-menu icon-only />
            </div>
        </div>
    </div>
</header>
