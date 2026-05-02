<x-layouts.admin>
    <x-slot name="title">Create University</x-slot>
    <x-slot name="subtitle">Add a new institution to the platform</x-slot>

    <div class="qs-surface rounded-lg p-6">
        <form method="POST" action="{{ route('admin.universities.store') }}">
            @csrf
            @include('admin.universities._form', ['submitLabel' => 'Create University'])
        </form>
    </div>
</x-layouts.admin>
