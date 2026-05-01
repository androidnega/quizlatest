<x-layouts.admin>
    <x-slot name="title">Edit Coordinator</x-slot>
    <x-slot name="subtitle">Update coordinator details and department assignments</x-slot>

    <div class="qs-surface border border-[#CFAC81] rounded-lg p-6">
        <form method="POST" action="{{ route('admin.coordinators.update', $coordinator) }}">
            @csrf
            @method('PUT')
            @include('admin.coordinators._form', [
                'submitLabel' => 'Update Coordinator',
                'coordinator' => $coordinator,
                'selectedDepartmentIds' => $selectedDepartmentIds,
            ])
        </form>
    </div>
</x-layouts.admin>
