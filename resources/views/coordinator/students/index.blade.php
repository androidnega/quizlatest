<x-layouts.coordinator>
    <x-slot name="title">{{ __('Students') }}</x-slot>
    <x-slot name="subtitle">{{ __('Manage students in your assigned departments') }}</x-slot>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-200/80 bg-rose-50/80 px-3 py-2.5 text-sm text-rose-900">
            {{ $errors->first() }}
        </div>
    @endif

    @php
        $field = 'w-full rounded-md border border-slate-200 bg-white px-2.5 py-2 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-300';
        $filterField = 'w-full rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-slate-300 focus:outline-none focus:ring-1 focus:ring-slate-300';
        $label = 'mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500';
        $section = 'px-4 py-3 sm:px-5 sm:py-3.5';
    @endphp

    <div class="overflow-hidden rounded-2xl border border-slate-200/95 bg-white shadow-sm">
        {{-- Import --}}
        <div class="{{ $section }} flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Import') }}</p>
            <div class="flex min-w-0 flex-1 flex-wrap gap-2 sm:justify-end">
                <a
                    href="{{ route('coordinator.students.template') }}"
                    class="inline-flex min-h-[38px] flex-1 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 sm:flex-initial sm:min-w-[9rem]"
                >
                    <i class="fa-solid fa-download text-[11px] text-slate-400" aria-hidden="true"></i>
                    {{ __('Directory CSV template') }}
                </a>
                <a
                    href="{{ route('coordinator.students.upload') }}"
                    class="inline-flex min-h-[38px] flex-1 items-center justify-center gap-2 rounded-md bg-slate-800 px-3 text-xs font-semibold text-white sm:flex-initial sm:min-w-[9rem]"
                >
                    <i class="fa-solid fa-file-arrow-up text-[11px] opacity-90" aria-hidden="true"></i>
                    {{ __('Upload CSV') }}
                </a>
                <a
                    href="{{ route('coordinator.students.export-json') }}"
                    class="inline-flex min-h-[38px] flex-1 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 sm:flex-initial sm:min-w-[9rem]"
                >
                    <i class="fa-solid fa-code text-[11px] text-slate-400" aria-hidden="true"></i>
                    {{ __('Export JSON') }}
                </a>
                <a
                    href="{{ route('coordinator.students.import-json.form') }}"
                    class="inline-flex min-h-[38px] flex-1 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 sm:flex-initial sm:min-w-[9rem]"
                >
                    <i class="fa-solid fa-file-import text-[11px] text-slate-400" aria-hidden="true"></i>
                    {{ __('Import JSON') }}
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <div class="border-t border-slate-100 bg-slate-50/50">
            <form method="GET" action="{{ route('coordinator.students.index') }}" class="px-4 py-2.5 sm:px-5 sm:py-3">
                <p class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Filter directory') }}</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                    <div class="sm:col-span-2 lg:col-span-5">
                        <label for="student_search" class="{{ $label }}">{{ __('Search') }}</label>
                        <input
                            id="student_search"
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="{{ __('Name or index number') }}"
                            class="{{ $filterField }}"
                        />
                    </div>
                    <div class="lg:col-span-3">
                        <label for="filter_program" class="{{ $label }}">{{ __('Program') }}</label>
                        <select id="filter_program" name="program_id" class="{{ $filterField }}">
                            <option value="">{{ __('All programs') }}</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') == $program->id)>{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="filter_level" class="{{ $label }}">{{ __('Level') }}</label>
                        <select id="filter_level" name="level_id" class="{{ $filterField }}">
                            <option value="">{{ __('All levels') }}</option>
                            @foreach ($levels as $level)
                                <option value="{{ $level->id }}" @selected(($filters['level_id'] ?? '') == $level->id)>{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-2 lg:flex lg:items-end">
                        <button type="submit" class="inline-flex min-h-[34px] w-full items-center justify-center rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            {{ __('Apply filters') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('coordinator.students.bulk-status') }}">
            @csrf
            {{-- Bulk row selection --}}
            <div class="border-t border-slate-100 {{ $section }} flex flex-col gap-2 sm:flex-row sm:items-center">
                <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Selection') }}</span>
                <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
                    <select name="action" class="{{ $field }} min-h-[38px] py-2 sm:max-w-xs sm:flex-initial" required>
                        <option value="">{{ __('Bulk action…') }}</option>
                        <option value="activate">{{ __('Activate selected') }}</option>
                        <option value="deactivate">{{ __('Deactivate selected') }}</option>
                        <option value="delete">{{ __('Delete selected (safe only)') }}</option>
                    </select>
                    <button type="submit" class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                        {{ __('Run action') }}
                    </button>
                    <button
                        type="submit"
                        class="inline-flex min-h-[38px] shrink-0 items-center justify-center rounded-md border border-rose-200 bg-rose-50 px-4 text-xs font-semibold text-rose-700 hover:bg-rose-100 sm:w-auto"
                        onclick="this.form.querySelector('select[name=action]').value='delete'; return confirm('{{ __('Delete selected students that are safe to remove? Assigned or active-data students will be skipped.') }}');"
                    >
                        {{ __('Bulk delete (safe)') }}
                    </button>
                </div>
            </div>

            {{-- Table --}}
            <div class="border-t border-slate-100">
                <div class="-mx-px overflow-x-auto">
                    <table class="min-w-[720px] w-full divide-y divide-slate-100 text-sm text-slate-800">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="w-11 whitespace-nowrap px-4 py-2.5">
                                    <input
                                        type="checkbox"
                                        onclick="document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked)"
                                        class="rounded border-slate-300 text-slate-700 focus:ring-slate-400"
                                        aria-label="{{ __('Select all') }}"
                                    />
                                </th>
                                <th scope="col" class="whitespace-nowrap px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Name') }}</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Index') }}</th>
                                <th scope="col" class="min-w-[8rem] px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Program') }}</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Level') }}</th>
                                <th scope="col" class="min-w-[7rem] px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Class') }}</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($students as $student)
                                <tr class="transition-colors hover:bg-slate-50/90">
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="student-checkbox rounded border-slate-300 text-slate-700 focus:ring-slate-400" />
                                    </td>
                                    <td class="max-w-[14rem] truncate px-4 py-2.5 font-medium text-slate-900">{{ $student->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <span class="inline-flex rounded-md bg-slate-900/[0.06] px-2 py-0.5 font-semibold tabular-nums text-slate-800 ring-1 ring-slate-200/90">{{ $student->index_number ?: '—' }}</span>
                                    </td>
                                    <td class="max-w-[11rem] truncate px-4 py-2.5 text-slate-600">{{ $student->program?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $student->level?->name ?? '—' }}</td>
                                    <td class="max-w-[10rem] truncate px-4 py-2.5 text-slate-600">{{ $student->classroom?->name ?? __('Unassigned') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium {{ $student->is_active ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100/90' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200/90' }}">
                                            {{ $student->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                        <a
                                            href="{{ route('coordinator.students.edit', $student) }}"
                                            class="inline-flex items-center justify-end gap-1 text-xs font-semibold text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline"
                                        >
                                            {{ __('Manage') }}
                                            <i class="fa-solid fa-chevron-right text-[9px] opacity-50" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center">
                                        <p class="text-sm font-medium text-slate-700">{{ __('No students match your filters') }}</p>
                                        <p class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-slate-500">{{ __('Adjust filters above, import here for multi-program CSVs, or manage class rosters under Classes.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        {{-- Pagination --}}
        <div class="border-t border-slate-100 {{ $section }} flex justify-center sm:justify-between">
            {{ $students->links() }}
        </div>
    </div>
</x-layouts.coordinator>
