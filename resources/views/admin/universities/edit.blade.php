<x-layouts.admin>
    <x-slot name="title">Edit University</x-slot>
    <x-slot name="subtitle">Update institution details and settings</x-slot>

    <div class="qs-surface border border-[#CFAC81] rounded-lg p-6">
        <form method="POST" action="{{ route('admin.universities.update', $university) }}">
            @csrf
            @method('PUT')
            @include('admin.universities._form', ['submitLabel' => 'Update University', 'university' => $university])
        </form>
    </div>
</x-layouts.admin>
