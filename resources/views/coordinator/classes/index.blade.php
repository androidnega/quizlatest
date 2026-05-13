<x-layouts.coordinator>
    <x-slot name="title">{{ __('Classes') }}</x-slot>
    <x-slot name="subtitle">{{ __('Find a class quickly, open it, or upload a roster.') }}</x-slot>

    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600">{{ __('Filters apply instantly as you change them — search matches name, section, or program.') }}</p>
        <a href="{{ route('coordinator.classes.create') }}" class="qs-btn-primary inline-flex min-h-[44px] shrink-0 items-center justify-center gap-2 px-5 text-sm font-semibold shadow-sm">
            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
            {{ __('New class') }}
        </a>
    </div>

    @php
        $f = $filters ?? [];
        $filterLabel = 'mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-500';
        $filterControl = 'qs-input w-full rounded-lg border-slate-200 py-2 text-sm leading-tight';
    @endphp

    <div class="mb-4 rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm sm:p-3.5">
        <form method="GET" action="{{ route('coordinator.classes.index') }}" id="classes-filter-form">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-12 lg:items-end lg:gap-x-2 lg:gap-y-1">
                <div class="min-w-0 sm:col-span-2 lg:col-span-3">
                    <label for="classes-q" class="{{ $filterLabel }}">{{ __('Search') }}</label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[11px] text-slate-400" aria-hidden="true"></i>
                        <input
                            id="classes-q"
                            type="search"
                            name="q"
                            value="{{ $f['q'] ?? '' }}"
                            autocomplete="off"
                            placeholder="{{ __('Class name, section, program…') }}"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50/80 py-2 pe-2.5 ps-8 text-sm leading-tight text-slate-900 shadow-inner outline-none ring-qs-primary/0 transition focus:border-qs-primary/40 focus:bg-white focus:ring-2 focus:ring-qs-primary/20"
                            @input.debounce.400ms="$el.form.requestSubmit()"
                        />
                    </div>
                </div>
                <div class="min-w-0 lg:col-span-2">
                    <label for="classes-program" class="{{ $filterLabel }}">{{ __('Program') }}</label>
                    <select id="classes-program" name="program_id" class="{{ $filterControl }}" @change="$el.form.requestSubmit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected((string) ($f['program_id'] ?? '') === (string) $program->id)>{{ $program->code }} — {{ $program->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0 lg:col-span-2">
                    <label for="classes-level" class="{{ $filterLabel }}">{{ __('Level') }}</label>
                    <select id="classes-level" name="level_id" class="{{ $filterControl }}" @change="$el.form.requestSubmit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}" @selected((string) ($f['level_id'] ?? '') === (string) $level->id)>{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0 lg:col-span-2">
                    <label for="classes-status" class="{{ $filterLabel }}">{{ __('Status') }}</label>
                    <select id="classes-status" name="status" class="{{ $filterControl }}" @change="$el.form.requestSubmit()">
                        <option value="all" @selected(($f['status'] ?? 'all') === 'all')>{{ __('All') }}</option>
                        <option value="active" @selected(($f['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(($f['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="flex min-w-0 flex-col gap-2 sm:col-span-2 sm:flex-row sm:items-end lg:col-span-3">
                    <div class="min-w-0 flex-1">
                        <label for="classes-sort" class="{{ $filterLabel }}">{{ __('Sort') }}</label>
                        <select id="classes-sort" name="sort" class="{{ $filterControl }}" @change="$el.form.requestSubmit()">
                            <option value="name" @selected(($f['sort'] ?? 'name') === 'name')>{{ __('Name') }}</option>
                            <option value="students" @selected(($f['sort'] ?? '') === 'students')>{{ __('Enrollment') }}</option>
                            <option value="recent" @selected(($f['sort'] ?? '') === 'recent')>{{ __('Recently updated') }}</option>
                        </select>
                    </div>
                    <div class="flex shrink-0 items-end gap-2">
                        <div>
                            <span class="{{ $filterLabel }} lg:sr-only">{{ __('Order') }}</span>
                            <div class="inline-flex rounded-md border border-slate-200 p-px">
                                <button type="submit" name="dir" value="asc" class="rounded-[5px] px-2.5 py-1 text-[11px] font-semibold transition {{ ($f['dir'] ?? 'asc') === 'asc' ? 'bg-qs-soft text-qs-primary shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}">{{ __('A → Z') }}</button>
                                <button type="submit" name="dir" value="desc" class="rounded-[5px] px-2.5 py-1 text-[11px] font-semibold transition {{ ($f['dir'] ?? 'asc') === 'desc' ? 'bg-qs-soft text-qs-primary shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}">{{ __('Z → A') }}</button>
                            </div>
                        </div>
                        @if ($filtersActive ?? false)
                            <a href="{{ route('coordinator.classes.index') }}" class="inline-flex min-h-[32px] items-center pb-0.5 text-xs font-semibold text-qs-primary underline-offset-2 hover:underline sm:pb-1">{{ __('Clear all') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>

    @if ($classes->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
            @if ($filtersActive ?? false)
                <p class="text-sm font-medium text-slate-800">{{ __('No classes match your filters') }}</p>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">{{ __('Try clearing search or widening program / level filters.') }}</p>
                <a href="{{ route('coordinator.classes.index') }}" class="qs-btn-primary mt-6 inline-flex min-h-[44px] items-center justify-center px-5 text-sm font-semibold">{{ __('Clear filters') }}</a>
            @else
                <p class="text-sm font-medium text-slate-800">{{ __('No classes yet') }}</p>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">{{ __('Create a class to start building rosters.') }}</p>
                <a href="{{ route('coordinator.classes.create') }}" class="qs-btn-primary mt-6 inline-flex min-h-[44px] items-center justify-center px-5 text-sm font-semibold">{{ __('Create class') }}</a>
            @endif
        </div>
    @else
        <p class="mb-4 text-xs text-slate-500">
            {{ __('Showing :from–:to of :total classes', ['from' => $classes->firstItem() ?? 0, 'to' => $classes->lastItem() ?? 0, 'total' => $classes->total()]) }}
        </p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($classes as $classroom)
                @php
                    $progCode = $classroom->program?->code ?? $classroom->program?->name ?? '—';
                    $studentCount = (int) ($classroom->students_count ?? 0);
                    $studentLabel = trans_choice('{1} :count student|[2,*] :count students', $studentCount, ['count' => number_format($studentCount)]);
                    $accentHex = $classroom->accentHex();
                    $hoverFgLight = $classroom->accentUsesLightForeground();
                    $ghostLinkHover = $hoverFgLight ? 'hover:bg-white/12' : 'hover:bg-slate-900/[0.07]';
                    $toolHover = $hoverFgLight ? 'hover:bg-white/12' : 'hover:bg-slate-900/[0.06]';
                @endphp
                <article
                    class="qs-co-class-card group relative flex min-h-0 flex-col overflow-hidden rounded-2xl border shadow-sm ring-1 ring-black/[0.03] motion-reduce:transition-none"
                    style="--qs-class-accent: {{ $accentHex }};"
                    data-hover-fg="{{ $hoverFgLight ? 'light' : 'dark' }}"
                >
                    <div class="pointer-events-none absolute inset-y-0 left-0 w-[3px] bg-[var(--qs-class-accent)] opacity-[0.38] transition-opacity duration-200 group-hover:opacity-100" aria-hidden="true"></div>
                    <div class="relative flex min-h-0 flex-1 flex-col ps-5 pe-4 pb-4 pt-4">
                        <div class="flex gap-3">
                            <div class="qs-co-class-card-icon flex size-12 shrink-0 items-center justify-center rounded-2xl shadow-sm">
                                <i class="fa-solid fa-chalkboard-user text-lg" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h2 class="qs-co-class-card-fg truncate text-[15px] font-semibold leading-snug tracking-tight text-slate-900">
                                            <a href="{{ route('coordinator.classes.show', $classroom) }}" class="text-inherit no-underline decoration-transparent transition-colors hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-qs-primary/35 focus-visible:ring-offset-2">{{ $classroom->name }}</a>
                                        </h2>
                                        @if ($classroom->section)
                                            <p class="qs-co-class-card-fg qs-co-class-card-muted mt-0.5 truncate text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ __('Section') }} {{ $classroom->section }}</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $classroom->is_active ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/90' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                        {{ $classroom->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <span class="qs-co-class-chip inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">{{ $progCode }}</span>
                                    <span class="qs-co-class-chip inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">{{ $classroom->level?->name ?? '—' }}</span>
                                    <span class="qs-co-class-chip inline-flex items-center rounded-lg bg-slate-900/[0.06] px-2 py-0.5 text-[11px] font-semibold tabular-nums text-slate-800">{{ $studentLabel }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="qs-co-class-card-divider mt-4 flex min-w-0 flex-wrap items-center gap-x-1 gap-y-1 border-t border-slate-100/90 pt-3 text-[13px] leading-snug">
                            <a href="{{ route('coordinator.classes.show', $classroom) }}" class="qs-co-class-card-fg group/o inline-flex min-w-0 max-w-full items-center gap-1.5 rounded-lg px-1.5 py-1 font-medium text-slate-600 transition-colors {{ $ghostLinkHover }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-qs-primary/25 focus-visible:ring-offset-2">
                                <span class="qs-co-class-card-muted transition-colors" aria-hidden="true">→</span>
                                <span class="truncate">{{ __('Open') }}</span>
                            </a>
                            <span class="qs-co-class-card-muted select-none text-slate-300" aria-hidden="true">·</span>
                            <a href="{{ route('coordinator.classes.students.upload', $classroom) }}" class="qs-co-class-card-fg group/u inline-flex min-w-0 max-w-full items-center gap-1.5 rounded-lg px-1.5 py-1 font-medium text-slate-600 transition-colors {{ $ghostLinkHover }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-qs-primary/25 focus-visible:ring-offset-2">
                                <i class="qs-co-class-card-muted fa-solid fa-file-arrow-up shrink-0 text-[11px] transition-colors" aria-hidden="true"></i>
                                <span class="truncate">{{ __('Upload') }}</span>
                            </a>
                        </div>

                        <div class="qs-co-class-card-divider mt-2 flex items-center justify-end gap-1 border-t border-slate-100/90 pt-2">
                            <a href="{{ route('coordinator.classes.edit', $classroom) }}" title="{{ __('Edit class') }}" class="qs-co-class-card-tool inline-flex size-9 items-center justify-center rounded-lg border border-transparent text-slate-500 transition {{ $toolHover }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-qs-primary/30 focus-visible:ring-offset-2">
                                <i class="fa-solid fa-pen text-[12px]" aria-hidden="true"></i>
                            </a>
                            <form method="POST" action="{{ route('coordinator.classes.toggle-status', $classroom) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" title="{{ $classroom->is_active ? __('Deactivate') : __('Activate') }}" class="qs-co-class-card-tool inline-flex size-9 items-center justify-center rounded-lg border border-transparent text-slate-500 transition {{ $toolHover }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-qs-primary/30 focus-visible:ring-offset-2">
                                    <i class="fa-solid {{ $classroom->is_active ? 'fa-circle-pause' : 'fa-circle-play' }} text-[12px]" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8 border-t border-slate-200 pt-6">
            {{ $classes->links() }}
        </div>
    @endif
</x-layouts.coordinator>
