<x-layouts.coordinator>
    <x-slot name="title">{{ $classroom->name }}</x-slot>
    <x-slot name="subtitle">{{ __('Roster & uploads for this teaching group.') }}</x-slot>

    <nav class="mb-4 min-w-0" aria-label="{{ __('Class actions') }}">
        <div class="flex min-w-0 flex-col gap-1 rounded-2xl border border-slate-200/90 bg-slate-50/90 p-1 sm:flex-row sm:items-center sm:gap-0 sm:overflow-hidden sm:p-0">
            <a
                href="{{ route('coordinator.classes.index', request()->only(['q', 'program_id', 'level_id', 'status', 'sort', 'dir'])) }}"
                class="flex min-h-[44px] min-w-0 flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-900 sm:min-h-0 sm:flex-none sm:justify-start sm:rounded-none sm:border-e sm:border-slate-200/80 sm:px-4 sm:py-3"
            >
                <i class="fa-solid fa-chevron-left text-[10px] text-slate-400" aria-hidden="true"></i>
                <span class="truncate">{{ __('Classes') }}</span>
            </a>
            <a
                href="{{ route('coordinator.classes.students.upload', $classroom) }}"
                class="flex min-h-[44px] min-w-0 flex-1 items-center justify-center gap-2 rounded-xl bg-white/70 px-3 py-2.5 text-sm font-semibold text-qs-primary ring-1 ring-qs-primary/15 transition-colors hover:bg-qs-soft/80 hover:ring-qs-primary/25 sm:min-h-0 sm:flex-none sm:justify-start sm:rounded-none sm:border-e sm:border-slate-200/80 sm:ring-0 sm:ring-offset-0 sm:hover:bg-qs-soft/60"
            >
                <i class="fa-solid fa-file-arrow-up text-[12px] text-qs-primary/90" aria-hidden="true"></i>
                <span class="truncate">{{ __('Upload roster') }}</span>
            </a>
            <a
                href="{{ route('coordinator.classes.edit', $classroom) }}"
                class="flex min-h-[44px] min-w-0 flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-900 sm:min-h-0 sm:flex-none sm:justify-start sm:rounded-none sm:border-slate-200/80 sm:border-e sm:px-4 sm:py-3"
            >
                <i class="fa-solid fa-pen text-[11px] text-slate-400" aria-hidden="true"></i>
                <span class="truncate">{{ __('Edit') }}</span>
            </a>
            <a
                href="{{ route('coordinator.students.index') }}"
                class="flex min-h-[44px] min-w-0 flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-900 sm:min-h-0 sm:flex-1 sm:justify-start sm:rounded-none sm:px-4 sm:py-3"
            >
                <span class="truncate">{{ __('Student directory') }}</span>
                <i class="fa-solid fa-arrow-up-right-from-square shrink-0 text-[10px] text-slate-400" aria-hidden="true"></i>
            </a>
        </div>
    </nav>

    <section class="qs-co-class-show-accent mb-5 rounded-xl border shadow-sm ring-1 ring-black/[0.03]" style="--qs-class-accent: {{ $classroom->accentHex() }};" aria-labelledby="class-summary-heading">
        <div class="px-4 py-3 sm:px-5 sm:py-3">
            <h2 id="class-summary-heading" class="text-lg font-bold leading-snug tracking-tight text-slate-900 sm:text-xl">{{ $classroom->name }}</h2>
            @if ($classroom->section)
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Section') }} {{ $classroom->section }}</p>
            @endif
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                <span class="inline-flex max-w-full truncate rounded-md bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200/90">{{ $classroom->program?->name }}</span>
                <span class="inline-flex rounded-md bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200/90">{{ __('Level') }} {{ $classroom->level?->name ?? '—' }}</span>
                <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $classroom->is_active ? 'bg-emerald-50 text-emerald-900 ring-emerald-200/90' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                    {{ $classroom->is_active ? __('Active') : __('Inactive') }}
                </span>
                <span class="inline-flex rounded-md bg-slate-900/[0.06] px-2 py-0.5 text-[11px] font-bold tabular-nums text-slate-800">{{ number_format((int) ($classroom->students_count ?? 0)) }} {{ __('enrolled') }}</span>
            </div>
            <p class="mt-2 max-w-3xl text-[11px] leading-snug text-slate-500">
                {{ __('CSV roster: optional index, name, phone — program and level stay tied to this class.') }}
            </p>
        </div>
    </section>

    <section class="mb-5 overflow-hidden rounded-xl border border-slate-200/95 bg-white shadow-sm" aria-labelledby="class-examiners-heading">
        <div class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/60 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5">
            <div>
                <h2 id="class-examiners-heading" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Examiners for this class') }}</h2>
                <p class="mt-1 max-w-3xl text-[11px] leading-snug text-slate-500">
                    {{ __('Listed from examiners assigned to courses that are linked to this class.') }}
                </p>
            </div>
            <a
                href="{{ route('coordinator.courses.examiners.edit') }}"
                class="inline-flex min-h-[36px] shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-[11px] font-semibold text-slate-800 shadow-sm transition-colors hover:bg-slate-50 sm:mt-0"
            >
                {{ __('Manage examiner assignments') }}
                <i class="fa-solid fa-arrow-up-right-from-square ms-2 text-[10px] text-slate-400" aria-hidden="true"></i>
            </a>
        </div>
        @if ($classroom->courses->isEmpty())
            <div class="px-4 py-6 sm:px-5">
                <p class="text-xs text-slate-600">{{ __('Link courses to this class first — then assigned examiners appear here.') }}</p>
                <a href="{{ route('coordinator.courses.assign.edit') }}" class="mt-3 inline-flex text-xs font-semibold text-qs-primary underline-offset-2 hover:underline">{{ __('Assign courses to classes') }}</a>
            </div>
        @elseif ($classExaminers->isEmpty())
            <div class="px-4 py-6 sm:px-5">
                <p class="text-xs text-slate-600">{{ __('No active examiner assignments for courses linked to this class yet.') }}</p>
            </div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($classExaminers as $row)
                    <li class="px-4 py-4 sm:px-5">
                        <p class="text-sm font-semibold text-slate-900">{{ $row['examiner']->name }}</p>
                        @if ($row['examiner']->email)
                            <p class="mt-0.5 text-xs text-slate-500">{{ $row['examiner']->email }}</p>
                        @endif
                        <ul class="mt-2 space-y-1 border-s border-slate-200 ps-3">
                            @foreach ($row['courses'] as $course)
                                <li class="text-xs text-slate-700">
                                    <span class="font-semibold tabular-nums text-slate-800">{{ $course->code }}</span>
                                    @if ($course->title)
                                        <span class="mx-1 text-slate-300" aria-hidden="true">·</span>
                                        <span>{{ $course->title }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="class-roster-heading">
        <div class="flex flex-col gap-2 border-b border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <h2 id="class-roster-heading" class="text-sm font-semibold text-slate-900">{{ __('Students') }}</h2>
            @if ($students->isNotEmpty())
                <span class="text-xs text-slate-500">{{ __('Newest first') }}</span>
            @endif
        </div>

        @if ($students->isEmpty())
            <div class="px-4 py-10 text-center sm:px-6">
                <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                    <i class="fa-solid fa-users text-lg" aria-hidden="true"></i>
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-900">{{ __('No students in this class yet') }}</p>
                <p class="mx-auto mt-1.5 max-w-md text-xs leading-relaxed text-slate-500">{{ __('Upload a roster CSV — records stay linked here and in the directory.') }}</p>
                <a href="{{ route('coordinator.classes.students.upload', $classroom) }}" class="qs-btn-primary mt-5 inline-flex min-h-[44px] items-center justify-center px-5 text-sm font-semibold">{{ __('Upload roster') }}</a>
            </div>
        @else
            <div class="divide-y divide-slate-100 sm:hidden">
                @foreach ($students as $student)
                    <div class="flex flex-col gap-2 px-4 py-4">
                        <div class="flex flex-col gap-1">
                            <p class="font-semibold text-slate-900">{{ $student->name }}</p>
                            <p class="text-xs tabular-nums text-slate-600">{{ $student->index_number ?: __('No index') }}</p>
                            <p class="text-[11px] text-slate-400">{{ $student->created_at?->timezone(config('app.timezone'))->format('M j, Y') }}</p>
                        </div>
                        <a
                            href="{{ route('coordinator.students.edit', $student) }}"
                            class="inline-flex min-h-[40px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            {{ __('Edit student') }}
                        </a>
                    </div>
                @endforeach
            </div>
            <div class="hidden overflow-x-auto sm:block">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50/90">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Index') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Added') }}</th>
                            <th class="whitespace-nowrap px-6 py-3 text-end text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($students as $student)
                            <tr class="transition-colors hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-6 py-3 font-medium text-slate-900">{{ $student->name }}</td>
                                <td class="whitespace-nowrap px-6 py-3 tabular-nums text-slate-600">{{ $student->index_number ?: '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-xs text-slate-500">{{ $student->created_at?->timezone(config('app.timezone'))->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-end text-xs font-semibold">
                                    <a href="{{ route('coordinator.students.edit', $student) }}" class="text-slate-600 underline-offset-2 hover:text-slate-900 hover:underline">{{ __('Edit') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                {{ $students->links() }}
            </div>
        @endif
    </section>
</x-layouts.coordinator>
