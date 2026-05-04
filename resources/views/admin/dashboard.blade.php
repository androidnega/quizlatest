<x-layouts.admin>
    <x-slot name="title">Admin Dashboard</x-slot>
    <x-slot name="subtitle">Institution-wide operations</x-slot>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('Universities') }}</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $universityCount }}</p>
        </div>
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('Coordinators') }}</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $coordinatorCount }}</p>
        </div>
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('Students') }}</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $studentCount }}</p>
        </div>
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('Published exams') }}</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $publishedExamCount }}</p>
        </div>
    </div>

    <div class="mt-6">
        <h3 class="text-base font-semibold text-qs-text">{{ __('Platform health') }}</h3>
        <p class="mt-1 text-sm text-qs-muted">{{ __('No secrets are shown. Values reflect this server only.') }}</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Active exam sessions') }}</p>
                <p class="mt-2 text-2xl font-semibold text-qs-text">
                    @if ($activeSessions['value'] !== null)
                        {{ $activeSessions['value'] }}
                    @else
                        {{ __('Unavailable') }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-qs-muted">
                    @if ($activeSessions['source'] === 'redis')
                        {{ __('Source: Redis counter') }}
                    @elseif ($activeSessions['source'] === 'database_estimate')
                        {{ __('Source: database estimate (active + paused)') }}
                    @else
                        {{ __('Source: not available') }}
                    @endif
                </p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Redis') }}</p>
                <p class="mt-2 text-lg font-semibold text-qs-text">
                    @if ($redisMode === 'connected')
                        {{ __('Enabled and connected') }}
                    @elseif ($redisMode === 'fallback_active')
                        {{ __('Fallback active (cache / database)') }}
                    @elseif ($redisMode === 'disabled_by_admin')
                        {{ __('Disabled by admin') }}
                    @else
                        {{ __('Unavailable') }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-qs-muted">{{ __('Ping') }}: {{ $redisPing ? __('OK') : __('Fail') }}</p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Live sockets (Reverb)') }}</p>
                <p class="mt-2 text-lg font-semibold text-qs-text">
                    @if ($liveSocketsMode === 'enabled_configured')
                        {{ __('Enabled and configured') }}
                    @elseif ($liveSocketsMode === 'disabled_by_admin')
                        {{ __('Disabled by admin') }}
                    @else
                        {{ __('Misconfigured') }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-qs-muted">
                    @if ($liveSocketsClientHint === 'fallback_polling_available')
                        {{ __('Students can use polling fallback.') }}
                    @elseif ($liveSocketsClientHint === 'polling_available')
                        {{ __('Polling is available if sockets fail.') }}
                    @else
                        {{ __('Polling fallback is disabled in settings.') }}
                    @endif
                </p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Vite build') }}</p>
                <p class="mt-2 text-lg font-semibold {{ $viteBuildPresent && $viteBuildDirPresent ? 'text-qs-text' : 'text-qs-danger' }}">
                    {{ $viteBuildPresent && $viteBuildDirPresent ? __('Ready') : __('Incomplete') }}
                </p>
                <p class="mt-1 text-xs text-qs-muted">
                    {{ __('public/build') }}: {{ $viteBuildDirPresent ? __('exists') : __('missing') }};
                    {{ __('manifest.json') }}: {{ $viteBuildPresent ? __('exists') : __('missing') }}
                </p>
                <p class="mt-1 text-xs text-qs-muted">{{ __('Run npm run build before production; deploy public/build.') }}</p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Database') }}</p>
                <p class="mt-2 text-lg font-semibold {{ $dbConnected ? 'text-qs-text' : 'text-qs-danger' }}">
                    {{ $dbConnected ? __('Connected') : __('Not connected') }}
                </p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Private storage') }}</p>
                <p class="mt-2 text-lg font-semibold {{ $privateWritable ? 'text-qs-text' : 'text-qs-danger' }}">
                    {{ $privateWritable ? __('Writable') : __('Not writable') }}
                </p>
                <p class="mt-1 text-xs text-qs-muted">
                    @if ($privateStorageBytes !== null)
                        {{ \Illuminate\Support\Number::fileSize($privateStorageBytes, 1) }}
                    @else
                        —
                    @endif
                </p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Queue driver') }}</p>
                <p class="mt-2 text-lg font-semibold text-qs-text">{{ $queueDriver }}</p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('Public storage (legacy)') }}</p>
                <p class="mt-2 text-lg font-semibold text-qs-text">
                    @if ($publicStorageBytes !== null)
                        {{ \Illuminate\Support\Number::fileSize($publicStorageBytes, 1) }}
                    @else
                        —
                    @endif
                </p>
            </div>
            <div class="qs-surface rounded-xl p-5">
                <p class="text-sm text-qs-muted">{{ __('SMS / OTP policy') }}</p>
                <p class="mt-2 text-sm font-semibold text-qs-text">{{ __('OTP') }}: {{ $otpEnabled ? __('On') : __('Off') }}</p>
                <p class="mt-1 text-sm font-semibold text-qs-text">{{ __('SMS channel') }}: {{ $smsEnabled ? __('On') : __('Off') }}</p>
            </div>
        </div>
    </div>

    <div class="mt-8 qs-surface rounded-xl p-6">
        <h3 class="text-lg font-semibold text-qs-text">{{ __('Governance & settings') }}</h3>
        <p class="mt-2 text-sm text-qs-muted">{{ __('Manage institutions, coordinators, and platform-wide exam policy.') }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ route('admin.universities.index') }}" class="qs-btn-primary inline-flex">{{ __('Universities') }}</a>
            <a href="{{ route('admin.coordinators.index') }}" class="qs-btn-secondary inline-flex">{{ __('Coordinators') }}</a>
            <a href="{{ route('admin.settings.index') }}" class="qs-btn-secondary inline-flex">{{ __('System settings') }}</a>
            <a href="{{ route('admin.academic-reset-snapshots.index') }}" class="qs-btn-secondary inline-flex">{{ __('Academic reset snapshots') }}</a>
        </div>
    </div>
</x-layouts.admin>
