<x-layouts.coordinator>
    <x-slot name="title">Students</x-slot>
    <x-slot name="subtitle">Manage students in your assigned departments</x-slot>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-qs-danger/35 bg-qs-danger-soft px-4 py-3 text-sm text-qs-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-5 flex flex-col gap-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <a href="{{ route('coordinator.students.template') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold sm:order-2 sm:ms-auto">
                {{ __('Download CSV template') }}
            </a>
            <a href="{{ route('coordinator.students.upload') }}" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold sm:order-3">
                {{ __('Upload CSV') }}
            </a>
        </div>

        <form method="GET" action="{{ route('coordinator.students.index') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="w-full sm:max-w-md sm:flex-1">
                <label for="student_search" class="sr-only">{{ __('Search') }}</label>
                <input id="student_search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search name, index number or email') }}" class="qs-input mt-0 w-full py-2.5" />
            </div>

            <div class="grid w-full gap-3 sm:w-auto sm:min-w-[10rem] sm:max-w-xs">
                <label for="filter_program" class="block text-xs font-medium text-qs-muted">{{ __('Program') }}</label>
                <select id="filter_program" name="program_id" class="qs-input mt-0 py-2.5">
                    <option value="">{{ __('All programs') }}</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid w-full gap-3 sm:w-auto sm:min-w-[10rem] sm:max-w-xs">
                <label for="filter_level" class="block text-xs font-medium text-qs-muted">{{ __('Level') }}</label>
                <select id="filter_level" name="level_id" class="qs-input mt-0 py-2.5">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}" @selected(($filters['level_id'] ?? '') == $level->id)>{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="qs-btn-primary min-h-[44px] w-full px-4 text-sm sm:w-auto">{{ __('Filter') }}</button>
        </form>
    </div>

    <form method="POST" action="{{ route('coordinator.students.bulk-status') }}">
        @csrf
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <select name="action" class="qs-input mt-0 min-h-[44px] w-full py-2.5 sm:max-w-xs" required>
                <option value="">{{ __('Bulk actions') }}</option>
                <option value="activate">{{ __('Activate selected') }}</option>
                <option value="deactivate">{{ __('Deactivate selected') }}</option>
            </select>
            <button type="submit" class="qs-btn-secondary min-h-[44px] w-full px-4 text-sm font-semibold sm:w-auto">{{ __('Apply') }}</button>
        </div>

        <div class="qs-table-wrap shadow-sm">
            <table class="qs-table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" onclick="document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked)" class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" aria-label="{{ __('Select all') }}">
                        </th>
                        <th class="text-left">{{ __('Name') }}</th>
                        <th class="text-left">{{ __('Index number') }}</th>
                        <th class="text-left">{{ __('Program') }}</th>
                        <th class="text-left">{{ __('Level') }}</th>
                        <th class="text-left">{{ __('Class') }}</th>
                        <th class="text-left">{{ __('Email') }}</th>
                        <th class="text-left">{{ __('Status') }}</th>
                        <th class="text-left">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>
                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="student-checkbox rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40">
                            </td>
                            <td class="font-medium">{{ $student->name }}</td>
                            <td class="text-qs-muted">{{ $student->index_number }}</td>
                            <td class="text-qs-muted">{{ $student->program?->name ?? 'N/A' }}</td>
                            <td class="text-qs-muted">{{ $student->level?->name ?? 'N/A' }}</td>
                            <td class="text-qs-muted">{{ $student->classroom?->name ?? __('Unassigned') }}</td>
                            <td class="text-qs-muted">{{ $student->email }}</td>
                            <td>
                                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $student->is_active ? 'border-qs-accent/30 bg-qs-accent/20 text-qs-text' : 'bg-qs-card text-qs-muted' }}">
                                    {{ $student->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('coordinator.students.assign-class.edit', $student) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center whitespace-nowrap px-3 py-2 text-xs font-semibold">
                                    {{ $student->class_id ? __('Edit class') : __('Assign class') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-sm text-qs-muted">
                                <p class="font-medium text-qs-text">{{ __('No students match your filters') }}</p>
                                <p class="mt-1 text-xs">{{ __('Try adjusting search or filters, or upload a CSV to add students.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-6 rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
        <h3 class="text-base font-semibold text-qs-text">{{ __('Bulk assign class') }}</h3>
        <p class="mt-1 text-sm text-qs-muted">{{ __('Filter by program and level, then assign one class to all matching students.') }}</p>
        <form method="POST" action="{{ route('coordinator.students.bulk-assign-class') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="bulk_program_id" class="block text-xs font-medium text-qs-muted">{{ __('Program') }}</label>
                <select id="bulk_program_id" name="program_id" required class="qs-input mt-1 py-2.5">
                    <option value="">{{ __('Select program') }}</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id }}">{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="bulk_level_id" class="block text-xs font-medium text-qs-muted">{{ __('Level') }}</label>
                <select id="bulk_level_id" name="level_id" required class="qs-input mt-1 py-2.5">
                    <option value="">{{ __('Select level') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="bulk_class_id" class="block text-xs font-medium text-qs-muted">{{ __('Class') }}</label>
                <select id="bulk_class_id" name="class_id" required class="qs-input mt-1 py-2.5">
                    <option value="">{{ __('Select class') }}</option>
                    @foreach ($classes as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }} ({{ $classroom->program?->code ?? $classroom->program?->name }} - {{ $classroom->level?->name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end sm:col-span-2 lg:col-span-1">
                <button type="submit" class="qs-btn-primary min-h-[44px] w-full text-sm">{{ __('Assign class') }}</button>
            </div>
        </form>
    </div>

    <div class="mt-4">
        {{ $students->links() }}
    </div>
</x-layouts.coordinator>
