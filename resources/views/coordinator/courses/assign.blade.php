<x-layouts.coordinator>
    <x-slot name="title">Assign Courses to Class</x-slot>
    <x-slot name="subtitle">Select a class and assign multiple courses in the same department</x-slot>

    <div class="rounded-xl bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('coordinator.courses.assign.edit') }}" class="grid gap-3 sm:grid-cols-4">
            <div class="sm:col-span-3">
                <label for="class_id" class="block text-sm font-medium text-gray-700">Class</label>
                <select id="class_id" name="class_id" class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Select class</option>
                    @foreach ($classes as $classroom)
                        <option value="{{ $classroom->id }}" {{ (int) request('class_id', $selectedClass?->id) === $classroom->id ? 'selected' : '' }}>
                            {{ $classroom->name }} ({{ $classroom->program?->name }} - {{ $classroom->level?->name }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">Load</button>
            </div>
        </form>
    </div>

    @if ($selectedClass)
        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm">
            <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                <span class="font-semibold">Selected class:</span> {{ $selectedClass->name }}
                ({{ $selectedClass->program?->name }} - {{ $selectedClass->level?->name }})
            </div>

            <form method="POST" action="{{ route('coordinator.courses.assign.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="class_id" value="{{ $selectedClass->id }}">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Courses</label>
                    <div class="mt-2 max-h-72 overflow-y-auto rounded-lg border border-gray-200 p-3">
                        @forelse ($courses as $course)
                            <label class="mb-2 flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="course_ids[]" value="{{ $course->id }}" {{ in_array($course->id, old('course_ids', $assignedCourseIds), true) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-600" />
                                <span>{{ $course->code }} - {{ $course->title }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">No courses available in your departments.</p>
                        @endforelse
                    </div>
                    @error('course_ids')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    @error('course_ids.*')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('coordinator.courses.index') }}" class="rounded-lg bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300">Back</a>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Assignments</button>
                </div>
            </form>
        </div>
    @endif
</x-layouts.coordinator>
