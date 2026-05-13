<x-layouts.coordinator>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>
    <x-slot name="subtitle">{{ __('Your department workspace — key numbers, trends, and shortcuts.') }}</x-slot>

    <x-slot name="headingActions">
        <button
            type="button"
            class="qs-coord-header-icon"
            onclick="window.location.reload()"
            title="{{ __('Refresh') }}"
        >
            <i class="fa-solid fa-arrows-rotate text-sm" aria-hidden="true"></i>
        </button>
        <a href="#quick-actions-heading" class="qs-coord-header-icon" title="{{ __('Quick actions') }}">
            <i class="fa-solid fa-bolt text-sm" aria-hidden="true"></i>
        </a>
    </x-slot>

    <div class="space-y-6">
        <section aria-labelledby="coordinator-metrics-heading" class="qs-coordinator-overview-metrics rounded-2xl border border-slate-200/80 bg-slate-100/70 p-4 shadow-sm sm:p-5" data-metric-source="live">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 id="coordinator-metrics-heading" class="text-sm font-semibold text-slate-700">{{ __('Overview') }}</h2>
                    <p class="mt-0.5 max-w-xl text-xs leading-relaxed text-slate-500">
                        {{ __('Sparklines show how many new records were added each day over the last :days days.', ['days' => $trendDays]) }}
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-sky-200 text-sky-900" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-slate-800" data-metric="total-students">{{ $studentCount }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Total students') }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 pl-14">
                        <x-ui.sparkline :values="$metricTrends['student-adds']" tone="sky" />
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('New students') }}</p>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-200 text-rose-900" aria-hidden="true"><i class="fa-solid fa-user-slash"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-slate-800" data-metric="students-without-class">{{ $studentsWithoutClass }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Students without class') }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 pl-14">
                        <x-ui.sparkline :values="$metricTrends['students-without-class-adds']" tone="rose" />
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('New & unassigned') }}</p>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-200 text-amber-900" aria-hidden="true"><i class="fa-solid fa-chalkboard"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-slate-800" data-metric="active-classes">{{ $classCount }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Active classes') }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 pl-14">
                        <x-ui.sparkline :values="$metricTrends['class-adds']" tone="amber" />
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('New classes') }}</p>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-violet-200 text-violet-900" aria-hidden="true"><i class="fa-solid fa-diagram-project"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-slate-800" data-metric="active-programs">
                                {{ $activeProgramCount }}<span class="text-base font-semibold text-slate-400">/ {{ $programTotal }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Active programs') }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 pl-14">
                        <x-ui.sparkline :values="$metricTrends['program-adds']" tone="violet" />
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('New programs') }}</p>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-teal-200 text-teal-900" aria-hidden="true"><i class="fa-solid fa-book"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-slate-800" data-metric="active-courses">{{ $activeCourseCount }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Active courses') }}</p>
                            @if ($courseTotal !== $activeCourseCount)
                                <p class="mt-1 text-[11px] text-slate-400">{{ __(':active of :total in catalog', ['active' => $activeCourseCount, 'total' => $courseTotal]) }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="min-w-0 pl-14">
                        <x-ui.sparkline :values="$metricTrends['course-adds']" tone="teal" />
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('New courses') }}</p>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-200 text-blue-900" aria-hidden="true"><i class="fa-solid fa-user-check"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-slate-800" data-metric="assigned-examiners">{{ $assignedExaminersCount }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Assigned examiners') }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 pl-14">
                        <x-ui.sparkline :values="$metricTrends['examiner-assignments']" tone="blue" />
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('New examiner links') }}</p>
                    </div>
                </article>

                <article class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:col-span-2 xl:col-span-2">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-700" aria-hidden="true"><i class="fa-solid fa-link"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-2xl font-bold tabular-nums leading-tight text-slate-800" data-metric="courses-assigned-to-classes">{{ $coursesAssignedToClassesCount }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ __('Courses assigned to classes') }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 pl-14">
                        <x-ui.sparkline :values="$metricTrends['class-course-adds']" tone="slate" />
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('New class–course links') }}</p>
                    </div>
                </article>
            </div>
        </section>

        <section aria-labelledby="quick-actions-heading">
            <h2 id="quick-actions-heading" class="mb-3 text-sm font-semibold text-slate-600">{{ __('Quick actions') }}</h2>
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('coordinator.classes.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 shadow-sm no-underline transition hover:border-slate-300 hover:shadow">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700" aria-hidden="true"><i class="fa-solid fa-file-import text-xs"></i></span>
                    {{ __('Classes · upload roster') }}
                </a>
                <a href="{{ route('coordinator.classes.create') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 shadow-sm no-underline transition hover:border-slate-300 hover:shadow">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700" aria-hidden="true"><i class="fa-solid fa-plus text-xs"></i></span>
                    {{ __('Create class') }}
                </a>
                <a href="{{ route('coordinator.courses.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 shadow-sm no-underline transition hover:border-slate-300 hover:shadow">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800" aria-hidden="true"><i class="fa-solid fa-book text-xs"></i></span>
                    {{ __('Manage courses') }}
                </a>
                <a href="{{ route('coordinator.courses.assign.edit') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 shadow-sm no-underline transition hover:border-slate-300 hover:shadow">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-800" aria-hidden="true"><i class="fa-solid fa-link text-xs"></i></span>
                    {{ __('Assign courses to classes') }}
                </a>
                <a href="{{ route('coordinator.courses.examiners.edit') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 shadow-sm no-underline transition hover:border-slate-300 hover:shadow">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-100 text-teal-800" aria-hidden="true"><i class="fa-solid fa-user-check text-xs"></i></span>
                    {{ __('Assign examiners') }}
                </a>
                <a href="{{ route('coordinator.academic-reset.index') }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 shadow-sm no-underline transition hover:border-slate-300 hover:shadow">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-700" aria-hidden="true"><i class="fa-solid fa-arrows-rotate text-xs"></i></span>
                    {{ __('Academic reset') }}
                </a>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="recent-students-heading">
                <h2 id="recent-students-heading" class="text-sm font-semibold text-slate-800">{{ __('Recently added students') }}</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ __('Latest records in your departments (import or manual).') }}</p>
                @if ($recentStudents->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">{{ __('No recent student records.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
                        @foreach ($recentStudents as $s)
                            <li class="flex justify-between gap-3 py-1.5">
                                <span class="text-slate-800">{{ $s->name }}</span>
                                <span class="shrink-0 text-slate-500">{{ $s->created_at?->timezone(config('app.timezone'))->format('M j, H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="recent-classes-heading">
                <h2 id="recent-classes-heading" class="text-sm font-semibold text-slate-800">{{ __('Recently created classes') }}</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ __('New class groups in your departments.') }}</p>
                @if ($recentClasses->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">{{ __('No classes yet.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
                        @foreach ($recentClasses as $class)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                                <span class="text-slate-800">{{ $class->name }}@if ($class->section)<span class="text-slate-500"> — {{ $class->section }}</span>@endif</span>
                                <span class="flex shrink-0 items-center gap-2 text-xs text-slate-500">
                                    @unless ($class->is_active)
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-medium text-slate-600">{{ __('Inactive') }}</span>
                                    @endunless
                                    {{ $class->created_at?->timezone(config('app.timezone'))->format('M j, Y') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 lg:col-span-2" aria-labelledby="recent-reset-heading">
                <h2 id="recent-reset-heading" class="text-sm font-semibold text-slate-800">{{ __('Recent academic reset snapshots') }}</h2>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ __('Draft and completed resets you started for your departments.') }}</p>
                @if ($recentSnapshots->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">{{ __('No reset snapshots yet.') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
                        @foreach ($recentSnapshots as $snap)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-1.5">
                                <span class="font-medium capitalize text-slate-800">{{ str_replace('_', ' ', $snap->reset_type) }}</span>
                                <span class="flex items-center gap-2 text-xs text-slate-500">
                                    @if ($snap->applied_at)
                                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-800">{{ __('Applied') }}</span>
                                    @else
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-medium text-slate-600">{{ __('Pending') }}</span>
                                    @endif
                                    {{ $snap->created_at?->timezone(config('app.timezone'))->format('M j, Y H:i') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-layouts.coordinator>
