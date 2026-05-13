<x-layouts.student>
    <x-slot name="title">{{ __('Class assignments') }}</x-slot>
    <x-slot name="subtitle">{{ __('Everything your class is enrolled in — open quizzes, upcoming windows, and quick links.') }}</x-slot>

    @php
        $tz = config('app.timezone');
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
    @endphp

    <div class="w-full min-w-0 space-y-5 pb-6 text-slate-950 md:space-y-6">
        @if ($activeSession !== null && $activeSession->exam)
            <div
                class="flex flex-col gap-3 rounded-[1.75rem] border px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5 sm:py-4 {{ $examSessionPaused ? 'border-amber-300/90 bg-amber-50' : 'border-emerald-300/90 bg-emerald-50' }}"
            >
                <div class="flex min-w-0 items-start gap-3">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $examSessionPaused ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}"
                        aria-hidden="true"
                    >
                        <i class="fa-solid {{ $examSessionPaused ? 'fa-pause' : 'fa-play' }}"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider {{ $examSessionPaused ? 'text-amber-900/75' : 'text-emerald-900/75' }}">
                            {{ $examSessionPaused ? __('Exam paused') : __('Exam in progress') }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ $activeSession->exam->title }}</p>
                        <p class="mt-1 text-xs leading-relaxed {{ $examSessionPaused ? 'text-amber-900/85' : 'text-emerald-900/85' }}">
                            {{ $examSessionPaused
                                ? __('Your timer is frozen until you resume in the exam window.')
                                : __('Continue where you left off — your answers are saved as you go.') }}
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('student.exam.take', $activeSession) }}"
                    class="inline-flex shrink-0 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $examSessionPaused ? 'bg-amber-800 hover:bg-amber-900 focus:ring-amber-700 focus:ring-offset-amber-50' : 'bg-[var(--qs-primary)] hover:opacity-95 focus:ring-[var(--qs-primary)] focus:ring-offset-emerald-50' }}"
                >
                    {{ $examSessionPaused ? __('Resume exam') : __('Continue exam') }}
                </a>
            </div>
        @endif

        @if ($user->class_id !== null && $assignments->isNotEmpty())
            <section class="grid grid-cols-3 gap-2 sm:gap-4" aria-label="{{ __('Assignment summary') }}">
                <article class="flex min-w-0 flex-col items-center gap-1 rounded-xl border border-slate-200 bg-slate-100 px-2 py-3 text-center sm:rounded-[1.25rem] sm:px-4 sm:py-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-slate-600 ring-1 ring-slate-200/80 sm:h-10 sm:w-10" aria-hidden="true">
                        <i class="fa-solid fa-book text-sm sm:text-base"></i>
                    </div>
                    <p class="text-lg font-semibold tabular-nums text-slate-900 sm:text-xl">{{ number_format($summaryCourses) }}</p>
                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-600 sm:text-xs">{{ __('Courses') }}</p>
                </article>
                <article class="flex min-w-0 flex-col items-center gap-1 rounded-xl border border-emerald-200/90 bg-emerald-50 px-2 py-3 text-center sm:rounded-[1.25rem] sm:px-4 sm:py-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-emerald-700 ring-1 ring-emerald-200/80 sm:h-10 sm:w-10" aria-hidden="true">
                        <i class="fa-solid fa-door-open text-sm sm:text-base"></i>
                    </div>
                    <p class="text-lg font-semibold tabular-nums text-slate-900 sm:text-xl">{{ number_format($summaryOpen) }}</p>
                    <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-900/80 sm:text-xs">{{ __('Open now') }}</p>
                </article>
                <article class="flex min-w-0 flex-col items-center gap-1 rounded-xl border border-amber-200/90 bg-amber-50 px-2 py-3 text-center sm:rounded-[1.25rem] sm:px-4 sm:py-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-amber-800 ring-1 ring-amber-200/80 sm:h-10 sm:w-10" aria-hidden="true">
                        <i class="fa-solid fa-calendar-days text-sm sm:text-base"></i>
                    </div>
                    <p class="text-lg font-semibold tabular-nums text-slate-900 sm:text-xl">{{ number_format($summaryUpcoming) }}</p>
                    <p class="text-[10px] font-medium uppercase tracking-wide text-amber-900/80 sm:text-xs">{{ __('Scheduled') }}</p>
                </article>
            </section>
        @endif

        @if ($user->class_id === null)
            <div class="rounded-[1.75rem] border border-qs-soft bg-qs-surface px-5 py-6 text-center shadow-sm sm:px-8 sm:py-10">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-qs-soft/80 text-[var(--qs-primary)]">
                    <i class="fa-solid fa-users text-2xl" aria-hidden="true"></i>
                </div>
                <p class="mt-4 text-base font-semibold text-qs-text">{{ __('No class group yet') }}</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-qs-muted">
                    {{ __('student_ui.class_group_not_assigned') }}
                </p>
            </div>
        @elseif ($assignments->isEmpty())
            <div class="rounded-[1.75rem] border border-slate-200 bg-white px-5 py-8 text-center shadow-sm sm:px-8 sm:py-10">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 ring-1 ring-amber-200/80">
                    <i class="fa-solid fa-link-slash text-2xl" aria-hidden="true"></i>
                </div>
                <p class="mt-4 text-base font-semibold text-slate-900">{{ __('No courses linked yet') }}</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600">
                    {{ __('Your coordinator has not linked any modules to your class. Once they do, each course will appear here with quizzes you can start.') }}
                </p>
            </div>
        @else
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600">
                    {{ __('Showing work for :class.', ['class' => $user->classroom?->name ?? __('your class')]) }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('student.practice.materials.index') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 sm:text-sm"
                    >
                        <i class="fa-solid fa-folder-open text-slate-500" aria-hidden="true"></i>
                        {{ __('Course materials') }}
                    </a>
                    <a
                        href="{{ route('student.results.index') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 sm:text-sm"
                    >
                        <i class="fa-solid fa-square-poll-vertical text-slate-500" aria-hidden="true"></i>
                        {{ __('Results') }}
                    </a>
                </div>
            </div>

            <ul class="space-y-4 md:space-y-5">
                @foreach ($assignments as $row)
                    @php
                        /** @var \App\Models\Course $course */
                        $course = $row['course'];
                        $open = $row['open_exams'];
                        $upcoming = $row['upcoming_exams'];
                        $nOpen = $open->count();
                        $nUp = $upcoming->count();
                    @endphp
                    <li class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-4 border-b border-slate-100 bg-gradient-to-br from-slate-50/90 to-white px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6 sm:py-5">
                            <div class="flex min-w-0 gap-3">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-800 ring-1 ring-amber-200/60" aria-hidden="true">
                                    <i class="fa-solid fa-graduation-cap text-lg"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('Course') }}</p>
                                    <h2 class="mt-0.5 text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">
                                        <span class="text-[var(--qs-primary)]">{{ $course->code }}</span>
                                        <span class="font-normal text-slate-400">·</span>
                                        <span class="font-semibold">{{ $course->title }}</span>
                                    </h2>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @if ($nOpen > 0)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-emerald-900">
                                                {{ $nOpen }} {{ __('open') }}
                                            </span>
                                        @endif
                                        @if ($nUp > 0)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-900">
                                                {{ $nUp }} {{ __('scheduled') }}
                                            </span>
                                        @endif
                                        @if ($nOpen === 0 && $nUp === 0)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                                {{ __('Nothing scheduled') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <a
                                href="{{ route('student.practice.materials.index') }}"
                                class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-xl border border-slate-200/90 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-[var(--qs-primary)]/30 hover:text-[var(--qs-primary)]"
                            >
                                <i class="fa-solid fa-file-arrow-down text-slate-500" aria-hidden="true"></i>
                                {{ __('Materials') }}
                            </a>
                        </div>

                        <div class="space-y-1 px-2 py-2 sm:px-3 sm:py-3">
                            @if ($open->isNotEmpty())
                                <div class="px-2 py-1">
                                    <p class="px-2 pb-2 text-[11px] font-bold uppercase tracking-wider text-emerald-800">{{ __('Start when you are ready') }}</p>
                                    <ul class="space-y-1">
                                        @foreach ($open as $exam)
                                            <li>
                                                <a
                                                    href="{{ route('student.exam.prepare', $exam) }}"
                                                    class="group flex items-center justify-between gap-3 rounded-xl px-3 py-3 transition hover:bg-emerald-50/80"
                                                >
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-semibold text-slate-900 group-hover:text-[var(--qs-primary)]">{{ $exam->title }}</p>
                                                        <p class="mt-0.5 text-xs font-medium text-emerald-700">{{ __('Open now') }}</p>
                                                        @if ($exam->duration_minutes)
                                                            <p class="mt-0.5 text-[11px] text-slate-500">
                                                                {{ (int) $exam->duration_minutes }} {{ __('min') }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 transition group-hover:bg-emerald-200" aria-hidden="true">
                                                        <i class="fa-solid fa-chevron-right text-xs"></i>
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($upcoming->isNotEmpty())
                                <div class="border-t border-slate-100 px-2 py-2 sm:px-2">
                                    <p class="px-2 pb-2 text-[11px] font-bold uppercase tracking-wider text-amber-900/90">{{ __('Coming up') }}</p>
                                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-100 bg-slate-50/50">
                                        @foreach ($upcoming as $exam)
                                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-3 sm:px-4">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium text-slate-900">{{ $exam->title }}</p>
                                                    @if ($exam->start_time)
                                                        <p class="mt-0.5 text-xs text-slate-600">
                                                            <i class="fa-regular fa-clock me-1 text-slate-400" aria-hidden="true"></i>
                                                            {{ __('Opens') }} {{ $exam->start_time->timezone($tz)->format('D, M j · H:i') }}
                                                        </p>
                                                    @else
                                                        <p class="mt-0.5 text-xs text-slate-500">{{ __('Scheduled — start time to be confirmed') }}</p>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-800 ring-1 ring-amber-200/80">
                                                    {{ __('Not yet open') }}
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($open->isEmpty() && $upcoming->isEmpty())
                                <div class="px-4 py-8 text-center sm:px-6">
                                    <p class="text-sm text-slate-500">{{ __('No published quizzes for this course in your schedule right now.') }}</p>
                                </div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-center text-xs text-slate-600 sm:text-sm">
            <p>
                {{ __('Assignments are managed by your coordinator under') }}
                <span class="font-semibold text-slate-800">{{ __('Course assignment') }}</span>.
                {{ __('If something looks wrong, ask your class rep to confirm your class is linked to the right modules.') }}
            </p>
        </div>
    </div>
</x-layouts.student>
