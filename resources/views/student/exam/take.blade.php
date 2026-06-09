@extends('layouts.exam-runtime', [
    'enableLiveSockets' => $enableLiveSockets,
    'allowPollingFallback' => $allowPollingFallback,
    'requireCameraMonitoring' => $requireCameraMonitoring,
    'isAssignmentMode' => $isAssignmentMode ?? false,
    'assignmentClipboardBlock' => $assignmentClipboardBlock ?? false,
    'assignmentAllowsText' => $assignmentAllowsText ?? true,
    'assignmentAllowCode' => $assignmentAllowCode ?? false,
    'examClipboardLock' => $examClipboardLock ?? false,
    'examScreenshotMitigation' => $examScreenshotMitigation ?? false,
    'examScreenRecordMitigation' => $examScreenRecordMitigation ?? false,
    'documentTitle' => $documentTitle ?? null,
])

@section('content')
@php
    $quiz = $examSession->exam;
    $tz = config('app.timezone');
    $assignmentTake = (bool) ($isAssignmentMode ?? false);
    $proctoringAsideClass = $assignmentTake || ! ($requireCameraMonitoring ?? false)
        ? 'hidden'
        : 'order-3 min-h-0 w-full shrink-0 lg:h-full lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain';
@endphp

@if ($assignmentTake)
    @include('student.exam.partials.assignment-take', [
        'quiz' => $quiz,
        'examSession' => $examSession,
        'tz' => $tz,
        'assignmentClipboardBlock' => $assignmentClipboardBlock ?? false,
        'assignmentAllowsFiles' => $assignmentAllowsFiles ?? false,
        'assignmentAttachmentRequired' => $assignmentAttachmentRequired ?? false,
    ])
@else
<div class="flex h-[100vh] max-h-[100vh] min-h-0 flex-col overflow-hidden bg-slate-50 text-slate-900">

<div id="exam-app" class="flex min-h-0 flex-1 flex-col">
    <header class="z-50 shrink-0 border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-[96rem] flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white" aria-hidden="true">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="min-w-0">
                    <h1 id="exam-title" class="truncate text-sm font-extrabold text-slate-900 sm:text-base">{{ __('Loading…') }}</h1>
                    <p id="exam-subtitle" class="mt-0.5 hidden truncate text-xs font-medium text-slate-500"></p>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" id="btn-fullscreen"
                    class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    {{ __('Fullscreen') }}
                </button>
                <span id="exam-timer-wrap" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 tabular-nums">
                    <i class="fa-regular fa-clock text-slate-400" aria-hidden="true"></i>
                    <span id="exam-timer">--:--</span>
                    <span class="hidden font-semibold text-slate-400 sm:inline">{{ __('left') }}</span>
                </span>
                <span id="exam-recording-badge" class="hidden items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700 md:inline-flex" role="status">
                    <i class="fa-solid fa-circle text-[7px]" aria-hidden="true"></i>
                    {{ __('Recording') }}
                </span>
                <span id="fullscreen-exit-notice" class="hidden max-w-[200px] shrink-0 text-xs font-medium text-amber-700" role="status"></span>
                <button type="button" id="btn-submit"
                    class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ __('Submit') }}
                </button>
            </div>
        </div>
    </header>

    <div
        id="exam-fullscreen-gate"
        role="dialog"
        aria-modal="true"
        aria-labelledby="exam-fullscreen-gate-title"
        class="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-4 bg-slate-950/95 px-6 text-center"
    >
        <p id="exam-fullscreen-gate-title" class="max-w-md text-base font-semibold leading-snug text-white">
            {{ __('This exam runs in full screen. Enter full screen to continue.') }}
        </p>
        <button
            type="button"
            id="btn-fullscreen-gate"
            class="rounded-xl bg-white px-6 py-3 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-slate-100"
        >
            {{ __('Enter full screen') }}
        </button>
    </div>

    <section id="question-progress-rail" class="shrink-0 border-b border-slate-200 bg-slate-50/95 backdrop-blur">
        <div class="mx-auto max-w-[96rem] px-4 py-2">
            <h2 class="sr-only">{{ __('Question navigation') }}</h2>
            <div id="question-nav-scroll" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-0.5 [-webkit-overflow-scrolling:touch]">
                <nav id="question-nav" class="flex min-w-0 gap-2" aria-label="{{ __('Questions') }}"></nav>
            </div>
        </div>
    </section>

    <div id="exam-banner" class="hidden shrink-0 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-950"></div>

    <div
        id="exam-workspace"
        class="mx-auto flex min-h-0 min-w-0 w-full max-w-[96rem] flex-1 flex-col gap-6 overflow-y-auto overscroll-y-contain px-4 py-4 lg:grid lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)_minmax(0,300px)] lg:items-stretch lg:gap-6 lg:overflow-hidden lg:overscroll-none lg:px-4 lg:py-6"
    >
        <aside id="exam-meta-aside" class="order-2 hidden min-h-0 shrink-0 lg:order-1 lg:flex lg:flex-col lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain">
            <div class="lg:sticky lg:top-0 lg:space-y-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('Current') }}</p>
                        <p id="exam-progress-label" class="text-[10px] font-semibold tabular-nums text-slate-500">—</p>
                    </div>
                    <h2 id="exam-current-q-label" class="mt-1 text-xl font-extrabold leading-tight text-slate-900">—</h2>
                    <p id="exam-current-q-type" class="mt-0.5 text-xs font-semibold text-slate-500">—</p>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100">
                        <div id="exam-progress-fill" class="h-1.5 w-0 rounded-full bg-slate-900 transition-[width] duration-300"></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ __('Rules') }}</p>
                    <p class="mt-1.5 text-xs leading-snug text-slate-600">
                        @if ($requireCameraMonitoring ?? false)
                            {{ __('Stay on camera, in frame, and in full screen as your school requires.') }}
                        @else
                            {{ __('Stay in full screen where your school requires it.') }}
                        @endif
                    </p>
                </div>
            </div>
        </aside>

        <section class="order-1 flex min-h-0 min-w-0 flex-col lg:order-2 lg:min-h-0 lg:flex-1 lg:overflow-hidden">
            <div id="exam-main" class="flex min-h-0 flex-1 flex-col lg:overflow-y-auto lg:overscroll-y-contain">
                <p id="exam-loading" class="text-sm text-slate-500">{{ __('Loading exam…') }}</p>
                <div id="question-container" class="hidden min-h-0 flex-1"></div>
            </div>
        </section>

        <aside id="proctoring-live-aside" class="{{ $proctoringAsideClass }}">
            @include('student.exam.partials.take-proctoring-panel')
        </aside>
    </div>

    @include('student.exam.partials.take-overlays', ['assignmentTake' => false])
</div>
</div>
@endif
@endsection
