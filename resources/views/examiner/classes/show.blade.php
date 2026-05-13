@php
    $titleUpper = \Illuminate\Support\Str::upper(trim($classroom->name));
    $levelLabel = $classroom->level ? ($classroom->level->name ?? $classroom->level->code) : null;
    $primaryCourse = $courses->first();
    $createQuizUrl = route('examiner.exams.create');
    if ($primaryCourse) {
        $createQuizUrl .= '?course_id='.$primaryCourse->id;
    }
@endphp

<x-layouts.examiner>
    <x-slot name="title">{{ $titleUpper }}@if ($levelLabel) — {{ __('Level') }} {{ $levelLabel }}@endif</x-slot>
    <x-slot name="subtitle">{{ __('Class group workspace') }}</x-slot>

    <div class="mb-6">
        <a href="{{ route('examiner.teaching-classes.index') }}" class="text-sm text-slate-500 underline decoration-slate-300 underline-offset-4 hover:text-slate-800 hover:decoration-slate-500">
            ← {{ __('Back to class groups') }}
        </a>
    </div>

    {{-- Group actions --}}
    <section class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200/80 bg-sky-50/90 px-4 py-3.5 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.7)] sm:px-5" aria-labelledby="group-actions-label">
        <h2 id="group-actions-label" class="text-sm font-semibold text-slate-700">{{ __('Group actions') }}</h2>
        <a
            href="{{ $createQuizUrl }}"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-full bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
        >
            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
            {{ __('Create assessment') }}
        </a>
    </section>

    {{-- Courses + Quizzes row --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <article class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-2 text-sky-600">
                <i class="fa-solid fa-book text-lg" aria-hidden="true"></i>
                <h2 class="text-sm font-semibold tracking-wide text-slate-900">{{ __('Your courses') }}</h2>
            </div>
            @forelse ($courses as $course)
                @php($examinerNames = $examinersByCourseId->get($course->id, []))
                <div class="@if (! $loop->first) mt-4 border-t border-slate-100 pt-4 @endif">
                    <p class="text-base font-semibold uppercase tracking-tight text-slate-900">{{ \Illuminate\Support\Str::upper($course->title) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $course->code }} @if ($course->department) · {{ $course->department->name }} @endif</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($examinerNames as $examinerName)
                            <span class="inline-flex max-w-full rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide text-slate-700">
                                {{ \Illuminate\Support\Str::upper($examinerName) }}
                            </span>
                        @endforeach
                        @if (empty($examinerNames))
                            <span class="text-xs text-slate-400">{{ __('No examiner label on file') }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="mt-4 text-sm text-slate-500">{{ __('No overlapping courses between this class and your assignments.') }}</p>
            @endforelse
        </article>

        <article class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-2 text-sky-600">
                <i class="fa-solid fa-list text-lg" aria-hidden="true"></i>
                <h2 class="text-sm font-semibold tracking-wide text-slate-900">{{ __('Assessments') }}</h2>
            </div>
            @if ($allQuizzes->isEmpty())
                <p class="mt-4 text-sm text-slate-600">{{ __('No assessments authored by you for these courses yet.') }}</p>
                <a href="{{ $createQuizUrl }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-sky-700 underline decoration-sky-300 underline-offset-4 hover:text-sky-900">
                    {{ __('Create assessment') }}
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
                </a>
            @else
                <p class="mt-2 text-sm text-slate-600">
                    {{ trans_choice(':count assessment|:count assessments', $allQuizzes->count(), ['count' => $allQuizzes->count()]) }}
                </p>
                <ul class="mt-4 divide-y divide-slate-100 border-y border-slate-100">
                    @foreach ($allQuizzes as $exam)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <span class="min-w-0 truncate text-sm font-medium text-slate-900">{{ $exam->title }}</span>
                            <a
                                href="{{ route('examiner.quizzes.workspace', $exam) }}"
                                class="shrink-0 text-sm font-semibold text-sky-700 underline-offset-2 hover:underline"
                            >
                                {{ __('Edit') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>
    </div>

    {{-- Student index — full roster on dedicated page; CSV download is this class only. --}}
    <section id="student-index" class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 text-sky-600">
                    <i class="fa-solid fa-graduation-cap text-lg" aria-hidden="true"></i>
                    <h2 class="text-sm font-semibold tracking-wide text-slate-900">{{ __('Student index list') }}</h2>
                </div>
                <p class="mt-2 max-w-prose text-sm leading-relaxed text-slate-600">
                    {{ __('Student indices for this class group apply across assessments in this group. Open Manage students to view the roster, add a learner, or export this class’s indices. Bulk uploads and removals are coordinated through your program office.') }}
                </p>
            </div>
            <div class="shrink-0 text-center sm:text-end">
                <p class="text-4xl font-semibold tabular-nums leading-none text-sky-600">{{ $classroom->students_count }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('indices') }}</p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
            <a
                href="{{ route('examiner.teaching-classes.students.index', $classroom) }}"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
            >
                <i class="fa-solid fa-users text-xs" aria-hidden="true"></i>
                {{ __('Manage students') }}
            </a>

            <a
                href="{{ route('examiner.teaching-classes.students.roster', $classroom) }}"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100"
            >
                <i class="fa-regular fa-file-excel text-slate-500" aria-hidden="true"></i>
                {{ __('Download Excel') }}
            </a>

            <span
                class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-400"
                title="{{ __('PDF export is not available yet.') }}"
                role="note"
            >
                <i class="fa-regular fa-file-pdf" aria-hidden="true"></i>
                {{ __('Download PDF') }}
            </span>
        </div>
        <p class="mt-3 text-xs text-slate-500">
            {{ __('“Download Excel” exports a CSV of learners assigned to this class only (index number, name, phone). Use Manage students for the full table and to add someone.') }}
        </p>
    </section>
</x-layouts.examiner>
