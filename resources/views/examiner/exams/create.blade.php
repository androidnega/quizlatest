<x-layouts.coordinator>
    <x-slot name="title">Create exam</x-slot>
    <x-slot name="subtitle">Choose a course and basic settings</x-slot>

    <div class="max-w-xl bg-white rounded-xl border border-qs-soft shadow-sm p-6">
        <form method="post" action="{{ route('examiner.exams.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-qs-muted mb-1">Course</label>
                <select name="course_id" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm">
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->title }}</option>
                    @endforeach
                </select>
                @error('course_id')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-qs-muted mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
                @error('title')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-qs-muted mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm">{{ old('description') }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-qs-muted mb-1">Duration (minutes)</label>
                <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 30) }}" min="1" max="600" required class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm" />
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="rounded-lg bg-qs-accent px-4 py-2 text-sm font-semibold text-qs-text hover:opacity-95">Save &amp; open builder</button>
                <a href="{{ route('examiner.exams.index') }}" class="rounded-lg border border-qs-soft px-4 py-2 text-sm font-semibold text-qs-muted hover:bg-qs-card">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
