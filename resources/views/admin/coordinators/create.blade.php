<x-layouts.admin>
    <x-slot name="title">Create Coordinator</x-slot>
    <x-slot name="subtitle">Add a coordinator and assign relevant departments</x-slot>

    <div class="qs-surface border border-[#CFAC81] rounded-lg p-6">
        <form method="POST" action="{{ route('admin.coordinators.store') }}">
            @csrf
            @include('admin.coordinators._form', ['submitLabel' => 'Create Coordinator'])
        </form>
    </div>
</x-layouts.admin>
