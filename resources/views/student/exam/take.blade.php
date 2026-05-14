@extends('layouts.exam-runtime', [
    'enableLiveSockets' => $enableLiveSockets,
    'allowPollingFallback' => $allowPollingFallback,
    'requireCameraMonitoring' => $requireCameraMonitoring,
    'isAssignmentMode' => $isAssignmentMode ?? false,
    'assignmentClipboardBlock' => $assignmentClipboardBlock ?? false,
    'examClipboardLock' => $examClipboardLock ?? false,
    'examScreenshotMitigation' => $examScreenshotMitigation ?? false,
    'examScreenRecordMitigation' => $examScreenRecordMitigation ?? false,
    'documentTitle' => $documentTitle ?? null,
])

@section('content')
@php
    $quiz = $examSession->exam;
    $tz = config('app.timezone');
    $proctoringAsideClass = ($isAssignmentMode ?? false) || ! ($requireCameraMonitoring ?? false)
        ? 'hidden'
        : 'order-3 min-h-0 w-full shrink-0 lg:h-full lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain';
@endphp
<div class="flex h-[100vh] max-h-[100vh] min-h-0 flex-col overflow-hidden bg-slate-50 text-slate-900">
@if ($isAssignmentMode ?? false)
    <div id="assignment-coursework-panel" class="max-h-[min(42vh,22rem)] shrink-0 overflow-y-auto overscroll-y-contain border-b border-sky-200 bg-sky-50/90">
        <div class="max-w-7xl mx-auto px-4 py-4 space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-900/80">{{ __('Coursework assignment') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-qs-text">{{ $quiz?->title }}</h2>
                    @if ($quiz?->course)
                        <p class="mt-0.5 text-sm text-qs-muted">{{ $quiz->course->code }} — {{ $quiz->course->title }}</p>
                    @endif
                </div>
            </div>
            @if (filled($quiz?->description))
                <div class="rounded-xl border border-sky-100 bg-white/90 px-4 py-3 text-sm leading-relaxed text-qs-text whitespace-pre-wrap">{{ $quiz->description }}</div>
            @endif
            <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-lg border border-sky-100 bg-white/90 px-3 py-2">
                    <dt class="text-xs font-medium text-qs-muted">{{ __('Due') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $quiz?->due_at?->timezone($tz)->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
                @if ($quiz?->start_time || $quiz?->end_time)
                    @php
                        $opensA = $quiz->start_time?->timezone($tz)->format('Y-m-d H:i');
                        $closesA = $quiz->end_time?->timezone($tz)->format('Y-m-d H:i');
                    @endphp
                    <div class="rounded-lg border border-sky-100 bg-white/90 px-3 py-2">
                        <dt class="text-xs font-medium text-qs-muted">{{ __('When you can take this') }}</dt>
                        <dd class="mt-0.5 space-y-1 font-medium text-qs-text">
                            @if ($opensA)
                                <p class="text-sm"><span class="text-qs-muted">{{ __('Opens') }}</span> · {{ $opensA }}</p>
                            @endif
                            @if ($closesA)
                                <p class="text-sm"><span class="text-qs-muted">{{ __('Closes') }}</span> · {{ $closesA }}</p>
                            @endif
                        </dd>
                    </div>
                @endif
                <div class="rounded-lg border border-sky-100 bg-white/90 px-3 py-2 sm:col-span-2 lg:col-span-1">
                    <dt class="text-xs font-medium text-qs-muted">{{ __('Submission & grades') }}</dt>
                    <dd class="mt-0.5 space-y-1 text-qs-text">
                        <p id="assignment-status-line" class="font-medium">
                            @if ($examSession->status === 'submitted')
                                {{ $examSession->submitted_late ? __('Submitted late') : __('Submitted') }}
                            @else
                                {{ __('In progress — your answers save automatically.') }}
                            @endif
                        </p>
                        <p id="assignment-grade-line" class="text-sm text-qs-muted"></p>
                    </dd>
                </div>
            </dl>
            @if ($assignmentClipboardBlock ?? false)
                <p class="rounded-lg border border-amber-200/80 bg-amber-50/90 px-3 py-2 text-xs leading-relaxed text-amber-950">
                    {{ __('Copy and paste is disabled in typed answer fields for this assignment to support academic integrity.') }}
                </p>
            @endif
            <p class="text-xs text-qs-muted">{{ __('This coursework does not use live camera or microphone invigilation unless your school explicitly enabled an exception.') }}</p>
            <div id="assignment-file-upload-slot" class="mt-3 rounded-lg border border-sky-100 bg-white/90 px-3 py-2 text-xs text-slate-700">
                <p class="font-semibold text-slate-900">{{ __('File upload (if enabled)') }}</p>
                <p class="mt-1 text-qs-muted">{{ __('When your instructor allows files, use the picker below. Only allowed types and sizes are accepted.') }}</p>
                <input type="file" id="assignment-file-input" class="mt-2 block w-full text-xs" disabled />
                <p id="assignment-file-status" class="mt-1 text-xs text-slate-600"></p>
            </div>
        </div>
    </div>
@endif

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
                @unless ($isAssignmentMode ?? false)
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
                @else
                    <span class="max-w-md text-xs leading-snug text-slate-500">{{ __('Coursework: work at your own pace within the due date. There is no exam countdown clock here.') }}</span>
                @endunless
                <button type="button" id="btn-submit"
                    class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ ($isAssignmentMode ?? false) ? __('Submit assignment') : __('Submit') }}
                </button>
            </div>
        </div>
    </header>

    @unless ($isAssignmentMode ?? false)
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
    @endunless

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
        {{-- Left: meta + rules (desktop) --}}
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

        {{-- Center: question (only this column scrolls on lg+) --}}
        <section class="order-1 flex min-h-0 min-w-0 flex-col lg:order-2 lg:min-h-0 lg:flex-1 lg:overflow-hidden">
            <div id="exam-main" class="flex min-h-0 flex-1 flex-col lg:overflow-y-auto lg:overscroll-y-contain">
                <p id="exam-loading" class="text-sm text-slate-500">{{ ($isAssignmentMode ?? false) ? __('Loading assignment…') : __('Loading exam…') }}</p>
                <div id="question-container" class="hidden min-h-0 flex-1"></div>
            </div>
        </section>

        {{-- Right: live proctoring (matches legacy test id + video hooks) --}}
        <aside id="proctoring-live-aside" class="{{ $proctoringAsideClass }}">
            <div class="space-y-3">
                <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-900">{{ __('Live camera') }}</h3>
                                <p class="text-xs font-medium text-slate-500">{{ __('Face framing preview') }}</p>
                            </div>
                            <span id="proctor-camera-live-pill" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ __('Live') }}</span>
                        </div>
                    </div>
                    <div class="p-3 pb-3">
                        <div class="relative aspect-[4/3] overflow-hidden rounded-3xl bg-slate-950">
                            <video id="proctoring-video" class="absolute inset-0 h-full w-full object-cover" playsinline muted autoplay></video>
                            <canvas id="proctoring-face-canvas" class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-slate-950/40 lg:hidden" aria-hidden="true">
                                <div class="absolute left-1/2 top-[45%] h-28 w-24 -translate-x-1/2 -translate-y-1/2 rounded-[45%] border border-emerald-400/80"></div>
                            </div>
                            <div class="absolute bottom-3 left-3 right-3 rounded-2xl bg-black/55 px-3 py-2 text-xs font-bold text-white backdrop-blur">
                                <div class="flex items-center justify-between gap-2">
                                    <span><i class="fa-solid fa-eye me-1 text-cyan-300" aria-hidden="true"></i><span id="proctor-eye-line">{{ __('Eyes on screen') }}</span></span>
                                    <span id="proctor-eye-status" class="text-emerald-300">{{ __('Normal') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-slate-50 p-2.5">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ __('Face') }}</p>
                                <p id="proctor-face-status" class="mt-0.5 text-sm font-extrabold text-slate-900">—</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-2.5">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ __('Risk score') }}</p>
                                <p id="proctor-risk-score" class="mt-0.5 text-sm font-extrabold text-slate-900">0</p>
                            </div>
                        </div>
                        <p id="proctoring-local-hint" class="mt-2 min-h-[1.25rem] text-xs leading-snug text-slate-500"></p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="flex items-start gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                            <i class="fa-solid fa-microphone text-xs"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-xs font-extrabold text-slate-900">{{ __('Microphone') }}</h3>
                                <span id="exam-mic-level-label" class="text-[10px] font-bold text-emerald-700">{{ __('Normal') }}</span>
                            </div>
                            <div id="exam-mic-wave" class="qs-exam-sound-wave mt-1.5 flex h-7 items-end justify-center gap-1 rounded-lg bg-slate-50 px-2 py-1">
                                @foreach (range(1, 7) as $_)
                                    <span class="inline-block w-1.5 rounded-full bg-slate-900"></span>
                                @endforeach
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="shrink-0 text-[10px] font-bold text-slate-500">{{ __('Input level') }}</span>
                                <div class="h-1 min-w-0 flex-1 rounded-full bg-slate-100">
                                    <div id="exam-mic-level-bar" class="h-1 w-[8%] rounded-full bg-slate-900 transition-[width] duration-150"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-5">
                    <h3 class="text-sm font-extrabold text-amber-900">{{ __('Invigilation notice') }}</h3>
                    <p class="mt-2 text-xs font-medium leading-relaxed text-amber-800">
                        {{ __('Tab changes, leaving fullscreen, and face visibility may be recorded for review when enabled by your school.') }}
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-amber-900/90">
                        {{ __('Camera-based phone detection is probabilistic and not guaranteed; it is used for warnings and review, not as proof of misconduct on its own.') }}
                    </p>
                </div>
            </div>
        </aside>
    </div>

    <div
        id="proctoring-review-overlay"
        class="hidden fixed inset-0 z-[71] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="proctoring-review-overlay-title"
        aria-describedby="proctoring-review-overlay-desc"
    >
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white px-6 py-7 shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-700" aria-hidden="true">
                <i class="fa-solid fa-display text-2xl"></i>
            </div>
            <h2 id="proctoring-review-overlay-title" class="mt-4 text-lg font-bold tracking-tight text-slate-900">
                {{ __('Screen setup needs attention') }}
            </h2>
            <p id="proctoring-review-overlay-desc" class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ __('Your school’s integrity rules require a single primary display during this session. Disconnect or mirror extra monitors, close extended desktop, then confirm when you are ready to continue.') }}
            </p>
            <button
                type="button"
                id="btn-proctoring-overlay-continue"
                class="mt-6 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                {{ __('I have fixed this — continue') }}
            </button>
        </div>
    </div>

    @unless ($isAssignmentMode ?? false)
        <div
            id="exam-tab-switch-modal"
            class="hidden fixed inset-0 z-[68] flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-[2px]"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="exam-tab-switch-title"
            aria-describedby="exam-tab-switch-desc"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white px-6 py-7 text-center shadow-2xl">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600" aria-hidden="true">
                    <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
                </div>
                <h2 id="exam-tab-switch-title" class="mt-4 text-lg font-bold tracking-tight text-slate-900">
                    {{ __('Tab switch detected') }}
                </h2>
                <p id="exam-tab-switch-desc" class="mt-2 text-sm leading-relaxed text-slate-600">
                    {{ __('Leaving the exam tab or window is not allowed during the assessment. Repeated violations may result in automatic submission.') }}
                </p>
                <div id="exam-tab-switch-dots" class="mt-5 flex justify-center gap-2" aria-hidden="true"></div>
                <p id="exam-tab-switch-level" class="mt-2 text-sm font-semibold text-red-600"></p>
                <button
                    type="button"
                    id="btn-tab-switch-dismiss"
                    class="mt-6 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-700"
                >
                    <i class="fa-solid fa-expand" aria-hidden="true"></i>
                    {{ __('Return to exam') }}
                </button>
            </div>
        </div>
    @endunless

    <div id="exam-timer-pause-overlay" class="hidden fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 px-4" role="dialog" aria-modal="true" aria-labelledby="exam-pause-title">
        <div class="max-w-md rounded-2xl border border-amber-200/80 bg-amber-50 px-5 py-6 text-center shadow-xl">
            @if ($isAssignmentMode ?? false)
                <p id="exam-pause-title" class="text-lg font-semibold text-amber-950">{{ __('Session paused') }}</p>
                <p id="exam-pause-body" class="mt-2 text-sm leading-relaxed text-amber-900/90">
                    {{ __('Your connection was interrupted. Press Resume when you are ready to continue your assignment.') }}
                </p>
            @else
                <p id="exam-pause-title" class="text-lg font-semibold text-amber-950">{{ __('Exam paused') }}</p>
                <p id="exam-pause-body" class="mt-2 text-sm leading-relaxed text-amber-900/90">
                    {{ __('Your timer is frozen. When you are ready, resume to continue from where you left off.') }}
                </p>
            @endif
            <button type="button" id="btn-exam-resume" class="mt-5 inline-flex min-h-[44px] w-full items-center justify-center rounded-xl bg-amber-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-900">
                {{ __('Resume') }}
            </button>
        </div>
    </div>

    <div id="essay-clipboard-toast" role="status" aria-live="polite"
        class="pointer-events-none fixed bottom-6 left-1/2 z-50 hidden max-w-sm -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-center text-sm text-slate-800 shadow-lg">
    </div>
</div>
</div>
@endsection
