<x-layouts.coordinator>
    <x-slot name="title">Courses</x-slot>
    <x-slot name="subtitle">Manage courses within your assigned departments</x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <a href="{{ route('coordinator.courses.assign.edit') }}" class="rounded-lg bg-qs-card px-4 py-2 text-sm font-semibold text-qs-muted hover:bg-qs-soft">
                Assign to Classes
            </a>
        </div>
        <a href="{{ route('coordinator.courses.create') }}" class="qs-btn-primary text-sm">
            Add Course
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-qs-card">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-muted">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-qs-muted">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courses as $course)
                        <tr class="hover:bg-qs-card">
                            <td class="px-4 py-3 text-sm text-qs-text">{{ $course->title }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $course->code }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $course->is_active ? 'bg-qs-accent/20 text-qs-text border border-qs-accent/30' : 'bg-qs-card text-qs-muted' }}">
                                    {{ $course->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('coordinator.courses.edit', $course) }}" class="rounded-lg bg-qs-card px-3 py-1.5 text-xs font-semibold text-qs-muted hover:bg-qs-soft">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('coordinator.courses.toggle-status', $course) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="{{ $course->is_active ? 'qs-btn-danger-sm' : 'qs-btn-primary px-3 py-1.5 text-xs' }}">
                                            {{ $course->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-qs-muted">No courses found in your departments.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $courses->links() }}
        </div>
    </div>
</x-layouts.coordinator>
