@extends('layouts.exam-runtime-arena', [
    'enableLiveSockets' => $enableLiveSockets,
    'allowPollingFallback' => $allowPollingFallback,
    'requireCameraMonitoring' => $requireCameraMonitoring,
    'examClipboardLock' => $examClipboardLock ?? false,
    'examScreenshotMitigation' => $examScreenshotMitigation ?? false,
    'examScreenRecordMitigation' => $examScreenRecordMitigation ?? false,
    'documentTitle' => $documentTitle ?? null,
])

@section('content')
<div id="exam-app" class="qs-arena__root">
    <div class="qs-arena__bg" aria-hidden="true">
        <div class="qs-arena__bg-image"></div>
        <div class="qs-arena__bg-veil"></div>
    </div>

    <header class="qs-arena__topbar" role="banner">
        <div class="qs-arena__topbar-left">
            <div class="qs-arena__brand" aria-hidden="true">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="qs-arena__brand-meta">
                <p id="exam-title" class="qs-arena__brand-title">{{ __('Loading…') }}</p>
                <p id="exam-subtitle" class="qs-arena__brand-subtitle"></p>
            </div>
        </div>
        <div class="qs-arena__topbar-right">
            <span id="exam-timer-wrap" class="qs-arena__timer" aria-live="polite">
                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                <span id="exam-timer">--:--</span>
            </span>
            <span id="exam-recording-badge" class="qs-arena__recording" role="status">
                <i class="fa-solid fa-circle" aria-hidden="true"></i>
                {{ __('Recording') }}
            </span>
            <span id="fullscreen-exit-notice" class="qs-arena__fs-notice" role="status"></span>
            <button type="button" id="btn-fullscreen" class="qs-arena__icon-btn" aria-label="{{ __('Fullscreen') }}">
                <i class="fa-solid fa-expand" aria-hidden="true"></i>
            </button>
            <button type="button" id="btn-submit" class="qs-arena__submit-btn">
                {{ __('Submit') }}
            </button>
        </div>
    </header>

    {{-- Single continuous progress bar (one question at a time, no step buckets). --}}
    <section class="qs-arena__progress" aria-label="{{ __('Exam progress') }}">
        <div class="qs-arena__progress-inner">
            <div class="qs-arena__progress-track" role="progressbar" aria-valuemin="0" aria-valuenow="0" aria-valuemax="0" aria-label="{{ __('Questions answered') }}">
                <span id="arena-progress-bar" class="qs-arena__progress-fill"></span>
            </div>
            <span id="arena-progress-label" class="qs-arena__progress-label">0/0</span>
        </div>
    </section>

    <div id="exam-banner" class="qs-arena__banner" role="status"></div>

    <main class="qs-arena__stage">
        <div id="question-progress-rail" class="qs-arena__hidden-rail">
            {{-- The arena does its own step rail above. We keep this empty container with the
                 id present so any shared module that probes for it doesn't blow up. --}}
            <nav id="question-nav" class="qs-arena__hidden-rail" aria-hidden="true"></nav>
        </div>

        <p id="exam-loading" class="qs-arena__loading">{{ __('Loading exam…') }}</p>

        {{-- Question card (Kahoot-style). studentExamArena.js renders the inside.
             No "locked in" / feedback band — selecting an answer is the
             feedback; the next question loads immediately after the save. --}}
        <article id="question-container" class="qs-arena__card qs-arena__hidden" aria-live="polite" aria-atomic="false">
            <header class="qs-arena__card-head">
                <p id="arena-q-step" class="qs-arena__card-step">{{ __('Question') }} 1</p>
                <span id="arena-q-timer-pill" class="qs-arena__card-timer">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                    <span id="arena-q-timer-text">--:--</span>
                </span>
            </header>
            <h1 id="arena-q-text" class="qs-arena__card-prompt">—</h1>

            <div id="arena-q-options" class="qs-arena__options" role="group" aria-label="{{ __('Answer options') }}"></div>

            <div class="qs-arena__card-foot">
                <button type="button" id="btn-q-back" class="qs-arena__chip-btn" data-q-action="back">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>{{ __('Back') }}</span>
                </button>
                <span id="save-indicator" class="qs-arena__save-indicator">—</span>
                <button type="button" id="btn-q-next" class="qs-arena__primary-btn" data-q-action="next">
                    <span>{{ __('Next') }}</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </article>

        {{-- Completion screen (shown after the last question; final submit lives here). --}}
        <section id="arena-completion" class="qs-arena__completion qs-arena__hidden" aria-live="polite">
            <header class="qs-arena__completion-head">
                <div class="qs-arena__completion-icon" aria-hidden="true">
                    <i class="fa-solid fa-award"></i>
                </div>
                <h2 class="qs-arena__completion-title">{{ __('Assessment Complete') }}</h2>
                <p class="qs-arena__completion-sub">{{ __('Tap any number to revisit a question, then submit when you are ready.') }}</p>
            </header>
            <ol id="arena-completion-list" class="qs-arena__completion-list" role="list"></ol>
            <button type="button" id="btn-arena-submit" class="qs-arena__cta-btn" data-submit-mirror>
                <span>{{ __('Submit assessment') }}</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
            <p id="arena-completion-foot" class="qs-arena__completion-foot">
                {{ __('You can still go back and change an answer before submitting.') }}
            </p>
            <button type="button" id="btn-arena-review" class="qs-arena__ghost-btn">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>{{ __('Back to questions') }}</span>
            </button>
        </section>
    </main>

    {{-- Floating, draggable camera PiP (proctoring monitor lives in here). --}}
    @if ($requireCameraMonitoring)
        <aside id="arena-cam-pip" class="qs-arena__campip" aria-label="{{ __('Live camera') }}">
            <div class="qs-arena__campip-handle" data-pip-handle="1">
                <span class="qs-arena__campip-handle-label">
                    <i class="fa-solid fa-circle qs-arena__campip-dot" aria-hidden="true"></i>
                    {{ __('Live') }}
                </span>
                <button type="button" id="btn-arena-cam-toggle" class="qs-arena__campip-toggle" aria-label="{{ __('Minimize camera') }}">
                    <i class="fa-solid fa-minus" aria-hidden="true"></i>
                </button>
            </div>
            <div id="arena-cam-body" class="qs-arena__campip-body">
                <video id="proctoring-video" class="qs-arena__campip-video" playsinline muted autoplay></video>
                <canvas id="proctoring-face-canvas" class="qs-arena__campip-canvas" aria-hidden="true"></canvas>
                <div class="qs-arena__campip-status">
                    <span><i class="fa-solid fa-eye" aria-hidden="true"></i> <span id="proctor-eye-status">{{ __('Normal') }}</span></span>
                    <span id="proctor-face-status" class="qs-arena__campip-face">—</span>
                </div>
            </div>

            {{-- Audio bar + live-feed indicator. Drives off the proctoring
                 engine's default selectors so it works identically to the
                 classic runtime's mic meter — just rendered inside the PiP. --}}
            <div class="qs-arena__campip-feed" aria-live="polite">
                <div class="qs-arena__campip-feedrow">
                    <span class="qs-arena__campip-feeddot" id="arena-feed-dot" data-state="pending" aria-hidden="true"></span>
                    <span class="qs-arena__campip-feedlabel" id="arena-feed-label">{{ __('Connecting…') }}</span>
                </div>
                <div class="qs-arena__campip-mic" aria-label="{{ __('Microphone level') }}">
                    <i class="fa-solid fa-microphone" aria-hidden="true"></i>
                    <span class="qs-arena__campip-mic-track">
                        <span id="arena-mic-bar" class="qs-arena__campip-mic-fill"></span>
                    </span>
                    <span id="arena-mic-label" class="qs-arena__campip-mic-label" data-tone="quiet">{{ __('Off') }}</span>
                </div>
            </div>

            <p id="proctoring-local-hint" class="qs-arena__campip-hint"></p>
        </aside>
        {{-- Hidden mic-meter scaffold to keep the proctoring engine's selectors happy
             (the visible mic meter above uses arena-specific ids so the engine and
             the arena UI never fight over the same DOM nodes). --}}
        <div class="qs-arena__hidden-meters" aria-hidden="true">
            <div id="exam-mic-wave"></div>
            <div id="exam-mic-level-bar"></div>
            <span id="exam-mic-level-label"></span>
            <span id="proctor-eye-line"></span>
            <span id="proctor-camera-live-pill"></span>
            <span id="proctor-risk-score">0</span>
        </div>
    @else
        {{-- Even without camera monitoring, the JS expects these refs — render hidden stubs. --}}
        <div class="qs-arena__hidden-meters" aria-hidden="true">
            <video id="proctoring-video" muted playsinline></video>
            <canvas id="proctoring-face-canvas"></canvas>
            <div id="exam-mic-wave"></div>
            <div id="exam-mic-level-bar"></div>
            <span id="exam-mic-level-label"></span>
            <span id="proctor-eye-line"></span>
            <span id="proctor-eye-status"></span>
            <span id="proctor-camera-live-pill"></span>
            <span id="proctor-risk-score">0</span>
            <span id="proctor-face-status"></span>
        </div>
    @endif

    {{-- Fullscreen entry gate. --}}
    <div
        id="exam-fullscreen-gate"
        role="dialog"
        aria-modal="true"
        aria-labelledby="exam-fullscreen-gate-title"
        class="qs-arena__fs-gate"
    >
        <p id="exam-fullscreen-gate-title" class="qs-arena__fs-gate-text">
            {{ __('This exam runs in full screen. Enter full screen to continue.') }}
        </p>
        <button type="button" id="btn-fullscreen-gate" class="qs-arena__fs-gate-btn">
            {{ __('Enter full screen') }}
        </button>
    </div>

    {{-- Tab switch warning (dark themed, matches the screenshot). --}}
    <div
        id="exam-tab-switch-modal"
        class="qs-arena__tabswitch qs-arena__hidden"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="exam-tab-switch-title"
        aria-describedby="exam-tab-switch-desc"
    >
        <div class="qs-arena__tabswitch-card">
            <div class="qs-arena__tabswitch-icon" aria-hidden="true">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h2 id="exam-tab-switch-title" class="qs-arena__tabswitch-title">
                {{ __('Tab Switch Detected') }}
            </h2>
            <p id="exam-tab-switch-desc" class="qs-arena__tabswitch-desc">
                {{ __('Leaving the quiz screen is not allowed during the assessment. Repeated violations may result in automatic submission.') }}
            </p>
            <div id="exam-tab-switch-dots" class="qs-arena__tabswitch-dots" aria-hidden="true"></div>
            <p id="exam-tab-switch-level" class="qs-arena__tabswitch-level"></p>
            <button type="button" id="btn-tab-switch-dismiss" class="qs-arena__tabswitch-cta">
                <i class="fa-solid fa-expand" aria-hidden="true"></i>
                {{ __('Return to Quiz') }}
            </button>
        </div>
    </div>

    {{-- Pause overlay (reuses classic ids). --}}
    <div id="exam-timer-pause-overlay" class="qs-arena__pause qs-arena__hidden" role="dialog" aria-modal="true" aria-labelledby="exam-pause-title">
        <div class="qs-arena__pause-card">
            <p id="exam-pause-title" class="qs-arena__pause-title">{{ __('Exam paused') }}</p>
            <p id="exam-pause-body" class="qs-arena__pause-body">
                {{ __('Your timer is frozen. When you are ready, resume to continue from where you left off.') }}
            </p>
            <button type="button" id="btn-exam-resume" class="qs-arena__cta-btn">{{ __('Resume') }}</button>
        </div>
    </div>

    {{-- Proctoring review overlay (screen-setup check). --}}
    <div
        id="proctoring-review-overlay"
        class="qs-arena__review qs-arena__hidden"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="proctoring-review-overlay-title"
        aria-describedby="proctoring-review-overlay-desc"
    >
        <div class="qs-arena__review-card">
            <div class="qs-arena__review-icon" aria-hidden="true">
                <i class="fa-solid fa-display"></i>
            </div>
            <h2 id="proctoring-review-overlay-title" class="qs-arena__review-title">
                {{ __('Screen setup needs attention') }}
            </h2>
            <p id="proctoring-review-overlay-desc" class="qs-arena__review-desc">
                {{ __('Your school’s integrity rules require a single primary display during this session. Disconnect or mirror extra monitors, close extended desktop, then confirm when you are ready to continue.') }}
            </p>
            <button type="button" id="btn-proctoring-overlay-continue" class="qs-arena__cta-btn">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                {{ __('I have fixed this — continue') }}
            </button>
        </div>
    </div>

    <div id="essay-clipboard-toast" role="status" aria-live="polite" class="qs-arena__clipboard-toast qs-arena__hidden"></div>
</div>
@endsection
