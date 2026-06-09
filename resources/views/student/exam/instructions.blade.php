@extends('layouts.exam-entry')

@section('title', __('Dos and don’ts').' — '.config('app.name', 'QuizSnap'))

@php
    $quiz = $quiz ?? null;
    $isAssignment = (bool) ($isAssignment ?? ($quiz?->isAssignment() ?? false));
    $course = $quiz?->course ?? null;
    $courseLine = $course
        ? trim((string) $course->code).' — '.mb_strtoupper((string) ($course->title ?? ''))
        : '';
    $assessmentTypeRaw = (string) ($quiz?->assessment_type ?? 'exam');
    $typeLabel = match ($assessmentTypeRaw) {
        'quiz' => __('Quiz'),
        'exam' => __('Exam'),
        'mid' => __('Mid-semester'),
        'assignment' => __('Assignment'),
        default => __('Assessment'),
    };

    $appTz = config('app.timezone');
    $startsAt = $quiz?->start_time?->copy()->timezone($appTz);
    $endsAt = $quiz?->end_time?->copy()->timezone($appTz);
    $dueAt = $quiz?->due_at?->copy()->timezone($appTz);
    $durationMinutes = (int) ($quiz?->duration_minutes ?? 0);

    $primaryLabel = match ($assessmentTypeRaw) {
        'quiz' => __('Continue to start quiz'),
        'exam' => __('Continue to start exam'),
        'mid' => __('Continue to start mid-semester'),
        'assignment' => __('Continue to assignment'),
        default => __('Continue'),
    };

    $continueHref = $quiz ? route('student.exam.prepare', $quiz) : route('dashboard');
@endphp

@section('content')
    <div class="qs-std-instructions w-full" style="max-width: 1240px; margin-left: auto; margin-right: auto;">
        {{-- Header band --}}
        <header class="rounded-2xl border border-slate-200/90 bg-white px-5 py-5 shadow-sm sm:px-7 sm:py-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700">
                        {{ __('Read carefully before you begin') }}
                    </p>
                    <h1 class="mt-1 text-lg font-semibold leading-tight text-slate-900 sm:text-xl md:text-2xl">
                        {{ $isAssignment ? __('Assignment dos and don’ts') : __('Exam dos and don’ts') }}
                    </h1>
                    @if ($quiz)
                        <p class="mt-1 text-sm text-slate-600">
                            <span class="font-medium text-slate-900">{{ $quiz->title }}</span>
                            @if ($courseLine)
                                <span class="text-slate-400"> · </span><span>{{ $courseLine }}</span>
                            @endif
                        </p>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 font-semibold uppercase tracking-wide text-sky-900">{{ $typeLabel }}</span>
                        @if ($durationMinutes > 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 font-medium text-slate-700">
                                <i class="fa-solid fa-clock text-[10px]" aria-hidden="true"></i>
                                {{ $durationMinutes }} {{ __('min') }}
                            </span>
                        @endif
                        @if ($startsAt && $startsAt->isFuture())
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 font-medium text-amber-900">
                                <i class="fa-solid fa-hourglass-start text-[10px]" aria-hidden="true"></i>
                                {{ __('Opens') }} {{ $startsAt->format('M j, g:i A') }}
                            </span>
                        @elseif ($endsAt)
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 font-medium text-slate-700">
                                <i class="fa-solid fa-flag-checkered text-[10px]" aria-hidden="true"></i>
                                {{ __('Closes') }} {{ $endsAt->format('M j, g:i A') }}
                            </span>
                        @endif
                        @if ($dueAt && $isAssignment)
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 font-medium text-slate-700">
                                <i class="fa-solid fa-calendar text-[10px]" aria-hidden="true"></i>
                                {{ __('Due') }} {{ $dueAt->format('M j, g:i A') }}
                            </span>
                        @endif
                    </div>
                </div>
                <a
                    href="{{ $continueHref }}"
                    class="hidden shrink-0 items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-sky-700 sm:inline-flex"
                >
                    {{ $primaryLabel }}
                    <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                </a>
            </div>

            {{-- Step progress --}}
            <div class="mt-5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-medium text-slate-500">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-1 font-semibold text-sky-900">
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-sky-600 text-[10px] font-bold text-white">1</span>
                    {{ __('Rules') }}
                </span>
                <span class="text-slate-300">→</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-slate-500">
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-300 bg-white text-[10px] font-bold text-slate-500">2</span>
                    {{ __('Permissions & checks') }}
                </span>
                <span class="text-slate-300">→</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-slate-500">
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-slate-300 bg-white text-[10px] font-bold text-slate-500">3</span>
                    {{ __('Begin') }}
                </span>
            </div>
        </header>

        {{-- Rule cards --}}
        @if ($isAssignment)
            <section class="mt-5 grid gap-4 sm:mt-6" aria-label="{{ __('Assignment rules') }}">
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-emerald-800">
                        <i class="fa-solid fa-circle-check text-emerald-600" aria-hidden="true"></i>
                        {{ __('Follow these rules') }}
                    </h2>
                    <ul class="mt-4 grid gap-3 text-sm leading-relaxed text-slate-700 md:grid-cols-2">
                        <li class="flex gap-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true">
                                <i class="fa-solid fa-user-check text-xs"></i>
                            </span>
                            <span>{{ __('Complete this assignment yourself, using only sources and collaboration rules your course allows.') }}</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true">
                                <i class="fa-solid fa-keyboard text-xs"></i>
                            </span>
                            <span>{{ __('Typed responses must be entered in this page. Copy and paste is blocked in answer fields to support academic integrity.') }}</span>
                        </li>
                        <li class="flex gap-3 md:col-span-2">
                            <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true">
                                <i class="fa-solid fa-video-slash text-xs"></i>
                            </span>
                            <span>{{ __('This coursework does not use live camera or audio invigilation unless your school explicitly enables an exception.') }}</span>
                        </li>
                    </ul>
                </div>
            </section>
        @else
            <section class="mt-5 grid gap-4 sm:mt-6 lg:grid-cols-2" aria-label="{{ __('Exam rules') }}">
                {{-- Do --}}
                <div class="rounded-2xl border border-emerald-200/70 bg-emerald-50/40 p-5 shadow-sm sm:p-6">
                    <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-emerald-800">
                        <i class="fa-solid fa-circle-check text-emerald-600" aria-hidden="true"></i>
                        {{ __('Do') }}
                    </h2>
                    <ul class="mt-4 space-y-3 text-sm leading-relaxed text-slate-700">
                        <li class="flex gap-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 ring-1 ring-emerald-200" aria-hidden="true">
                                <i class="fa-solid fa-user-check text-xs"></i>
                            </span>
                            <span>{{ __('Complete this exam honestly, on your own, without unauthorised help or materials, unless your institution explicitly allows otherwise.') }}</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 ring-1 ring-emerald-200" aria-hidden="true">
                                <i class="fa-solid fa-clipboard-check text-xs"></i>
                            </span>
                            <span>{{ __('Follow invigilator or institution instructions (for example fullscreen, staying visible on camera, and not switching away from the exam without permission).') }}</span>
                        </li>
                    </ul>
                </div>

                {{-- Don't --}}
                <div class="rounded-2xl border border-rose-200/70 bg-rose-50/40 p-5 shadow-sm sm:p-6">
                    <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-rose-800">
                        <i class="fa-solid fa-circle-xmark text-rose-600" aria-hidden="true"></i>
                        {{ __('Don’t') }}
                    </h2>
                    <ul class="mt-4 space-y-3 text-sm leading-relaxed text-slate-700">
                        <li class="flex gap-3">
                            <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-rose-700 ring-1 ring-rose-200" aria-hidden="true">
                                <i class="fa-solid fa-camera text-xs"></i>
                            </span>
                            <span>{{ __('Do not copy, share, or capture exam content. Suspicious behaviour may be logged for review.') }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            {{-- Auto-submit notice --}}
            <section class="mt-5 rounded-2xl border border-amber-200/80 bg-amber-50 p-5 shadow-sm sm:mt-6 sm:p-6" aria-label="{{ __('Auto-submit conditions') }}">
                <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-amber-900">
                    <i class="fa-solid fa-bolt text-amber-600" aria-hidden="true"></i>
                    {{ __('When your work may be submitted automatically') }}
                </h2>
                <ul class="mt-4 grid gap-3 text-sm leading-relaxed text-amber-950 md:grid-cols-3">
                    <li class="flex gap-3">
                        <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-amber-200/70 text-amber-900" aria-hidden="true">
                            <i class="fa-solid fa-gauge-high text-xs"></i>
                        </span>
                        <span>{{ __('Proctoring signals can raise a violation score; policy may auto-submit after warnings.') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-amber-200/70 text-amber-900" aria-hidden="true">
                            <i class="fa-solid fa-user-shield text-xs"></i>
                        </span>
                        <span>{{ __('Staff may end or submit a session under your institution’s rules (e.g. emergency or misconduct).') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-amber-200/70 text-amber-900" aria-hidden="true">
                            <i class="fa-solid fa-clock text-xs"></i>
                        </span>
                        <span>{{ __('Timer and submission windows still apply — submit before time runs out when allowed.') }}</span>
                    </li>
                </ul>
            </section>
        @endif

        {{-- Footer actions --}}
        <footer class="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200/90 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                {{ __('Back to dashboard') }}
            </a>
            <a
                href="{{ $continueHref }}"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-sky-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-sky-700"
            >
                {{ $primaryLabel }}
                <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
            </a>
        </footer>
    </div>
@endsection
