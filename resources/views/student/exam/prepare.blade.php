@extends('layouts.exam-entry')

@section('title', __('Exam entry').' — '.config('app.name', 'QuizSnap'))

@section('content')
    <div id="qs-exam-prepare-root" class="flex w-full max-w-xl shrink-0 flex-col items-center text-center sm:max-w-2xl">
    <div id="exam-prepare-inner" class="flex w-full min-h-0 flex-col items-center py-1">
            @if ($entryBlocked)
                <div class="mb-6 w-full max-w-lg rounded-xl border border-qs-danger/35 bg-qs-danger-soft px-4 py-3 text-center text-sm text-qs-danger">
                    {{ __('Exam entry is temporarily unavailable. Please try again later or contact support.') }}
                </div>
            @endif

            <div id="exam-prepare-surface" class="flex h-fit w-full max-w-lg shrink-0 flex-col items-center gap-6 px-3 py-8 text-center sm:max-w-xl sm:px-5">
                <section id="panel-rules" class="flex w-full flex-col items-center gap-6">
                    <div class="qs-surface w-full max-w-md rounded-2xl p-5 text-left shadow-sm sm:max-w-lg sm:p-6">
                        @include('student.exam.partials.dos-and-donts-body')
                    </div>
                    <label class="flex max-w-md cursor-pointer items-start justify-center gap-3 text-left text-sm text-qs-text sm:items-center sm:text-center">
                        <input id="chk-rules-agree" type="checkbox" class="mt-1 h-4 w-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent sm:mt-0" @if ($entryBlocked) disabled @endif />
                        <span>{{ ($isAssignment ?? false)
                            ? __('I confirm I will follow those dos and don’ts and submit my own work in the answer fields.')
                            : __('I confirm I will follow those dos and don’ts and understand that my attempt may be auto-submitted or ended under my institution’s proctoring policy.') }}</span>
                    </label>
                    <button type="button" id="btn-rules-next" class="qs-btn-primary" disabled @if ($entryBlocked) disabled @endif>
                        {{ __('Continue') }}
                    </button>
                </section>

                @unless ($isAssignment ?? false)
                <section id="panel-permissions" class="hidden !hidden flex w-full flex-col items-center">
                    <h3 class="text-lg font-semibold text-qs-text">{{ __('Camera & microphone') }}</h3>
                    <p class="mt-2 max-w-sm text-xs leading-snug text-qs-muted sm:text-sm">{{ __('Allow each once. Streams stop right after confirmation.') }}</p>
                    <div class="mt-5 flex w-full max-w-md flex-col items-center gap-3">
                        <div class="flex w-full items-center gap-3 rounded-xl border border-qs-soft bg-qs-soft/35 px-4 py-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-qs-bg text-qs-primary shadow-sm" aria-hidden="true">
                                <i class="fa-solid fa-video text-lg"></i>
                            </span>
                            <div class="min-w-0 flex-1 text-left">
                                <p class="text-sm font-semibold text-qs-text">{{ __('Camera') }}</p>
                                <p id="perm-camera-msg" class="mt-0.5 text-xs text-qs-muted">{{ __('Not granted yet') }}</p>
                            </div>
                            <button type="button" id="btn-perm-camera" class="qs-btn-secondary shrink-0 text-sm" @if ($entryBlocked) disabled @endif>{{ __('Allow') }}</button>
                        </div>
                        <div class="flex w-full items-center gap-3 rounded-xl border border-qs-soft bg-qs-soft/35 px-4 py-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-qs-bg text-qs-primary shadow-sm" aria-hidden="true">
                                <i class="fa-solid fa-microphone text-lg"></i>
                            </span>
                            <div class="min-w-0 flex-1 text-left">
                                <p class="text-sm font-semibold text-qs-text">{{ __('Microphone') }}</p>
                                <p id="perm-mic-msg" class="mt-0.5 text-xs text-qs-muted">{{ __('Not granted yet') }}</p>
                            </div>
                            <button type="button" id="btn-perm-mic" class="qs-btn-secondary shrink-0 text-sm" @if ($entryBlocked) disabled @endif>{{ __('Allow') }}</button>
                        </div>
                    </div>
                    <p id="perm-hint" class="mt-3 max-w-md text-center text-xs text-qs-muted" role="status"></p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <button type="button" id="btn-permissions-back" class="qs-btn-secondary text-sm">{{ __('Back') }}</button>
                        <button type="button" id="btn-permissions-next" class="qs-btn-primary text-sm" disabled @if ($entryBlocked) disabled @endif>{{ __('Continue') }}</button>
                    </div>
                </section>
                @endunless

                <section id="panel-overview" class="hidden !hidden flex w-full flex-col items-center">
                    <h3 class="text-lg font-semibold text-qs-text">{{ ($isAssignment ?? false) ? __('Assignment overview') : __('Exam overview') }}</h3>
                    <dl class="mt-4 w-full max-w-md space-y-3 text-sm">
                        <div class="flex flex-col items-center gap-1 border-b border-qs-soft pb-3 text-center">
                            <dt class="text-qs-muted">{{ __('Course') }}</dt>
                            <dd class="font-medium text-qs-text">{{ $quiz->course?->code }} — {{ $quiz->course?->title }}</dd>
                        </div>
                        @if ($quiz->due_at)
                            <div class="flex flex-col items-center gap-1 border-b border-qs-soft pb-3 text-center">
                                <dt class="text-qs-muted">{{ __('Due') }}</dt>
                                <dd class="font-medium text-qs-text">{{ $quiz->due_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                            </div>
                        @endif
                        <div class="flex flex-col items-center gap-1 border-b border-qs-soft pb-3 text-center">
                            <dt class="text-qs-muted">{{ ($isAssignment ?? false) ? __('Time budget (minutes)') : __('Duration') }}</dt>
                            <dd class="font-medium text-qs-text">{{ $quiz->duration_minutes }} {{ __('minutes') }}</dd>
                        </div>
                        @if ($quiz->start_time || $quiz->end_time)
                            @php
                                $tz = config('app.timezone');
                                $fmt = static fn ($t) => $t ? $t->timezone($tz)->format('Y-m-d H:i') : null;
                                $opens = $fmt($quiz->start_time);
                                $closes = $fmt($quiz->end_time);
                            @endphp
                            <div class="flex flex-col items-center gap-2 border-b border-qs-soft pb-3 text-center text-sm">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('When you can take this exam') }}</dt>
                                <dd class="w-full space-y-1.5 text-qs-text">
                                    @if ($opens)
                                        <p class="text-sm"><span class="text-qs-muted">{{ __('Opens') }}</span> · {{ $opens }}</p>
                                    @endif
                                    @if ($closes)
                                        <p class="text-sm"><span class="text-qs-muted">{{ __('Closes') }}</span> · {{ $closes }}</p>
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                    @if ($snapshotRequired)
                        <p class="mt-2 max-w-md text-center text-sm text-qs-muted">{{ __('Before starting, we will take a verification photo and keep camera monitoring active during the exam, according to your school’s proctoring settings.') }}</p>
                    @endif
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <button type="button" id="btn-overview-back" class="qs-btn-secondary text-sm">{{ __('Back') }}</button>
                        <button type="button" id="btn-overview-next" class="qs-btn-primary text-sm" @if ($entryBlocked) disabled @endif>
                            {{ __('Continue') }}
                        </button>
                    </div>
                </section>

                @if ($snapshotRequired)
                    <section id="panel-snapshot" class="hidden !hidden flex w-full flex-col items-center pb-0">
                        <div
                            id="snap-main-card"
                            class="qs-surface flex w-full max-w-md flex-col items-center overflow-hidden p-4 text-center shadow-sm [contain:layout] transform-gpu sm:max-w-lg sm:p-5"
                        >
                            <h3 class="text-lg font-semibold leading-tight text-qs-text">{{ __('Verification photo') }}</h3>
                            <p class="mt-2 max-w-sm text-[11px] leading-snug text-qs-muted sm:text-xs">{{ __('Follow the mesh. One photo saves when verified — not automated ID matching.') }}</p>

                            <div id="snap-stage" class="mt-5 w-full shrink-0">
                                <div id="snap-column" class="mx-auto flex w-full max-w-[18rem] shrink-0 flex-col items-stretch [contain:layout]">
                                    <div
                                        id="snap-preview-wrap"
                                        class="relative isolate hidden w-full shrink-0 overflow-hidden rounded-xl border-2 border-qs-soft bg-black shadow-inner [transform:translateZ(0)]"
                                    >
                                        <div class="pointer-events-none aspect-square w-full shrink-0" aria-hidden="true"></div>
                                        <video
                                            id="snap-video"
                                            class="absolute inset-0 z-0 h-full w-full object-cover object-center [transform:translateZ(0)]"
                                            autoplay
                                            muted
                                            playsinline
                                        ></video>
                                        <canvas id="snap-overlay" class="pointer-events-none absolute inset-0 z-[1] h-full w-full [transform:translateZ(0)]" aria-hidden="true"></canvas>
                                        <div id="snap-verified-badge" class="pointer-events-none absolute inset-0 z-10 hidden flex items-center justify-center bg-emerald-950/55 p-4 text-center backdrop-blur-[2px]">
                                            <div class="flex max-w-[min(90%,15rem)] flex-col items-center gap-1.5 rounded-2xl border border-emerald-400/55 bg-emerald-950/90 px-3 py-3 shadow-xl sm:max-w-[16rem]">
                                                <span class="text-balance break-words text-lg font-bold leading-tight tracking-wide text-emerald-50">{{ __('Verified') }}</span>
                                                <span class="text-balance break-words text-[11px] font-medium leading-relaxed text-emerald-100/95">{{ __('Saving your verification photo…') }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="my-3 w-full shrink-0 border-t border-qs-soft" aria-hidden="true"></div>

                                    <div
                                        id="snap-hud"
                                        class="pointer-events-none hidden flex h-[5.75rem] w-full shrink-0 flex-col justify-center sm:h-24"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        <div id="snap-hud-card" class="flex h-full w-full flex-col justify-center rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-center text-red-950 [transform:translateZ(0)]">
                                            <p id="snap-hud-primary" class="line-clamp-2 text-sm font-bold leading-snug text-inherit"></p>
                                            <p id="snap-hud-secondary" class="mt-1 line-clamp-2 text-[11px] font-medium leading-snug text-inherit opacity-85"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex w-full shrink-0 flex-wrap items-center justify-center gap-2 border-t border-qs-soft pt-4">
                                <button type="button" id="snap-retry-camera" class="qs-btn-secondary text-sm hidden">{{ __('Try camera again') }}</button>
                                <button type="button" id="snap-capture" class="qs-btn-primary text-sm hidden">{{ __('Capture photo') }}</button>
                                <button type="button" id="snap-retake" class="qs-btn-secondary text-sm hidden">{{ __('Retake') }}</button>
                            </div>
                            <p id="snap-status" class="mt-2 w-full shrink-0 text-center text-[11px] leading-tight text-qs-muted empty:hidden sm:text-xs" role="status"></p>
                        </div>
                        <canvas id="snap-canvas" class="hidden"></canvas>
                    </section>
                @endif

                <section id="panel-start" class="hidden !hidden flex w-full flex-col items-center">
                    <h3 class="text-lg font-semibold text-qs-text">{{ ($isAssignment ?? false) ? __('Ready to work') : __('Ready to begin') }}</h3>
                    <p class="mt-2 max-w-md text-sm text-qs-muted">{{ ($isAssignment ?? false)
                        ? __('When you start, you can type and save your answers. There is no live invigilation camera for this coursework by default.')
                        : __('When you start, the exam timer may begin immediately according to your institution’s rules.') }}</p>
                    <p id="start-message" class="mt-3 max-w-md text-sm text-qs-muted" role="status"></p>
                    <button type="button" id="btn-start-exam" class="qs-btn-primary mt-6" @if ($entryBlocked) disabled @endif>
                        {{ ($isAssignment ?? false) ? __('Start assignment') : __('Start exam now') }}
                    </button>
                </section>
            </div>
    </div>
    </div>
@endsection

    @push('scripts')
        <script type="module">
            const examId = {{ (int) $quiz->id }};
            const snapshotRequired = @json($snapshotRequired);
            const entryBlocked = @json($entryBlocked);
            const isAssignment = @json($isAssignment ?? false);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const STEP_KEY = 'qs_prepare_' + examId + '_step';
            const RULES_KEY = 'qs_prepare_' + examId + '_rules_ok';

            const FACE_OVAL_IDX = [
                10, 338, 297, 332, 284, 251, 389, 356, 454, 323, 361, 288, 397, 365, 379, 378, 400, 377, 152, 148, 176, 149, 150, 136, 172, 58, 132, 93, 234, 127, 162, 21, 54, 103, 67, 109,
            ];

            let currentStep = 'rules';

            const panelRules = document.getElementById('panel-rules');
            const panelPermissions = document.getElementById('panel-permissions');
            const panelOverview = document.getElementById('panel-overview');
            const panelSnapshot = document.getElementById('panel-snapshot');
            const panelStart = document.getElementById('panel-start');

            let verificationBlob = null;
            let snapStream = null;
            let faceLandmarker = null;
            let snapDetectRaf = null;
            let snapHumanFrames = 0;
            let snapMpBypass = false;
            let snapCocoCheckInFlight = false;
            let snapFinishing = false;

            const SNAP_FRAMES_NEEDED = 12;

            let permCameraOk = false;
            let permMicOk = false;

            const msgPermOk = '{{ __('Access granted.') }}';
            const msgPermDenied = '{{ __('Access was denied or unavailable. Check this site’s permissions in your browser settings and try again.') }}';
            const msgSecureContext = '{{ __('Camera and microphone work best over HTTPS (or on localhost).') }}';

            function persistStep(step) {
                try {
                    sessionStorage.setItem(STEP_KEY, step);
                } catch (_) {
                    /* ignore */
                }
            }

            function getPersistedStep() {
                try {
                    return sessionStorage.getItem(STEP_KEY);
                } catch (_) {
                    return null;
                }
            }

            function stepIsAllowed(step) {
                if (!['rules', 'permissions', 'overview', 'snapshot', 'start'].includes(step)) return false;
                if (step === 'permissions' && isAssignment) return false;
                if (step === 'snapshot' && !snapshotRequired) return false;
                return true;
            }

            function resolveInitialStep() {
                const raw = getPersistedStep();
                let s = raw && stepIsAllowed(raw) ? raw : 'rules';
                let rulesOk = false;
                try {
                    rulesOk = sessionStorage.getItem(RULES_KEY) === '1';
                } catch (_) {
                    rulesOk = false;
                }
                if (['overview', 'permissions', 'snapshot', 'start'].includes(s) && !rulesOk) {
                    s = 'rules';
                }
                if (s === 'start' && snapshotRequired) {
                    s = 'snapshot';
                }
                return s;
            }

            function setPreparePanelVisibility(el, visible) {
                if (!el) return;
                const hide = !visible;
                el.classList.toggle('hidden', hide);
                el.classList.toggle('!hidden', hide);
                if (hide) {
                    el.setAttribute('aria-hidden', 'true');
                } else {
                    el.removeAttribute('aria-hidden');
                }
            }

            function showPanel(name) {
                setPreparePanelVisibility(panelRules, name === 'rules');
                setPreparePanelVisibility(panelPermissions, name === 'permissions');
                setPreparePanelVisibility(panelOverview, name === 'overview');
                setPreparePanelVisibility(panelSnapshot, name === 'snapshot');
                setPreparePanelVisibility(panelStart, name === 'start');

                const surface = document.getElementById('exam-prepare-surface');
                const snap = name === 'snapshot';
                if (surface) {
                    if (snap) {
                        surface.classList.remove('gap-6', 'px-3', 'py-8', 'sm:px-5');
                        surface.classList.add('gap-2', 'px-1', 'py-3');
                    } else {
                        surface.classList.remove('gap-2', 'px-1', 'py-3', 'pt-3', 'pb-0', 'sm:pt-4', 'sm:px-4', 'sm:pb-0', 'p-6', 'p-3', 'sm:p-4');
                        surface.classList.add('gap-6', 'px-3', 'py-8', 'sm:px-5');
                    }
                }
            }

            function updatePermissionsContinue() {
                const btn = document.getElementById('btn-permissions-next');
                if (!btn) return;
                btn.disabled = entryBlocked || !permCameraOk || !permMicOk;
            }

            function stylePermMessage(el, ok) {
                if (!el) return;
                el.classList.toggle('text-emerald-700', ok);
                el.classList.toggle('text-qs-muted', !ok);
            }

            async function parseResponseBody(res) {
                const ct = (res.headers.get('content-type') || '').toLowerCase();
                if (ct.includes('application/json')) {
                    try {
                        return await res.json();
                    } catch {
                        return {};
                    }
                }
                const text = await res.text().catch(() => '');
                const trimmed = text.trim();
                if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
                    try {
                        return JSON.parse(trimmed);
                    } catch {
                        /* ignore */
                    }
                }
                if (trimmed.length > 0) {
                    return { message: trimmed.slice(0, 800) };
                }

                return {};
            }

            function firstValidationMessage(errors) {
                if (!errors || typeof errors !== 'object') {
                    return '';
                }
                for (const v of Object.values(errors)) {
                    if (Array.isArray(v) && v.length && typeof v[0] === 'string') {
                        return v[0];
                    }
                    if (typeof v === 'string') {
                        return v;
                    }
                }

                return '';
            }

            function friendlyStartError(status, body) {
                let rawMsg = typeof body?.message === 'string' ? body.message.trim() : '';
                if (rawMsg.includes('<!DOCTYPE') || rawMsg.includes('<html') || rawMsg.includes('<HTML')) {
                    rawMsg = '';
                }
                const fromFields = firstValidationMessage(body?.errors);
                const useful =
                    fromFields ||
                    (rawMsg && rawMsg !== 'The given data was invalid.' && rawMsg !== 'The given data was invalid' ? rawMsg : '');

                if (status === 500 || status === 502 || status === 504) {
                    return useful || '{{ __('The server had a problem starting your exam. Wait a moment, refresh the page, and try again.') }}';
                }
                if (status === 503 || body?.status === 'service_unavailable') {
                    return '{{ __('Verification is temporarily unavailable. Please try again shortly.') }}';
                }
                if (status === 419) {
                    return '{{ __('Your session expired. Refresh this page and try starting again.') }}';
                }
                if (status === 409) {
                    return '{{ __('Another start request is in progress. Wait a moment and try again.') }}';
                }
                if (status === 429) {
                    return '{{ __('Too many attempts. Please wait before trying again.') }}';
                }
                if (status === 423) {
                    return useful || '{{ __('Exam entry is temporarily unavailable. Please try again later.') }}';
                }
                if (status === 403) {
                    return useful || '{{ __('You are not allowed to start this exam.') }}';
                }
                if (typeof useful === 'string' && useful.length > 0) {
                    return useful;
                }
                if (status === 422) {
                    return '{{ __('We could not start the exam with the details provided. Review the steps or contact support.') }}';
                }
                if (status === 400) {
                    return '{{ __('The start request was not accepted. Refresh the page and try again.') }}';
                }
                if (status === 401) {
                    return '{{ __('You are no longer signed in. Sign in again, then return to this page.') }}';
                }
                return '{{ __('Something went wrong. Please try again.') }}';
            }

            async function postJson(url, payload) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload ?? {}),
                });
                const body = await parseResponseBody(res);
                return { res, body };
            }

            async function postStartMultipart(extraFields) {
                const fd = new FormData();
                fd.append('exam_id', String(examId));
                if (verificationBlob) {
                    fd.append('verification_snapshot', verificationBlob, 'verification.jpg');
                }
                if (extraFields?.hardware_concurrency != null) {
                    fd.append('hardware_concurrency', String(extraFields.hardware_concurrency));
                }
                const res = await fetch(@json(route('exam-sessions.start')), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: fd,
                });
                const body = await parseResponseBody(res);
                return { res, body };
            }

            if (isAssignment) {
                permCameraOk = true;
                permMicOk = true;
            }

            const chkRules = document.getElementById('chk-rules-agree');
            const btnRulesNext = document.getElementById('btn-rules-next');
            chkRules?.addEventListener('change', () => {
                if (btnRulesNext) {
                    btnRulesNext.disabled = entryBlocked || !chkRules.checked;
                }
            });
            if (btnRulesNext && chkRules && !entryBlocked) {
                btnRulesNext.disabled = !chkRules.checked;
            }

            document.getElementById('btn-rules-next')?.addEventListener('click', () => {
                if (entryBlocked) return;
                if (!chkRules?.checked) return;
                try {
                    sessionStorage.setItem(RULES_KEY, '1');
                } catch (_) {
                    /* ignore */
                }
                if (isAssignment) {
                    void navigateToStep('overview');
                    return;
                }
                void navigateToStep('permissions');
            });

            const permHintEl = document.getElementById('perm-hint');
            if (permHintEl && typeof window.isSecureContext !== 'undefined' && !window.isSecureContext) {
                permHintEl.textContent = msgSecureContext;
            }

            document.getElementById('btn-permissions-back')?.addEventListener('click', () => {
                void navigateToStep('rules');
            });

            document.getElementById('btn-permissions-next')?.addEventListener('click', () => {
                if (entryBlocked) return;
                if (!permCameraOk || !permMicOk) return;
                void navigateToStep('overview');
            });

            document.getElementById('btn-perm-camera')?.addEventListener('click', async () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('perm-camera-msg');
                try {
                    if (!navigator.mediaDevices?.getUserMedia) {
                        throw new Error('no-gum');
                    }
                    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    stream.getTracks().forEach((t) => t.stop());
                    permCameraOk = true;
                    if (msgEl) msgEl.textContent = msgPermOk;
                    stylePermMessage(msgEl, true);
                    document.getElementById('btn-perm-camera')?.setAttribute('disabled', 'disabled');
                } catch {
                    permCameraOk = false;
                    if (msgEl) {
                        msgEl.textContent = msgPermDenied;
                        stylePermMessage(msgEl, false);
                    }
                }
                updatePermissionsContinue();
            });

            document.getElementById('btn-perm-mic')?.addEventListener('click', async () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('perm-mic-msg');
                try {
                    if (!navigator.mediaDevices?.getUserMedia) {
                        throw new Error('no-gum');
                    }
                    const stream = await navigator.mediaDevices.getUserMedia({ video: false, audio: true });
                    stream.getTracks().forEach((t) => t.stop());
                    permMicOk = true;
                    if (msgEl) msgEl.textContent = msgPermOk;
                    stylePermMessage(msgEl, true);
                    document.getElementById('btn-perm-mic')?.setAttribute('disabled', 'disabled');
                } catch {
                    permMicOk = false;
                    if (msgEl) {
                        msgEl.textContent = msgPermDenied;
                        stylePermMessage(msgEl, false);
                    }
                }
                updatePermissionsContinue();
            });

            document.getElementById('btn-overview-back')?.addEventListener('click', () => {
                void navigateToStep(isAssignment ? 'rules' : 'permissions');
            });

            document.getElementById('btn-overview-next')?.addEventListener('click', () => {
                if (entryBlocked) return;
                void navigateToStep(snapshotRequired ? 'snapshot' : 'start');
            });

            const snapVideo = document.getElementById('snap-video');
            const snapCanvas = document.getElementById('snap-canvas');
            const snapWrap = document.getElementById('snap-preview-wrap');
            const snapStatus = document.getElementById('snap-status');
            const snapOverlay = document.getElementById('snap-overlay');
            const snapHud = document.getElementById('snap-hud');
            const snapHudCard = document.getElementById('snap-hud-card');
            const snapHudPrimary = document.getElementById('snap-hud-primary');
            const snapHudSecondary = document.getElementById('snap-hud-secondary');
            const snapVerifiedBadge = document.getElementById('snap-verified-badge');
            const btnSnapRetry = document.getElementById('snap-retry-camera');
            const btnSnapCapture = document.getElementById('snap-capture');
            const btnSnapRetake = document.getElementById('snap-retake');

            let snapAutoTimer = null;
            let lastSnapLandmarks = null;
            let lastSnapMeshHint = 'none';

            async function stopSnapCamera() {
                if (snapDetectRaf !== null) {
                    cancelAnimationFrame(snapDetectRaf);
                    snapDetectRaf = null;
                }
                snapHumanFrames = 0;
                snapCocoCheckInFlight = false;
                if (snapStream) {
                    snapStream.getTracks().forEach((t) => t.stop());
                    snapStream = null;
                }
                if (snapVideo) snapVideo.srcObject = null;
                lastSnapLandmarks = null;
                lastSnapMeshHint = 'none';
                if (snapHud) snapHud.classList.add('hidden');
                if (snapOverlay) {
                    const octx = snapOverlay.getContext('2d');
                    if (octx && snapOverlay.width) octx.clearRect(0, 0, snapOverlay.width, snapOverlay.height);
                }
                snapVerifiedBadge?.classList.add('hidden');
            }

            function bboxFromLandmarks(lm) {
                if (!lm || lm.length < 100) return null;
                let minX = 1;
                let maxX = 0;
                let minY = 1;
                let maxY = 0;
                for (const p of lm) {
                    minX = Math.min(minX, p.x);
                    maxX = Math.max(maxX, p.x);
                    minY = Math.min(minY, p.y);
                    maxY = Math.max(maxY, p.y);
                }
                return { minX, maxX, minY, maxY, bw: maxX - minX, bh: maxY - minY, cx: (minX + maxX) / 2, cy: (minY + maxY) / 2 };
            }

            /** Normalized face bbox (0–1). Stricter centre band so verification only counts when the face sits in the middle of the square preview. */
            function framingFromBbox(b) {
                if (!b) return 'none';
                if (b.bw > 0.52 || b.bh > 0.68) return 'too_close';
                if (b.bw < 0.16) return 'too_far';
                if (b.bw < 0.2 || b.bh < 0.22) return 'small';
                const cxLo = 0.42;
                const cxHi = 0.58;
                const cyLo = 0.34;
                const cyHi = 0.56;
                if (b.cx < cxLo || b.cx > cxHi || b.cy < cyLo || b.cy > cyHi) return 'off_center';
                return 'standard';
            }

            function snapFaceAnalysisOk(result) {
                const lms = result?.faceLandmarks;
                if (!Array.isArray(lms) || lms.length === 0) {
                    lastSnapLandmarks = null;
                    lastSnapMeshHint = 'none';
                    return { ok: false, reason: 'none', meshHint: 'none' };
                }
                if (lms.length > 1) {
                    lastSnapLandmarks = null;
                    lastSnapMeshHint = 'multi';
                    return { ok: false, reason: 'multi', meshHint: 'multi' };
                }
                const bbox = bboxFromLandmarks(lms[0]);
                const fr = framingFromBbox(bbox);
                lastSnapLandmarks = lms[0];
                lastSnapMeshHint = fr === 'standard' ? 'good' : fr;
                if (fr === 'too_close') return { ok: false, reason: 'too_close', meshHint: 'too_close' };
                if (fr === 'too_far') return { ok: false, reason: 'too_far', meshHint: 'too_far' };
                if (fr === 'off_center') return { ok: false, reason: 'off_center', meshHint: 'off_center' };
                if (fr === 'small') return { ok: false, reason: 'size', meshHint: 'small' };
                return { ok: true, reason: '', meshHint: 'good' };
            }

            function syncOverlaySize() {
                if (!snapVideo || !snapOverlay) return null;
                const w = snapVideo.clientWidth | 0;
                const h = snapVideo.clientHeight | 0;
                if (w < 2 || h < 2) return null;
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                snapOverlay.width = Math.floor(w * dpr);
                snapOverlay.height = Math.floor(h * dpr);
                snapOverlay.style.width = w + 'px';
                snapOverlay.style.height = h + 'px';
                const ctx = snapOverlay.getContext('2d');
                if (!ctx) return null;
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                return { ctx, w, h };
            }

            function drawSnapOverlay(ana) {
                const pack = syncOverlaySize();
                if (!pack) return;
                const { ctx, w, h } = pack;
                ctx.clearRect(0, 0, w, h);
                if (ana.meshHint === 'multi') {
                    return;
                }
                const lm = lastSnapLandmarks;
                if (!lm) return;
                const stroke =
                    ana.meshHint === 'good'
                        ? 'rgba(52, 211, 153, 0.88)'
                        : ana.meshHint === 'too_close'
                          ? 'rgba(251, 191, 36, 0.92)'
                          : ana.meshHint === 'too_far'
                            ? 'rgba(96, 165, 250, 0.92)'
                            : 'rgba(248, 113, 113, 0.88)';
                ctx.lineWidth = 1.75;
                ctx.strokeStyle = stroke;
                ctx.beginPath();
                for (let i = 0; i < FACE_OVAL_IDX.length; i += 1) {
                    const a = FACE_OVAL_IDX[i];
                    const b = FACE_OVAL_IDX[(i + 1) % FACE_OVAL_IDX.length];
                    const pa = lm[a];
                    const pb = lm[b];
                    if (!pa || !pb) continue;
                    ctx.moveTo(pa.x * w, pa.y * h);
                    ctx.lineTo(pb.x * w, pb.y * h);
                }
                ctx.stroke();
                const markers = [159, 386, 1, 61, 291];
                ctx.fillStyle = stroke;
                for (const idx of markers) {
                    const p = lm[idx];
                    if (!p) continue;
                    ctx.beginPath();
                    ctx.arc(p.x * w, p.y * h, 2.4, 0, Math.PI * 2);
                    ctx.fill();
                }
            }

            function setSnapHud(ana) {
                if (!snapHud) return;
                const lines = {
                    none: ['{{ __('No face detected yet') }}', '{{ __('Sit so your face is visible in the square frame.') }}'],
                    multi: [
                        '{{ __('More than one face') }}',
                        '{{ __('Only you may be on camera. Ask anyone else to step away, then wait a moment.') }}',
                    ],
                    too_close: ['{{ __('Too close') }}', '{{ __('Move back a little so your head fits comfortably inside the frame.') }}'],
                    too_far: ['{{ __('Too far') }}', '{{ __('Move a bit closer so your face fills more of the frame.') }}'],
                    off_center: ['{{ __('Centre yourself') }}', '{{ __('Shift so your face sits in the middle of the square.') }}'],
                    small: ['{{ __('Face too small') }}', '{{ __('Come slightly closer.') }}'],
                    good: ['{{ __('Good distance') }}', '{{ __('Hold steady — checks run on this device only.') }}'],
                };
                const pair = lines[ana.meshHint] || lines.none;
                snapHud.classList.remove('hidden');
                if (snapHudCard) {
                    const base =
                        'flex h-full w-full flex-col justify-center rounded-xl border px-3 py-2 text-center [transform:translateZ(0)] ';
                    if (ana.meshHint === 'good') {
                        snapHudCard.className =
                            base + 'border-emerald-300 bg-emerald-50 text-emerald-950';
                    } else {
                        snapHudCard.className = base + 'border-red-200 bg-red-50 text-red-950';
                    }
                }
                if (snapHudPrimary) snapHudPrimary.textContent = pair[0];
                if (snapHudSecondary) snapHudSecondary.textContent = pair[1];
                if (snapStatus) snapStatus.textContent = '';
            }

            async function runSnapTensorFlowPersonGate() {
                if (typeof window.qsExamPrepareDetectPerson !== 'function') return true;
                return window.qsExamPrepareDetectPerson(snapVideo);
            }

            async function destroyFaceLandmarker() {
                if (faceLandmarker?.close) {
                    try {
                        await faceLandmarker.close();
                    } catch (_) {
                        /* ignore */
                    }
                }
                faceLandmarker = null;
            }

            async function initSnapFaceLandmarker() {
                if (faceLandmarker) {
                    return true;
                }
                const mpPkg = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.35';
                const tryCreate = async (delegate) => {
                    const { FilesetResolver, FaceLandmarker } = await import(`${mpPkg}/+esm`);
                    const vision = await FilesetResolver.forVisionTasks(`${mpPkg}/wasm`);
                    return FaceLandmarker.createFromOptions(vision, {
                        baseOptions: {
                            modelAssetPath:
                                'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
                            delegate,
                        },
                        runningMode: 'VIDEO',
                        outputFaceBlendshapes: false,
                        numFaces: 2,
                    });
                };
                try {
                    faceLandmarker = await tryCreate('GPU');
                    return true;
                } catch {
                    await destroyFaceLandmarker();
                    try {
                        faceLandmarker = await tryCreate('CPU');
                        return true;
                    } catch {
                        await destroyFaceLandmarker();
                        return false;
                    }
                }
            }

            function stopSnapPresenceLoop() {
                if (snapDetectRaf !== null) {
                    cancelAnimationFrame(snapDetectRaf);
                    snapDetectRaf = null;
                }
            }

            function startSnapPresenceLoop() {
                stopSnapPresenceLoop();
                const tick = () => {
                    if (!snapStream || !snapVideo || snapVideo.readyState < 2) {
                        snapDetectRaf = requestAnimationFrame(tick);
                        return;
                    }
                    if (!faceLandmarker) {
                        snapDetectRaf = requestAnimationFrame(tick);
                        return;
                    }
                    const result = faceLandmarker.detectForVideo(snapVideo, performance.now());
                    const ana = snapFaceAnalysisOk(result);
                    setSnapHud(ana);
                    drawSnapOverlay(ana);
                    if (ana.reason === 'multi') {
                        snapHumanFrames = 0;
                    } else if (ana.ok) {
                        snapHumanFrames += 1;
                    } else {
                        snapHumanFrames = 0;
                    }
                    if (snapHumanFrames >= SNAP_FRAMES_NEEDED) {
                        stopSnapPresenceLoop();
                        if (snapCocoCheckInFlight) {
                            return;
                        }
                        snapCocoCheckInFlight = true;
                        void (async () => {
                            const personOk = await runSnapTensorFlowPersonGate();
                            snapCocoCheckInFlight = false;
                            if (!personOk) {
                                snapHumanFrames = 0;
                        if (snapStatus) {
                            snapStatus.textContent =
                                '{{ __('Step back so your upper body is visible, then wait.') }}';
                        }
                                startSnapPresenceLoop();
                                return;
                            }
                            if (snapStatus) snapStatus.textContent = '{{ __('Verified — saving your photo.') }}';
                            snapHud?.classList.add('hidden');
                            snapVerifiedBadge?.classList.remove('hidden');
                            window.setTimeout(() => void finalizeVerificationSnapshot(), 1200);
                        })();
                        return;
                    }
                    snapDetectRaf = requestAnimationFrame(tick);
                };
                snapDetectRaf = requestAnimationFrame(tick);
            }

            function scheduleSnapAutoStart() {
                if (!snapshotRequired || !panelSnapshot) return;
                clearTimeout(snapAutoTimer);
                snapAutoTimer = window.setTimeout(() => void attemptStartSnapCamera(), 200);
            }

            async function navigateToStep(step) {
                if (currentStep === 'snapshot' && step !== 'snapshot') {
                    await stopSnapCamera();
                }
                currentStep = step;
                persistStep(step);
                showPanel(step);
                if (step === 'snapshot' && snapshotRequired) {
                    scheduleSnapAutoStart();
                }
            }

            async function finalizeVerificationSnapshot() {
                if (snapFinishing || !snapVideo || snapVideo.readyState < 2) return;
                snapFinishing = true;
                try {
                    const w = snapVideo.videoWidth || 640;
                    const h = snapVideo.videoHeight || 480;
                    const tw = Math.min(720, w);
                    const th = Math.max(1, Math.floor(h * (tw / w)));
                    snapCanvas.width = tw;
                    snapCanvas.height = th;
                    const ctx2 = snapCanvas.getContext('2d');
                    if (!ctx2) return;
                    ctx2.drawImage(snapVideo, 0, 0, tw, th);
                    verificationBlob = await new Promise((resolve) => {
                        snapCanvas.toBlob((b) => resolve(b), 'image/jpeg', 0.88);
                    });
                    await stopSnapCamera();
                    snapVerifiedBadge?.classList.add('hidden');
                    snapHud?.classList.add('hidden');
                    btnSnapRetry?.classList.add('hidden');
                    btnSnapCapture?.classList.add('hidden');
                    btnSnapRetake?.classList.remove('hidden');
                    if (snapStatus) {
                        snapStatus.textContent = '{{ __('Photo saved. Continue to start the exam.') }}';
                    }
                    lastSnapLandmarks = null;
                    await navigateToStep('start');
                } finally {
                    snapFinishing = false;
                }
            }

            async function attemptStartSnapCamera() {
                if (!snapshotRequired || !snapVideo) return;
                if (snapStream && snapVideo.srcObject) return;
                btnSnapRetry?.classList.add('hidden');
                if (snapStatus) snapStatus.textContent = '';
                snapMpBypass = false;
                snapHumanFrames = 0;
                snapCocoCheckInFlight = false;
                btnSnapCapture?.classList.add('hidden');
                snapVerifiedBadge?.classList.add('hidden');
                try {
                    if (!navigator.mediaDevices?.getUserMedia) {
                        throw new Error('no-gum');
                    }
                    snapStream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user' },
                        audio: false,
                    });
                    snapVideo.srcObject = snapStream;
                    snapWrap?.classList.remove('hidden');
                    await snapVideo.play();
                    void syncOverlaySize();
                    const mpOk = await initSnapFaceLandmarker();
                    if (mpOk) {
                        if (snapStatus) {
                            snapStatus.textContent = '{{ __('Camera on — align to the mesh until verified.') }}';
                        }
                        startSnapPresenceLoop();
                    } else {
                        snapMpBypass = true;
                        if (snapStatus) {
                            snapStatus.textContent = '{{ __('Running backup person detection…') }}';
                        }
                        const personOk = await runSnapTensorFlowPersonGate();
                        if (!personOk) {
                            if (snapStatus) {
                                snapStatus.textContent =
                                    '{{ __('We need to see you clearly. Check permissions, then tap Try camera again.') }}';
                            }
                            btnSnapRetry?.classList.remove('hidden');
                            await stopSnapCamera();
                            return;
                        }
                        if (snapStatus) snapStatus.textContent = '{{ __('Verified — saving your photo.') }}';
                        snapHud?.classList.add('hidden');
                        snapVerifiedBadge?.classList.remove('hidden');
                        window.setTimeout(() => void finalizeVerificationSnapshot(), 1200);
                    }
                } catch {
                    if (snapStatus) {
                        snapStatus.textContent =
                            '{{ __('Camera access was denied or is unavailable. Allow camera in your browser settings to continue, or ask your coordinator if this exam does not require a photo.') }}';
                    }
                    btnSnapRetry?.classList.remove('hidden');
                }
            }

            btnSnapRetry?.addEventListener('click', () => void attemptStartSnapCamera());

            window.addEventListener('resize', () => {
                if (currentStep === 'snapshot' && snapStream && lastSnapLandmarks) {
                    drawSnapOverlay({ meshHint: lastSnapMeshHint });
                }
            });

            btnSnapCapture?.addEventListener('click', async () => {
                if (!snapVideo || snapVideo.readyState < 2) return;
                if (faceLandmarker && !snapMpBypass) {
                    const check = faceLandmarker.detectForVideo(snapVideo, performance.now());
                    const ana = snapFaceAnalysisOk(check);
                    if (!ana.ok) {
                        if (snapStatus) {
                            snapStatus.textContent =
                                ana.reason === 'multi'
                                    ? '{{ __('Only one person should be in the photo.') }}'
                                    : '{{ __('Step back into the frame, wait a second, then try capture again.') }}';
                        }
                        return;
                    }
                }
                if (typeof window.qsExamPrepareDetectPerson === 'function') {
                    const ok = await window.qsExamPrepareDetectPerson(snapVideo);
                    if (!ok) {
                        if (snapStatus) {
                            snapStatus.textContent =
                                '{{ __('Quick check did not see a person clearly. Adjust the camera and try again.') }}';
                        }
                        return;
                    }
                }
                await finalizeVerificationSnapshot();
            });

            btnSnapRetake?.addEventListener('click', async () => {
                verificationBlob = null;
                btnSnapRetake?.classList.add('hidden');
                snapWrap?.classList.add('hidden');
                if (snapStatus) snapStatus.textContent = '';
                await navigateToStep('snapshot');
            });

            document.getElementById('btn-start-exam')?.addEventListener('click', async () => {
                if (entryBlocked) return;
                const msgEl = document.getElementById('start-message');
                if (snapshotRequired && !verificationBlob) {
                    if (msgEl) {
                        msgEl.textContent = '{{ __('Capture your verification photo before starting.') }}';
                    }
                    void navigateToStep('snapshot');
                    return;
                }
                if (msgEl) {
                    msgEl.textContent = '{{ __('Starting…') }}';
                }
                const hw = typeof navigator.hardwareConcurrency === 'number' ? navigator.hardwareConcurrency : null;
                let res;
                let body;
                if (snapshotRequired && verificationBlob) {
                    ({ res, body } = await postStartMultipart({ hardware_concurrency: hw }));
                } else {
                    ({ res, body } = await postJson(@json(route('exam-sessions.start')), {
                        exam_id: examId,
                        hardware_concurrency: hw,
                    }));
                }
                if (res.ok && body?.session_id) {
                    try {
                        sessionStorage.removeItem(STEP_KEY);
                        sessionStorage.removeItem(RULES_KEY);
                    } catch (_) {
                        /* ignore */
                    }
                    window.location.href = `/student/exam/${encodeURIComponent(body.session_id)}`;
                    return;
                }
                if (msgEl) {
                    msgEl.textContent = friendlyStartError(res.status, body);
                }
            });

            try {
                if (sessionStorage.getItem(RULES_KEY) === '1' && chkRules) {
                    chkRules.checked = true;
                }
            } catch (_) {
                /* ignore */
            }
            if (btnRulesNext) {
                btnRulesNext.disabled = entryBlocked || !chkRules?.checked;
            }
            void navigateToStep(resolveInitialStep());

            window.addEventListener('beforeunload', () => {
                void stopSnapCamera();
            });
        </script>
    @endpush
