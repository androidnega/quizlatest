<x-layouts.coordinator>
    <x-slot name="title">Coordinator Dashboard</x-slot>
    <x-slot name="subtitle">Department-scoped academic and student activity overview</x-slot>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-qs-muted">Total Students</p>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-qs-card text-qs-text">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <path d="M20 8v6M23 11h-6"/>
                    </svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $studentCount }}</p>
        </div>

        <div class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-qs-muted">Active Programs</p>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-qs-card text-qs-text">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 6h16M4 12h16M4 18h10"/>
                    </svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $activeProgramCount }}</p>
        </div>

        <div class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-qs-muted">Active Classes</p>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-qs-card text-qs-text">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M3 7h18M5 7v12h14V7M9 11h6M9 15h4"/>
                    </svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $classCount }}</p>
        </div>

        <div class="rounded-xl border border-qs-soft bg-white p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-qs-muted">Courses Assigned</p>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-qs-card text-qs-text">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 6v12M6 12h12"/>
                        <path d="M4 4h16v16H4z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $assignedCourseCount }}</p>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-qs-soft bg-qs-soft/30 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-qs-text">Next Steps</h3>
        <p class="mt-2 text-sm text-qs-muted leading-6">
            Continue with student onboarding and academic setup using the left navigation. All data shown here is scoped to your assigned departments.
        </p>
    </div>
</x-layouts.coordinator>
