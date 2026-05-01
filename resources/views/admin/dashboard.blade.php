<x-layouts.admin>
    <x-slot name="title">Admin Dashboard</x-slot>
    <x-slot name="subtitle">Institution-level overview and setup</x-slot>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="qs-surface border border-[#CFAC81] rounded-lg p-5">
            <p class="text-sm text-gray-600">Universities</p>
            <p class="mt-2 text-3xl font-semibold qs-heading">{{ $universityCount }}</p>
        </div>

        <div class="qs-surface border border-[#CFAC81] rounded-lg p-5">
            <p class="text-sm text-gray-600">Coordinators</p>
            <p class="mt-2 text-3xl font-semibold qs-heading">{{ $coordinatorCount }}</p>
        </div>

        <div class="qs-surface border border-[#CFAC81] rounded-lg p-5">
            <p class="text-sm text-gray-600">Students</p>
            <p class="mt-2 text-3xl font-semibold qs-heading">{{ $studentCount }}</p>
        </div>
    </div>

    <div class="mt-8 qs-surface border border-[#CFAC81] rounded-lg p-6">
        <h3 class="text-lg font-semibold qs-heading">Quick Actions</h3>
        <p class="mt-2 text-sm text-gray-700">
            Begin Phase 2 setup by creating or reviewing universities and system-level institution settings.
        </p>
        <a href="{{ route('admin.universities.index') }}" class="mt-4 inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-[#CFAC81] border border-[#CFAC81] rounded-md hover:bg-[#b9966f]">
            Manage Universities
        </a>
    </div>
</x-layouts.admin>
