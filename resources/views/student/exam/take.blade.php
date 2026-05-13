@extends('layouts.exam-runtime', [
    'enableLiveSockets' => $enableLiveSockets,
    'allowPollingFallback' => $allowPollingFallback,
    'requireCameraMonitoring' => $requireCameraMonitoring,
    'isAssignmentMode' => $isAssignmentMode ?? false,
    'assignmentClipboardBlock' => $assignmentClipboardBlock ?? false,
    'documentTitle' => $documentTitle ?? null,
])

@section('content')
@php
    $quiz = $examSession->exam;
    $tz = config('app.timezone');
@endphp
@if ($isAssignmentMode ?? false)
    <div id="assignment-coursework-panel" class="border-b border-sky-200 bg-sky-50/90">
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
                    <div class="rounded-lg border border-sky-100 bg-white/90 px-3 py-2">
                        <dt class="text-xs font-medium text-qs-muted">{{ __('Availability window') }}</dt>
                        <dd class="mt-0.5 font-medium text-qs-text">
                            {{ $quiz->start_time?->timezone($tz)->format('Y-m-d H:i') ?? '—' }}
                            —
                            {{ $quiz->end_time?->timezone($tz)->format('Y-m-d H:i') ?? '—' }}
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
        </div>
    </div>
@endif
<div id="exam-app" class="min-h-screen flex flex-col">
    <header class="border-b border-qs-soft bg-qs-bg shadow-sm shrink-0">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 id="exam-title" class="text-lg font-semibold qs-heading">{{ __('Loading…') }}</h1>
                <p id="exam-subtitle" class="text-sm text-qs-muted hidden"></p>
            </div>
            <div class="flex items-center gap-4">
                @unless ($isAssignmentMode ?? false)
                    <button type="button" id="btn-fullscreen"
                        class="text-sm px-3 py-1.5 rounded border border-qs-soft bg-qs-bg hover:bg-qs-card">
                        {{ __('Fullscreen') }}
                    </button>
                    <div id="exam-timer" class="font-mono text-xl font-semibold tabular-nums text-qs-accent"
                        aria-live="polite">--:--</div>
                    <span id="fullscreen-exit-notice" class="hidden max-w-[220px] shrink-0 text-xs text-qs-text"
                        role="status"></span>
                @else
                    <span class="max-w-md text-xs leading-snug text-qs-muted">{{ __('Coursework: work at your own pace within the due date. There is no exam countdown clock here.') }}</span>
                @endunless
            </div>
        </div>
    </header>

    <div class="flex flex-1 flex-col lg:flex-row max-w-7xl mx-auto w-full min-h-0">
        {{-- Live proctoring (camera + local mesh preview) — only when institution requires camera monitoring --}}
        <aside id="proctoring-live-aside"
            class="{{ $requireCameraMonitoring ? '' : 'hidden' }} w-full lg:w-72 shrink-0 border-b lg:border-b-0 lg:border-r border-qs-soft bg-qs-bg p-3 flex flex-col gap-3 min-h-0 lg:max-w-[20rem]">
            <p class="text-xs font-semibold text-qs-muted uppercase tracking-wide">{{ __('Live proctoring') }}</p>
            <div class="relative aspect-video w-full max-h-56 lg:max-h-none overflow-hidden rounded-lg border border-qs-soft bg-black shadow-inner">
                <video id="proctoring-video" class="absolute inset-0 h-full w-full object-cover" playsinline muted autoplay></video>
                <canvas id="proctoring-face-canvas" class="absolute inset-0 h-full w-full pointer-events-none" aria-hidden="true"></canvas>
            </div>
            <p id="proctoring-local-hint" class="text-xs leading-snug text-qs-muted min-h-[2.5rem]"></p>
            <p class="text-[11px] leading-snug text-qs-muted">{{ __('Face tracking runs on your device. Only summary events are sent to the server in batches.') }}</p>
        </aside>

        <div class="flex flex-1 flex-col md:flex-row min-w-0 min-h-0">
            <aside class="w-full md:w-52 shrink-0 border-b md:border-b-0 md:border-r border-qs-soft bg-qs-bg p-3 overflow-y-auto max-h-40 md:max-h-none">
                <p class="text-xs font-semibold text-qs-muted uppercase mb-2">{{ __('Questions') }}</p>
                <nav id="question-nav" class="flex flex-wrap md:flex-col gap-2"></nav>
                <div id="exam-answer-summary" class="mt-3 space-y-1 border-t border-qs-soft pt-3 text-xs leading-snug text-qs-muted"></div>
            </aside>

            <main class="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden">
                <div id="exam-banner" class="hidden px-4 py-2 text-sm border-b border-qs-accent/35 bg-qs-accent/15 text-qs-text"></div>
                <div id="exam-nav-actions" class="hidden shrink-0 border-b border-qs-soft bg-qs-bg px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="btn-q-back" data-q-action
                            class="qs-btn-secondary min-h-[44px] px-4 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ __('Back') }}
                        </button>
                        <button type="button" id="btn-q-next" data-q-action
                            class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed">
                            {{ __('Next') }}
                        </button>
                    </div>
                    <p id="question-progress-label" class="text-xs text-qs-muted"></p>
                </div>
                <div id="exam-main" class="flex-1 overflow-y-auto p-4 md:p-6">
                    <p id="exam-loading" class="text-qs-muted">{{ ($isAssignmentMode ?? false) ? __('Loading assignment…') : __('Loading exam…') }}</p>
                    <div id="question-container" class="hidden space-y-4"></div>
                </div>
            </main>
        </div>
    </div>

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

    <footer class="border-t border-qs-soft bg-qs-bg shrink-0">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div id="save-indicator" class="text-sm text-qs-muted">{{ __('Answers save automatically.') }}</div>
            <div class="flex items-center gap-2">
                @unless ($isAssignmentMode ?? false)
                    <span id="video-status" class="text-xs text-qs-muted hidden md:inline">{{ __('Camera required for proctoring.') }}</span>
                @endunless
                <button type="button" id="btn-submit"
                    class="qs-btn-primary px-5 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ ($isAssignmentMode ?? false) ? __('Submit assignment') : __('Submit exam') }}
                </button>
            </div>
        </div>
    </footer>

    <div id="essay-clipboard-toast" role="status" aria-live="polite"
        class="pointer-events-none fixed bottom-20 left-1/2 z-50 hidden max-w-sm -translate-x-1/2 rounded-lg border border-qs-soft bg-qs-card px-4 py-2 text-center text-sm text-qs-text shadow-lg">
    </div>
</div>
@endsection
