@props([
    'assignedCourses',
    'materialUploadsEnabled' => false,
    'heading' => null,
])

<section class="overflow-hidden rounded-2xl border border-qs-soft bg-qs-card shadow-sm" aria-labelledby="examiner-courses-heading">
    <div class="border-b border-qs-soft bg-gradient-to-r from-[color-mix(in_srgb,var(--qs-primary)_8%,var(--qs-card))] to-qs-card px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="examiner-courses-heading" class="text-lg font-semibold tracking-tight text-qs-text sm:text-xl">
                    {{ $heading ?? __('Courses') }}
                </h2>
                <p class="mt-1 text-sm text-qs-muted">{{ __('Open a course to work with classes, exams, and uploads.') }}</p>
            </div>
            @if ($assignedCourses->isNotEmpty())
                <p class="text-sm tabular-nums text-qs-muted">
                    {{ trans_choice(':count course|:count courses', $assignedCourses->count(), ['count' => $assignedCourses->count()]) }}
                </p>
            @endif
        </div>
    </div>

    <div class="p-4 sm:p-6">
        @if ($assignedCourses->isEmpty())
            <div class="rounded-xl border border-dashed border-qs-soft bg-qs-bg/50 px-6 py-12 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-qs-soft text-qs-primary">
                    <i class="fa-solid fa-book-open text-lg" aria-hidden="true"></i>
                </span>
                <p class="mt-4 text-sm font-medium text-qs-text">{{ __('No courses assigned yet') }}</p>
                <p class="mx-auto mt-2 max-w-sm text-xs leading-relaxed text-qs-muted">
                    {{ __('When a coordinator assigns you to a module, it will appear here.') }}
                </p>
            </div>
        @else
            <ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" role="list">
                @foreach ($assignedCourses as $course)
                    @php
                        $classCount = $course->classrooms->count();
                        $outlineCount = (int) ($course->outlines_ready_count ?? 0);
                    @endphp
                    <li class="min-w-0">
                        <article
                            class="flex h-full flex-col rounded-2xl border border-qs-soft bg-qs-card p-5 shadow-sm ring-1 ring-black/[0.02] transition hover:-translate-y-0.5 hover:border-[color-mix(in_srgb,var(--qs-primary)_35%,var(--qs-soft))] hover:shadow-md"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="inline-flex shrink-0 rounded-lg bg-qs-soft px-2.5 py-1 font-mono text-xs font-bold tabular-nums text-qs-text ring-1 ring-qs-soft">
                                    {{ $course->code }}
                                </span>
                                @if ($outlineCount > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-qs-bg px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-qs-muted ring-1 ring-qs-soft" title="{{ __('Ready outlines') }}">
                                        <i class="fa-solid fa-file-circle-check text-qs-primary" aria-hidden="true"></i>
                                        {{ $outlineCount }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="mt-3 line-clamp-2 text-base font-semibold leading-snug text-qs-text">
                                <a href="{{ route('examiner.courses.show', $course) }}" class="hover:text-[var(--qs-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--qs-primary)] focus-visible:ring-offset-2">
                                    {{ $course->title }}
                                </a>
                            </h3>

                            <p class="mt-2 text-xs text-qs-muted">
                                {{ trans_choice(':count class group|:count class groups', $classCount, ['count' => $classCount]) }}
                            </p>

                            @if ($course->classrooms->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach ($course->classrooms->take(4) as $room)
                                        <span class="max-w-full truncate rounded-md border border-qs-soft bg-qs-bg/60 px-2 py-0.5 text-[11px] font-medium text-qs-text">
                                            {{ $room->name }}
                                        </span>
                                    @endforeach
                                    @if ($classCount > 4)
                                        <span class="rounded-md border border-transparent px-2 py-0.5 text-[11px] font-medium text-qs-muted">
                                            +{{ $classCount - 4 }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-qs-soft pt-4">
                                <a href="{{ route('examiner.courses.show', $course) }}" class="qs-btn-primary inline-flex min-h-[40px] w-full items-center justify-center gap-2 text-sm font-semibold sm:w-auto">
                                    {{ __('Open course') }}
                                    <i class="fa-solid fa-arrow-right text-xs opacity-80" aria-hidden="true"></i>
                                </a>
                                @if (! empty($materialUploadsEnabled))
                                    <a
                                        href="{{ route('examiner.courses.outline', $course) }}"
                                        class="qs-btn-secondary inline-flex min-h-[40px] items-center justify-center px-3 text-xs font-semibold"
                                        title="{{ __('Course outline') }}"
                                    >
                                        <i class="fa-solid fa-file-arrow-up me-1.5 text-[11px]" aria-hidden="true"></i>
                                        {{ __('Outline') }}
                                    </a>
                                    <a
                                        href="{{ route('examiner.courses.materials.index', $course) }}"
                                        class="qs-btn-secondary inline-flex min-h-[40px] items-center justify-center px-3 text-xs font-semibold"
                                        title="{{ __('Materials') }}"
                                    >
                                        <i class="fa-solid fa-folder-open me-1.5 text-[11px]" aria-hidden="true"></i>
                                        {{ __('Files') }}
                                    </a>
                                @endif
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
