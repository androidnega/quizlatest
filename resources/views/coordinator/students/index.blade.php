<x-layouts.coordinator>
    <x-slot name="title">Students</x-slot>
    <x-slot name="subtitle">Manage students in your assigned departments</x-slot>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('coordinator.students.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, index number or email" class="w-64 rounded-lg border border-camel bg-white px-3 py-2 text-sm focus:border-camel focus:ring-camel" />

            <select name="program_id" class="rounded-lg border border-camel bg-white px-3 py-2 text-sm focus:border-camel focus:ring-camel">
                <option value="">All Programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->name }}</option>
                @endforeach
            </select>

            <select name="level_id" class="rounded-lg border border-camel bg-white px-3 py-2 text-sm focus:border-camel focus:ring-camel">
                <option value="">All Levels</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}" @selected(($filters['level_id'] ?? '') == $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="rounded-lg border border-camel bg-camel px-3 py-2 text-sm font-medium text-white hover:bg-camel/90">Filter</button>
        </form>

        <div class="flex items-center gap-2">
            <a href="{{ route('coordinator.students.template') }}" class="rounded-lg border border-camel bg-white px-3 py-2 text-sm text-gray-700 hover:bg-beige">Download CSV Template</a>
            <a href="{{ route('coordinator.students.upload') }}" class="rounded-lg border border-camel bg-camel px-3 py-2 text-sm font-semibold text-white hover:bg-camel/90">Upload CSV</a>
        </div>
    </div>

    <form method="POST" action="{{ route('coordinator.students.bulk-status') }}">
        @csrf
        <div class="mb-3 flex items-center gap-2">
            <select name="action" class="rounded-lg border border-camel bg-white px-3 py-2 text-sm focus:border-camel focus:ring-camel" required>
                <option value="">Bulk Actions</option>
                <option value="activate">Activate Selected</option>
                <option value="deactivate">Deactivate Selected</option>
            </select>
            <button type="submit" class="rounded-lg border border-camel bg-white px-3 py-2 text-sm text-gray-700 hover:bg-beige">Apply</button>
        </div>

        <div class="overflow-hidden rounded-xl border border-beige bg-white shadow-sm">
            <table class="min-w-full divide-y divide-beige">
                <thead class="bg-beige/60">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">
                            <input type="checkbox" onclick="document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked)" class="rounded border-camel text-camel focus:ring-camel">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Index Number</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Program</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Level</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-sage">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-beige">
                    @forelse ($students as $student)
                        <tr class="hover:bg-beige/30">
                            <td class="px-4 py-3 text-sm">
                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="student-checkbox rounded border-camel text-camel focus:ring-camel">
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $student->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $student->index_number }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $student->program?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $student->level?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $student->email }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $student->is_active ? 'bg-camel text-white' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $student->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-600">No students found for your departments.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-4">
        {{ $students->links() }}
    </div>
</x-layouts.coordinator>
