@php
    /** @var \App\Models\Quiz|null $quiz */
    /** @var \App\Models\ExamSession $examSession */
    $tz = $tz ?? config('app.timezone');

    $courseTitle = $quiz?->course?->title ?? null;
    $courseCode = $quiz?->course?->code ?? null;
    $courseLabel = trim(($courseCode ? $courseCode.' · ' : '').($courseTitle ?? ''));

    $assignmentTitle = $quiz?->title ?? __('Assignment');
    $dueAt = $quiz?->due_at;

    $assignmentDueCountdown = $quiz
        ? \App\Support\AssignmentDueCountdown::resolve($quiz, null, $examSession)
        : null;
@endphp

<script src="https://cdn.tiny.cloud/1/ps3oq2yzvy43l968b2wgxtcegt0exfvjwv0hak1zqrkqszje/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

<div class="as-page">

    {{-- Header --}}
    <header class="as-header">
        <div class="as-header-inner">
            <div class="as-header-left">
                <a href="{{ route('student.assignments.index') }}" class="as-back-btn" title="{{ __('Back to assignments') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span class="sr-only">{{ __('Back to assignments') }}</span>
                </a>

                <div class="as-title-wrap">
                    <div class="as-kicker">
                        <span>{{ __('Assignment') }}</span>
                        @if ($courseLabel !== '')
                            <small>{{ $courseLabel }}</small>
                        @endif
                    </div>

                    <h1 id="exam-title">{{ $assignmentTitle }}</h1>
                    <p id="exam-subtitle" class="hidden"></p>
                </div>
            </div>

            <div class="as-header-actions">
                @if ($assignmentDueCountdown)
                    <div class="as-due-pill">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <span
                            class="qs-std-dash-countdown !mt-0 inline text-inherit"
                            data-qs-countdown
                            data-qs-countdown-ends="{{ $assignmentDueCountdown['ends_at'] }}"
                            data-qs-countdown-prefix="{{ $assignmentDueCountdown['prefix'] }}"
                        >
                            <span class="qs-std-dash-countdown__prefix">{{ $assignmentDueCountdown['prefix'] }}</span>
                            <span class="qs-std-dash-countdown__time tabular-nums"></span>
                        </span>
                    </div>
                @elseif ($dueAt)
                    <div class="as-due-pill">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <span>{{ __('Due') }} {{ \Carbon\Carbon::parse($dueAt)->timezone($tz)->format('M j, Y') }}</span>
                    </div>
                @endif

                <button type="button" id="btn-submit" class="as-primary-btn">
                    {{ __('Submit Assignment') }}
                </button>
            </div>
        </div>
    </header>

    {{-- Main --}}
    <main class="as-shell">

        {{-- LEFT: Question + status --}}
        <aside class="as-left-panel">
            <section id="assignment-coursework-panel" class="as-card as-question-card" aria-labelledby="as-question-heading">
                <div class="as-card-head">
                    <p class="as-card-label">{{ __('Question') }}</p>
                    <h2 id="as-question-heading">{{ __('Read while writing') }}</h2>
                </div>

                @if ($quiz && filled($quiz->description))
                    <div class="as-question-instructions">
                        <p class="as-question-instructions-label">{{ __('Instructions') }}</p>
                        <div class="as-question-instructions-body">{{ $quiz->description }}</div>
                    </div>
                @endif

                <div id="assignment-question-slot" class="as-question-slot">
                    <p class="as-question-loading">{{ __('Loading question…') }}</p>
                </div>
            </section>

            <section class="as-card">
                <div class="as-mini-head">
                    <h3>{{ __('Work Status') }}</h3>
                    <span id="assignment-status-line" class="as-open-pill">
                        @if ($examSession->status === 'submitted')
                            {{ $examSession->submitted_late ? __('Submitted late') : __('Submitted') }}
                        @else
                            {{ __('In progress') }}
                        @endif
                    </span>
                </div>

                <div class="as-status-list">
                    @if ($dueAt)
                        <div>
                            <span>{{ __('Due date') }}</span>
                            <strong>{{ \Carbon\Carbon::parse($dueAt)->timezone($tz)->format('M j, Y') }}</strong>
                        </div>
                    @endif

                    <div>
                        <span>{{ __('Autosave') }}</span>
                        <strong id="save-indicator" aria-live="polite">{{ __('Ready') }}</strong>
                    </div>
                </div>

                <p id="assignment-grade-line" class="as-grade-line"></p>
            </section>

            @if ($assignmentAllowsFiles ?? false)
                <section class="as-card as-upload-card" id="assignment-file-upload-slot" aria-labelledby="as-upload-heading">
                    <div class="as-upload-head">
                        <div>
                            <h3 id="as-upload-heading">{{ __('Supporting File') }}</h3>
                            <p>{{ __('Attach before you submit if your assignment requires it.') }}</p>
                        </div>

                        <span>
                            {{ ($assignmentAttachmentRequired ?? false) ? __('Required') : __('Optional') }}
                        </span>
                    </div>

                    <label class="as-upload-box">
                        <span class="as-upload-icon" aria-hidden="true">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </span>

                        <span class="as-upload-text">
                            <strong>{{ __('Click to upload file') }}</strong>
                            <small>{{ __('PDF, DOCX, JPG, PNG, or ZIP accepted') }}</small>
                        </span>

                        <input type="file" id="assignment-file-input" disabled />
                    </label>

                    <p id="assignment-file-status" class="as-upload-status"></p>
                </section>
            @endif

            @if ($assignmentClipboardBlock ?? false)
                <section class="as-card as-card--notice" aria-label="{{ __('Clipboard policy') }}">
                    <div class="as-notice">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span>{{ __('Copy and paste is disabled in the answer box.') }}</span>
                    </div>
                </section>
            @endif
        </aside>

        {{-- RIGHT: Editor (sticky on desktop) --}}
        <section class="as-right-panel">
            <div class="as-right-stack">

                <div id="exam-banner" class="hidden as-banner" role="status"></div>

                <section class="as-card as-editor-card" aria-labelledby="as-editor-heading">
                    <div class="as-editor-head">
                        <h2 id="as-editor-heading">{{ __('Your Response') }}</h2>
                        <p>{{ __('Write your answer below. Your work saves automatically.') }}</p>
                    </div>

                    <div class="as-editor-host">
                        <nav id="question-nav" class="sr-only" aria-hidden="true"></nav>
                        <div id="exam-progress-label" class="sr-only" aria-hidden="true"></div>
                        <div id="exam-current-q-label" class="sr-only" aria-hidden="true"></div>
                        <p id="exam-current-q-type" class="sr-only" aria-hidden="true"></p>
                        <div id="exam-progress-fill" class="sr-only" aria-hidden="true"></div>

                        <div id="exam-workspace" class="as-workspace">
                            <div id="exam-main">
                                <p id="exam-loading">{{ __('Loading assignment…') }}</p>
                                <div id="question-container" class="hidden"></div>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </section>
    </main>

    {{-- Mobile dock --}}
    <div class="as-mobile-dock" aria-label="{{ __('Submit actions') }}">
        <span id="save-indicator-dock" class="as-mobile-save" aria-live="polite"></span>
        <button type="button" class="as-mobile-submit" data-submit-mirror>
            {{ __('Submit Assignment') }}
        </button>
    </div>

    <aside id="proctoring-live-aside" class="hidden" aria-hidden="true">
        <video id="proctoring-video" playsinline muted autoplay></video>
        <canvas id="proctoring-face-canvas" aria-hidden="true"></canvas>
    </aside>

    @include('student.exam.partials.take-overlays', ['assignmentTake' => true])
</div>
