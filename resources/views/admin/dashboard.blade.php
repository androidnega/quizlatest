<x-layouts.admin>
    <x-slot name="title">Admin Dashboard</x-slot>
    <x-slot name="subtitle">Institution-level overview and setup</x-slot>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-soft">Universities</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $universityCount }}</p>
        </div>

        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-soft">Coordinators</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $coordinatorCount }}</p>
        </div>

        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-soft">Students</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $studentCount }}</p>
        </div>

        <div class="qs-surface rounded-xl p-5">
            <p class="text-sm text-qs-soft">Active exam sessions (Redis)</p>
            <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $activeExamSessions }}</p>
            <p class="mt-1 text-xs text-qs-soft">Key: qs:exam_active_sessions:global</p>
        </div>
    </div>

    <div class="mt-8 qs-surface rounded-xl p-6">
        <h3 class="text-lg font-semibold text-qs-text">Quick actions</h3>
        <p class="mt-2 text-sm text-qs-soft">
            Create or review universities and system-level institution settings.
        </p>
        <a href="{{ route('admin.universities.index') }}" class="qs-btn-primary mt-4 inline-flex">
            Manage universities
        </a>
        <a href="{{ route('admin.settings.index') }}" class="qs-btn-secondary mt-4 ms-3 inline-flex">
            System settings
        </a>
    </div>
</x-layouts.admin>
