<x-layouts.coordinator>
    <x-slot name="title">Coordinator Dashboard</x-slot>
    <x-slot name="subtitle">Department-scoped academic and student activity overview</x-slot>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="qs-surface border border-[#CFAC81] rounded-lg p-5">
            <p class="text-sm text-gray-600">Total Students</p>
            <p class="mt-2 text-3xl font-semibold qs-heading">{{ $studentCount }}</p>
        </div>

        <div class="qs-surface border border-[#CFAC81] rounded-lg p-5">
            <p class="text-sm text-gray-600">Active Programs</p>
            <p class="mt-2 text-3xl font-semibold qs-heading">{{ $activeProgramCount }}</p>
        </div>

        <div class="qs-surface border border-[#CFAC81] rounded-lg p-5">
            <p class="text-sm text-gray-600">Active Classes</p>
            <p class="mt-2 text-3xl font-semibold qs-heading">{{ $classCount }}</p>
        </div>

        <div class="qs-surface border border-[#CFAC81] rounded-lg p-5">
            <p class="text-sm text-gray-600">Courses Assigned</p>
            <p class="mt-2 text-3xl font-semibold qs-heading">{{ $assignedCourseCount }}</p>
        </div>
    </div>

    <div class="mt-8 qs-surface border border-[#CFAC81] rounded-lg p-6">
        <h3 class="text-lg font-semibold qs-heading">Next Steps</h3>
        <p class="mt-2 text-sm text-gray-700">
            Use the sidebar to manage students, programs, levels, classes, and courses for your assigned departments.
        </p>
    </div>
</x-layouts.coordinator>
