<x-layouts.coordinator>
    <x-slot name="title">Edit Course</x-slot>
    <x-slot name="subtitle">Update course details in your department scope</x-slot>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <form method="POST" action="{{ route('coordinator.courses.update', $course) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Course Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $course->title) }}" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-600 focus:ring-blue-600" />
                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="code" class="block text-sm font-medium text-gray-700">Course Code</label>
                <input id="code" name="code" type="text" value="{{ old('code', $course->code) }}" required class="mt-1 block w-full rounded-lg border-gray-300 focus:border-blue-600 focus:ring-blue-600" />
                @error('code')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Department</label>
                <input type="text" value="{{ $course->department?->name ?? 'N/A' }}" disabled class="mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 text-gray-600" />
            </div>

            <div class="flex items-center gap-2">
                <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $course->is_active) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-600" />
                <label for="is_active" class="text-sm text-gray-700">Course is active</label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('coordinator.courses.index') }}" class="rounded-lg bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Update Course</button>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
