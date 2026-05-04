<x-layouts.coordinator>
    <x-slot name="title">{{ __('Coordinator Dashboard') }}</x-slot>
    <x-slot name="subtitle">
        {{ __('Students, programs, classes, courses, and examiner links for your assigned departments.') }}
        @if ($activeAcademicYear)
            <span class="mt-1 block text-xs font-normal text-qs-muted">{{ __('Class and class–course counts follow the active academic year (classes with no year set are included).') }}</span>
        @endif
    </x-slot>

    <div class="space-y-8">
        @if (count($alerts) > 0)
            <div class="space-y-3" role="region" aria-label="{{ __('Important alerts') }}">
                @foreach ($alerts as $alert)
                    <div class="rounded-xl border border-qs-danger/25 bg-qs-danger-soft px-4 py-3 text-sm text-qs-text">
                        {{ $alert['message'] }}
                    </div>
                @endforeach
            </div>
        @endif

        <section aria-labelledby="coordinator-metrics-heading">
            <h2 id="coordinator-metrics-heading" class="qs-heading mb-4 text-base">{{ __('Overview') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article class="qs-tile">
                    <span class="qs-tile-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <p class="text-sm font-medium text-qs-muted">{{ __('Total students') }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-qs-text" data-metric="total-students">{{ $studentCount }}</p>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Students enrolled in programs under your assigned departments.') }}</p>
                </article>

                <article class="qs-tile">
                    <span class="qs-tile-icon"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span>
                    <p class="text-sm font-medium text-qs-muted">{{ __('Students without class') }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-qs-text" data-metric="students-without-class">{{ $studentsWithoutClass }}</p>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Students who must be placed into a class group before exams.') }}</p>
                </article>

                <article class="qs-tile">
                    <span class="qs-tile-icon"><i class="fa-solid fa-chalkboard" aria-hidden="true"></i></span>
                    <p class="text-sm font-medium text-qs-muted">{{ __('Active classes') }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-qs-text" data-metric="active-classes">{{ $classCount }}</p>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Class groups in your departments that are active for the current view.') }}</p>
                </article>

                <article class="qs-tile">
                    <span class="qs-tile-icon"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></span>
                    <p class="text-sm font-medium text-qs-muted">{{ __('Active programs') }}</p>
                    <p class="mt-3 text-2xl font-semibold tabular-nums text-qs-text" data-metric="active-programs">
                        {{ $activeProgramCount }} <span class="text-lg font-medium text-qs-muted">{{ __('active') }} / {{ $programTotal }} {{ __('total') }}</span>
                    </p>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Active programs are available for class setup and student placement.') }}</p>
                </article>

                <article class="qs-tile">
                    <span class="qs-tile-icon"><i class="fa-solid fa-book" aria-hidden="true"></i></span>
                    <p class="text-sm font-medium text-qs-muted">{{ __('Active courses') }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-qs-text" data-metric="active-courses">{{ $activeCourseCount }}</p>
                    @if ($courseTotal !== $activeCourseCount)
                        <p class="mt-1 text-xs text-qs-muted">{{ __(':active active of :total total in catalog.', ['active' => $activeCourseCount, 'total' => $courseTotal]) }}</p>
                    @endif
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Courses available in your assigned departments.') }}</p>
                </article>

                <article class="qs-tile">
                    <span class="qs-tile-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
                    <p class="text-sm font-medium text-qs-muted">{{ __('Assigned examiners') }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-qs-text" data-metric="assigned-examiners">{{ $assignedExaminersCount }}</p>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Distinct examiners with an active assignment on a course in your departments.') }}</p>
                </article>

                <article class="qs-tile sm:col-span-2 xl:col-span-1">
                    <span class="qs-tile-icon"><i class="fa-solid fa-link" aria-hidden="true"></i></span>
                    <p class="text-sm font-medium text-qs-muted">{{ __('Courses assigned to classes') }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums text-qs-text" data-metric="courses-assigned-to-classes">{{ $coursesAssignedToClassesCount }}</p>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Class–course links for active classes in scope (same academic year rules as active classes).') }}</p>
                </article>
            </div>
        </section>

        <section class="qs-surface bg-qs-bg p-5 sm:p-6" aria-labelledby="setup-checklist-heading">
            <h2 id="setup-checklist-heading" class="qs-heading text-base">{{ __('Setup checklist') }}</h2>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Track the main coordinator workflow before exams go live.') }}</p>
            <ul class="mt-4 divide-y divide-qs-soft rounded-lg border border-qs-soft bg-qs-bg">
                @foreach ($checklist as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <span class="text-qs-text">{{ $item['label'] }}</span>
                        @if ($item['ready'])
                            <span class="inline-flex shrink-0 rounded-full border border-qs-primary/30 bg-qs-soft/80 px-2.5 py-0.5 text-xs font-semibold text-qs-primary">{{ __('Ready') }}</span>
                        @else
                            <span class="inline-flex shrink-0 rounded-full border border-qs-danger/30 bg-qs-danger-soft px-2.5 py-0.5 text-xs font-semibold text-qs-danger">{{ __('Needs attention') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="quick-actions-heading">
            <h2 id="quick-actions-heading" class="qs-heading mb-4 text-base">{{ __('Quick actions') }}</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('coordinator.students.upload') }}" class="qs-tile flex min-h-[52px] items-center justify-center text-center text-sm font-semibold text-qs-text no-underline hover:border-qs-primary/40">
                    {{ __('Upload students') }}
                </a>
                <a href="{{ route('coordinator.classes.create') }}" class="qs-tile flex min-h-[52px] items-center justify-center text-center text-sm font-semibold text-qs-text no-underline hover:border-qs-primary/40">
                    {{ __('Create class') }}
                </a>
                <a href="{{ route('coordinator.courses.index') }}" class="qs-tile flex min-h-[52px] items-center justify-center text-center text-sm font-semibold text-qs-text no-underline hover:border-qs-primary/40">
                    {{ __('Manage courses') }}
                </a>
                <a href="{{ route('coordinator.courses.assign.edit') }}" class="qs-tile flex min-h-[52px] items-center justify-center text-center text-sm font-semibold text-qs-text no-underline hover:border-qs-primary/40">
                    {{ __('Assign courses to classes') }}
                </a>
                <a href="{{ route('coordinator.courses.index') }}" class="qs-tile flex min-h-[52px] items-center justify-center text-center text-sm font-semibold text-qs-text no-underline hover:border-qs-primary/40">
                    {{ __('Assign examiners') }}
                </a>
                <a href="{{ route('coordinator.academic-reset.index') }}" class="qs-tile flex min-h-[52px] items-center justify-center text-center text-sm font-semibold text-qs-text no-underline hover:border-qs-primary/40">
                    {{ __('Academic reset') }}
                </a>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section class="qs-surface bg-qs-bg p-5 sm:p-6" aria-labelledby="recent-students-heading">
                <h2 id="recent-students-heading" class="qs-heading text-base">{{ __('Recently added students') }}</h2>
                <p class="mt-1 text-xs text-qs-muted">{{ __('Latest records in your departments (import or manual).') }}</p>
                @if ($recentStudents->isEmpty())
                    <p class="mt-4 text-sm text-qs-muted">{{ __('No recent student records.') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-qs-soft text-sm">
                        @foreach ($recentStudents as $s)
                            <li class="flex justify-between gap-3 py-2">
                                <span class="text-qs-text">{{ $s->name }}</span>
                                <span class="shrink-0 text-qs-muted">{{ $s->created_at?->timezone(config('app.timezone'))->format('M j, H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="qs-surface bg-qs-bg p-5 sm:p-6" aria-labelledby="recent-classes-heading">
                <h2 id="recent-classes-heading" class="qs-heading text-base">{{ __('Recently created classes') }}</h2>
                <p class="mt-1 text-xs text-qs-muted">{{ __('New class groups in your departments.') }}</p>
                @if ($recentClasses->isEmpty())
                    <p class="mt-4 text-sm text-qs-muted">{{ __('No classes yet.') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-qs-soft text-sm">
                        @foreach ($recentClasses as $class)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span class="text-qs-text">{{ $class->name }}@if ($class->section)<span class="text-qs-muted"> — {{ $class->section }}</span>@endif</span>
                                <span class="flex shrink-0 items-center gap-2 text-xs text-qs-muted">
                                    @unless ($class->is_active)
                                        <span class="rounded-full border border-qs-soft px-2 py-0.5 font-medium text-qs-muted">{{ __('Inactive') }}</span>
                                    @endunless
                                    {{ $class->created_at?->timezone(config('app.timezone'))->format('M j, Y') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="qs-surface bg-qs-bg p-5 sm:p-6 lg:col-span-2" aria-labelledby="recent-reset-heading">
                <h2 id="recent-reset-heading" class="qs-heading text-base">{{ __('Recent academic reset snapshots') }}</h2>
                <p class="mt-1 text-xs text-qs-muted">{{ __('Draft and completed resets you started for your departments.') }}</p>
                @if ($recentSnapshots->isEmpty())
                    <p class="mt-4 text-sm text-qs-muted">{{ __('No reset snapshots yet.') }}</p>
                @else
                    <ul class="mt-4 divide-y divide-qs-soft text-sm">
                        @foreach ($recentSnapshots as $snap)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                <span class="font-medium capitalize text-qs-text">{{ str_replace('_', ' ', $snap->reset_type) }}</span>
                                <span class="flex items-center gap-2 text-xs text-qs-muted">
                                    @if ($snap->applied_at)
                                        <span class="rounded-full border border-qs-primary/30 bg-qs-soft/80 px-2 py-0.5 font-semibold text-qs-primary">{{ __('Applied') }}</span>
                                    @else
                                        <span class="rounded-full border border-qs-soft px-2 py-0.5 font-medium">{{ __('Pending') }}</span>
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
