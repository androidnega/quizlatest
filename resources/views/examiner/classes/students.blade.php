@php
    $titleUpper = \Illuminate\Support\Str::upper(trim($classroom->name));
    $levelLabel = $classroom->level ? ($classroom->level->name ?? $classroom->level->code) : null;
    $f = $filters ?? ['q' => '', 'activity' => 'all', 'sort' => 'name_asc'];
    $hasActiveFilters = ($f['q'] ?? '') !== '' || ($f['activity'] ?? 'all') !== 'all' || ($f['sort'] ?? 'name_asc') !== 'name_asc';
    $rosterIndexUrl = route('examiner.teaching-classes.students.index', $classroom);
@endphp

<x-layouts.examiner>
    <x-slot name="title">{{ __('Students') }} — {{ $titleUpper }}@if ($levelLabel) · {{ __('Level') }} {{ $levelLabel }}@endif</x-slot>
    <x-slot name="subtitle">{{ __('Roster, search, and quick add for this class group') }}</x-slot>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('examiner.teaching-classes.show', $classroom) }}#student-index" class="group inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-slate-800">
            <span class="inline-flex size-8 items-center justify-center rounded-lg border border-slate-200/90 bg-white text-slate-400 shadow-sm transition group-hover:border-slate-300 group-hover:text-slate-600" aria-hidden="true">
                <i class="fa-solid fa-arrow-left text-[11px]"></i>
            </span>
            {{ __('Class workspace') }}
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('examiner.teaching-classes.students.roster', $classroom) }}"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-3.5 py-2 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-black/[0.03] transition hover:border-sky-200 hover:bg-sky-50/80 hover:text-sky-900"
            >
                <i class="fa-regular fa-file-excel text-slate-400" aria-hidden="true"></i>
                {{ __('Export CSV') }}
            </a>
            <a
                href="{{ route('examiner.teaching-classes.students.template', $classroom) }}"
                class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
            >
                {{ __('Blank template') }}
            </a>
        </div>
    </div>

    {{-- Hero strip --}}
    <div class="relative mb-6 overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50/95 via-white to-white px-5 py-5 shadow-sm ring-1 ring-sky-500/10 sm:px-6 sm:py-6">
        <div class="pointer-events-none absolute -right-16 -top-16 size-48 rounded-full bg-sky-400/10 blur-2xl" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-sky-700 ring-1 ring-sky-200/80">
                        <i class="fa-solid fa-fingerprint text-[10px]" aria-hidden="true"></i>
                        {{ __('Student index') }}
                    </span>
                    @if (($allowedCourseIds ?? []) === [])
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-amber-200/80" title="{{ __('No courses overlap your examiner assignments for this class.') }}">
                            {{ __('Sessions N/A') }}
                        </span>
                    @endif
                </div>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">
                    {{ __('Indices apply across quizzes for this group. Add learners one at a time; duplicates are rejected. Bulk roster changes go through your program office.') }}
                </p>
            </div>
            <div class="flex shrink-0 items-end gap-3 sm:flex-col sm:items-end">
                <div class="text-right">
                    <p class="text-4xl font-bold tabular-nums leading-none tracking-tight text-sky-700">{{ number_format((int) ($classroom->students_count ?? 0)) }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('on roster') }}</p>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- items-stretch so the aside column is as tall as the roster column — required for sticky Add learner on lg+ --}}
    <div class="grid gap-6 lg:grid-cols-12 lg:items-stretch">
        {{-- Main: filters + table --}}
        <div class="min-w-0 space-y-4 lg:col-span-8">
            <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-black/[0.02] sm:p-5">
                <form method="get" action="{{ $rosterIndexUrl }}" class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1">
                            <label for="roster-q" class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Search') }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <i class="fa-solid fa-magnifying-glass text-sm" aria-hidden="true"></i>
                                </span>
                                <input
                                    id="roster-q"
                                    type="search"
                                    name="q"
                                    value="{{ $f['q'] }}"
                                    autocomplete="off"
                                    placeholder="{{ __('Name or index number…') }}"
                                    class="block w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 shadow-inner transition focus:border-sky-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                                />
                            </div>
                        </div>
                        <div class="grid w-full gap-3 sm:w-auto sm:min-w-[11rem]">
                            <label for="roster-activity" class="mb-0 block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:mb-1.5">{{ __('Sessions') }}</label>
                            <select
                                id="roster-activity"
                                name="activity"
                                class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm font-medium text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                            >
                                <option value="all" @selected(($f['activity'] ?? 'all') === 'all')>{{ __('All learners') }}</option>
                                <option value="active" @selected(($f['activity'] ?? '') === 'active')>{{ __('With sessions') }}</option>
                                <option value="quiet" @selected(($f['activity'] ?? '') === 'quiet')>{{ __('No sessions yet') }}</option>
                            </select>
                        </div>
                        <div class="grid w-full gap-3 sm:w-auto sm:min-w-[12rem]">
                            <label for="roster-sort" class="mb-0 block text-[11px] font-semibold uppercase tracking-wide text-slate-500 sm:mb-1.5">{{ __('Sort') }}</label>
                            <select
                                id="roster-sort"
                                name="sort"
                                class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-10 text-sm font-medium text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                            >
                                <option value="name_asc" @selected(($f['sort'] ?? '') === 'name_asc')>{{ __('Name A–Z') }}</option>
                                <option value="name_desc" @selected(($f['sort'] ?? '') === 'name_desc')>{{ __('Name Z–A') }}</option>
                                <option value="index_asc" @selected(($f['sort'] ?? '') === 'index_asc')>{{ __('Index ascending') }}</option>
                                <option value="index_desc" @selected(($f['sort'] ?? '') === 'index_desc')>{{ __('Index descending') }}</option>
                                <option value="sessions_desc" @selected(($f['sort'] ?? '') === 'sessions_desc')>{{ __('Most sessions first') }}</option>
                                <option value="sessions_asc" @selected(($f['sort'] ?? '') === 'sessions_asc')>{{ __('Fewest sessions first') }}</option>
                            </select>
                        </div>
                        <div class="flex gap-2 sm:pb-0.5">
                            <button type="submit" class="inline-flex min-h-[44px] flex-1 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 sm:flex-none">
                                <i class="fa-solid fa-filter text-xs opacity-90" aria-hidden="true"></i>
                                {{ __('Apply') }}
                            </button>
                            @if ($hasActiveFilters)
                                <a href="{{ $rosterIndexUrl }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                    {{ __('Clear') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-black/[0.02]">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Enrolled learners') }}</h2>
                    @if ($students->total() > 0)
                        <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200/90">
                            {{ number_format($students->total()) }} {{ $students->total() === 1 ? __('result') : __('results') }}
                        </span>
                    @endif
                </div>

                @if ($students->isEmpty())
                    <div class="px-5 py-14 text-center">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <i class="fa-solid fa-users-line text-xl" aria-hidden="true"></i>
                        </div>
                        <p class="mt-4 text-sm font-medium text-slate-800">{{ $hasActiveFilters ? __('No learners match these filters.') : __('No learners on this roster yet.') }}</p>
                        @if ($hasActiveFilters)
                            <a href="{{ $rosterIndexUrl }}" class="mt-3 inline-flex text-sm font-semibold text-sky-700 hover:text-sky-900">{{ __('Clear filters') }}</a>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 bg-white text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Learner') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Index') }}</th>
                                    <th class="hidden whitespace-nowrap px-4 py-3 text-center sm:table-cell sm:px-5">{{ __('Sessions') }}</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-end sm:px-5"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($students as $student)
                                    @php($sessions = (int) ($student->roster_session_count ?? 0))
                                    <tr class="bg-white transition hover:bg-sky-50/40">
                                        <td class="max-w-[min(14rem,48vw)] px-4 py-3 sm:max-w-xs sm:px-5">
                                            <span class="block truncate font-semibold text-slate-900">{{ $student->name }}</span>
                                            <span class="mt-1 inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-500 sm:hidden">
                                                <span class="rounded-full bg-slate-100 px-1.5 py-0 font-bold tabular-nums text-slate-700 ring-1 ring-slate-200/80">{{ $sessions }}</span>
                                                {{ __('sessions') }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-medium text-slate-700 sm:px-5">
                                            {{ $student->index_number }}
                                        </td>
                                        <td class="hidden whitespace-nowrap px-4 py-3 text-center sm:table-cell sm:px-5">
                                            <span class="inline-flex min-w-[2rem] justify-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold tabular-nums text-slate-800 ring-1 ring-slate-200/80">
                                                {{ $sessions }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end sm:px-5">
                                            <a href="{{ route('examiner.teaching-classes.students.show', [$classroom, $student]) }}" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 hover:text-sky-900">
                                                {{ __('View') }}
                                                <i class="fa-solid fa-chevron-right text-[10px] opacity-70" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($students->hasPages())
                        <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-3">
                            {{ $students->links() }}
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Add learner: sticks within main scroll while roster column is taller (desktop). --}}
        <aside class="flex flex-col lg:col-span-4">
            <div class="rounded-2xl border border-sky-100 bg-gradient-to-b from-white to-sky-50/40 p-5 shadow-sm ring-1 ring-sky-500/10 lg:sticky lg:top-6 lg:z-30 lg:shadow-md lg:shadow-sky-900/5 lg:ring-sky-500/15">
                <div class="flex items-start gap-3">
                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-sky-600 text-white shadow-md shadow-sky-600/25">
                        <i class="fa-solid fa-user-plus text-sm" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold tracking-tight text-slate-900">{{ __('Add learner') }}</h2>
                        <p class="mt-1 text-xs leading-snug text-slate-600">{{ __('Creates the student on this class roster institution-wide.') }}</p>
                    </div>
                </div>

                <form method="post" action="{{ route('examiner.teaching-classes.students.store', $classroom) }}" class="mt-5 space-y-4">
                    @csrf
                    @if (($f['q'] ?? '') !== '')
                        <input type="hidden" name="filter_q" value="{{ $f['q'] }}" />
                    @endif
                    @if (($f['activity'] ?? 'all') !== 'all')
                        <input type="hidden" name="filter_activity" value="{{ $f['activity'] }}" />
                    @endif
                    @if (($f['sort'] ?? 'name_asc') !== 'name_asc')
                        <input type="hidden" name="filter_sort" value="{{ $f['sort'] }}" />
                    @endif
                    <div>
                        <label for="examiner-add-name" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('Full name') }}</label>
                        <input
                            id="examiner-add-name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            required
                            autocomplete="name"
                            class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                    <div>
                        <label for="examiner-add-index" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('Index number') }}</label>
                        <input
                            id="examiner-add-index"
                            name="index_number"
                            type="text"
                            value="{{ old('index_number') }}"
                            required
                            class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-mono text-sm text-slate-900 shadow-sm transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                    <div>
                        <label for="examiner-add-phone" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('Phone') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                        <input
                            id="examiner-add-phone"
                            name="phone"
                            type="text"
                            value="{{ old('phone') }}"
                            autocomplete="tel"
                            placeholder="+233…"
                            class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-mono text-sm text-slate-900 shadow-sm transition focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                    <button type="submit" class="flex w-full min-h-[46px] items-center justify-center gap-2 rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white shadow-md shadow-sky-600/20 transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                        {{ __('Add to roster') }}
                        <i class="fa-solid fa-arrow-right text-xs opacity-90" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </aside>
    </div>
</x-layouts.examiner>
