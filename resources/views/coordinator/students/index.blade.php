<x-layouts.coordinator>
    <x-slot name="title">Students</x-slot>
    <x-slot name="subtitle">Manage students in your assigned departments</x-slot>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-4 py-3 text-sm text-qs-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('coordinator.students.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, index number or email" class="w-64 rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40" />

            <select name="program_id" class="rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                <option value="">All Programs</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->name }}</option>
                @endforeach
            </select>

            <select name="level_id" class="rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                <option value="">All Levels</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}" @selected(($filters['level_id'] ?? '') == $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="qs-btn-primary text-sm">Filter</button>
        </form>

        <div class="flex items-center gap-2">
            <a href="{{ route('coordinator.students.template') }}" class="rounded-lg bg-qs-card px-4 py-2 text-sm text-qs-muted hover:bg-qs-soft">Download CSV Template</a>
            <a href="{{ route('coordinator.students.upload') }}" class="qs-btn-primary text-sm">Upload CSV</a>
        </div>
    </div>

    <form method="POST" action="{{ route('coordinator.students.bulk-status') }}">
        @csrf
        <div class="mb-3 flex items-center gap-2">
            <select name="action" class="rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40" required>
                <option value="">Bulk Actions</option>
                <option value="activate">Activate Selected</option>
                <option value="deactivate">Deactivate Selected</option>
            </select>
            <button type="submit" class="rounded-lg bg-qs-card px-4 py-2 text-sm text-qs-muted hover:bg-qs-soft">Apply</button>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <table class="min-w-full divide-y divide-beige">
                <thead class="bg-qs-soft/30">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">
                            <input type="checkbox" onclick="document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked)" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Index Number</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Program</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Level</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Class</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-qs-text">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-beige">
                    @forelse ($students as $student)
                        <tr class="hover:bg-qs-card">
                            <td class="px-4 py-3 text-sm">
                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="student-checkbox rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40">
                            </td>
                            <td class="px-4 py-3 text-sm text-qs-text">{{ $student->name }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $student->index_number }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $student->program?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $student->level?->name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $student->classroom?->name ?? 'Unassigned' }}</td>
                            <td class="px-4 py-3 text-sm text-qs-muted">{{ $student->email }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $student->is_active ? 'bg-qs-accent/20 text-qs-text border border-qs-accent/30' : 'bg-qs-card text-qs-muted' }}">
                                    {{ $student->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('coordinator.students.assign-class.edit', $student) }}" class="rounded-lg bg-qs-card px-3 py-1.5 text-xs font-semibold text-qs-muted hover:bg-qs-soft">
                                    {{ $student->class_id ? 'Edit Class' : 'Assign Class' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-qs-muted">No students found for your departments.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-qs-text">Bulk Assign Class</h3>
        <p class="mt-1 text-sm text-qs-muted">Filter by program and level, then assign one class to all matching students.</p>
        <form method="POST" action="{{ route('coordinator.students.bulk-assign-class') }}" class="mt-4 grid gap-3 sm:grid-cols-4">
            @csrf
            <div>
                <label for="bulk_program_id" class="block text-xs font-medium text-qs-muted">Program</label>
                <select id="bulk_program_id" name="program_id" required class="mt-1 block w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                    <option value="">Select program</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}">{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bulk_level_id" class="block text-xs font-medium text-qs-muted">Level</label>
                <select id="bulk_level_id" name="level_id" required class="mt-1 block w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                    <option value="">Select level</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bulk_class_id" class="block text-xs font-medium text-qs-muted">Class</label>
                <select id="bulk_class_id" name="class_id" required class="mt-1 block w-full rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm focus:border-qs-accent focus:ring-qs-accent/40">
                    <option value="">Select class</option>
                    @foreach ($classes as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }} ({{ $classroom->program?->code ?? $classroom->program?->name }} - {{ $classroom->level?->name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full qs-btn-primary text-sm">Assign Class</button>
            </div>
        </form>
    </div>

    <div class="mt-4">
        {{ $students->links() }}
    </div>
</x-layouts.coordinator>
