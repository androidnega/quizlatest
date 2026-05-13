@php
    $titleUpper = \Illuminate\Support\Str::upper(trim($classroom->name));
    $levelLabel = $classroom->level ? ($classroom->level->name ?? $classroom->level->code) : null;
@endphp

<x-layouts.examiner>
    <x-slot name="title">{{ $student->name }}</x-slot>
    <x-slot name="subtitle">{{ __('Learner summary — your assigned courses for this class') }}</x-slot>

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <a href="{{ route('examiner.teaching-classes.students.index', $classroom) }}" class="text-sm font-medium text-sky-700 underline decoration-sky-300 underline-offset-4 hover:text-sky-900">
            ← {{ __('Back to students') }}
        </a>
        <a href="{{ route('examiner.teaching-classes.show', $classroom) }}#student-index" class="text-sm text-slate-500 underline decoration-slate-300 underline-offset-4 hover:text-slate-800 hover:decoration-slate-500">
            {{ __('Class group') }}
        </a>
    </div>

    <section class="mb-6 rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
        <h1 class="text-lg font-semibold tracking-tight text-slate-900">{{ $student->name }}</h1>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Index number') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $student->index_number }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Phone') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $student->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Class group') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $titleUpper }}@if ($levelLabel) · {{ __('Level') }} {{ $levelLabel }} @endif</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ $student->is_active ? __('Active') : __('Inactive') }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-xs leading-relaxed text-slate-500">
            {{ __('Contact details are shown so you can coordinate teaching. Removing learners from this roster is handled by your program office.') }}
        </p>
    </section>

    <section class="rounded-xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-center gap-2 text-sky-600">
            <i class="fa-solid fa-clipboard-list text-lg" aria-hidden="true"></i>
            <h2 class="text-sm font-semibold tracking-wide text-slate-900">{{ __('Exam sessions — your courses linked to this class') }}</h2>
        </div>
        @if ($allowedCourseIds === [])
            <p class="mt-4 text-sm text-slate-600">{{ __('No course overlap between this class and your examiner assignments — nothing to list here.') }}</p>
        @elseif ($sessions->isEmpty())
            <p class="mt-4 text-sm text-slate-600">{{ __('No exam sessions recorded yet for this learner under those courses.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-100">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5">{{ __('Course') }}</th>
                            <th class="px-4 py-2.5">{{ __('Exam') }}</th>
                            <th class="px-4 py-2.5">{{ __('Status') }}</th>
                            <th class="px-4 py-2.5">{{ __('Started') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sessions as $session)
                            <tr class="bg-white">
                                <td class="px-4 py-3 text-slate-800">
                                    @if ($session->exam?->course)
                                        <span class="font-semibold tabular-nums">{{ $session->exam->course->code }}</span>
                                        <span class="text-slate-500">· {{ $session->exam->course->title }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-800">{{ $session->exam?->title ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $session->exam_status ?? $session->status ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    @if ($session->start_time)
                                        {{ $session->start_time->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.examiner>
