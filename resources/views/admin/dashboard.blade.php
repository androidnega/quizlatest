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

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('Active exam sessions (Redis)') }}</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $activeExamSessions }}</p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Key: qs:exam_active_sessions:global') }}</p>
        </div>
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('Redis') }}</p>
            <p class="mt-2 text-lg font-semibold {{ $redisAvailable ? 'text-qs-text' : 'text-qs-danger' }}">
                {{ $redisAvailable ? __('Connected') : __('Unavailable') }}
            </p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Runtime counters and OTP depend on Redis health.') }}</p>
        </div>
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('SMS / OTP') }}</p>
            <p class="mt-2 text-sm font-semibold text-qs-text">{{ __('OTP') }}: {{ $otpEnabled ? __('On') : __('Off') }}</p>
            <p class="mt-1 text-sm font-semibold text-qs-text">{{ __('SMS channel') }}: {{ $smsEnabled ? __('On') : __('Off') }}</p>
        </div>
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-muted">{{ __('Public storage (uploads)') }}</p>
            <p class="mt-2 text-lg font-semibold text-qs-text">
                @if ($publicStorageBytes !== null)
                    {{ \Illuminate\Support\Number::fileSize($publicStorageBytes, 1) }}
                @else
                    —
                @endif
            </p>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Approximate size of the public disk.') }}</p>
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
