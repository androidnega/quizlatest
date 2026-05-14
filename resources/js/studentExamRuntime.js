import axios from 'axios';
import {
    ProctoringRuntimeEngine,
    fetchProctoringCapability,
} from './proctoringRuntimeEngine';
import { ProctoringEventBatcher } from './proctoringEventBatcher';
import { createProctoringEcho } from './proctoringRealtime';
import { ExamStateEngine } from './examStateEngine';
import {
    queuePendingAnswer,
    clearPendingAnswer,
    clearSessionPending,
    listPendingForSession,
} from './examAnswerOfflineQueue';
import { attachEssayAntiClipboard, ESSAY_CLIPBOARD_WARNING_MESSAGE } from './essayAntiClipboard';
import { attachExamIntegritySurface } from './examIntegritySurface';

const DEBOUNCE_MS = 1600;
const POLL_MS = 12000;
const SAVE_RETRY = 3;
const SUBMIT_RETRIES = 3;
const SUBMIT_PERSIST_INTERVAL_MS = 12000;
/** Max wall-clock time for background submit retries after initial failures. */
const SUBMIT_PERSIST_MAX_MS = 15 * 60 * 1000;
const FULLSCREEN_EXIT_NOTICE_MS = 7000;

function isExamDocumentFullscreen() {
    const d = document;
    return !!(
        d.fullscreenElement ||
        d.webkitFullscreenElement ||
        d.mozFullScreenElement ||
        d.msFullscreenElement
    );
}

/**
 * Best-effort fullscreen for the exam (requires a recent user gesture in most browsers).
 * Tries the document first, then `#exam-app`, with vendor prefixes — improves Safari / Firefox when returning to an attempt.
 */
async function requestExamFullscreen() {
    if (isExamDocumentFullscreen()) {
        return;
    }

    /** @param {Element | null} node */
    async function tryNode(node) {
        if (!node) {
            return;
        }
        const el = /** @type {Element & { webkitRequestFullscreen?: () => void; mozRequestFullScreen?: () => Promise<void> | void; msRequestFullscreen?: () => Promise<void> | void }} */ (
            node
        );
        try {
            if (typeof el.requestFullscreen === 'function') {
                await el.requestFullscreen();
            }
        } catch {
            //
        }
        if (isExamDocumentFullscreen()) {
            return;
        }
        try {
            if (typeof el.webkitRequestFullscreen === 'function') {
                await Promise.resolve(el.webkitRequestFullscreen());
            }
        } catch {
            //
        }
        if (isExamDocumentFullscreen()) {
            return;
        }
        try {
            if (typeof el.mozRequestFullScreen === 'function') {
                await Promise.resolve(el.mozRequestFullScreen());
            }
        } catch {
            //
        }
        if (isExamDocumentFullscreen()) {
            return;
        }
        try {
            if (typeof el.msRequestFullscreen === 'function') {
                await Promise.resolve(el.msRequestFullscreen());
            }
        } catch {
            //
        }
    }

    await tryNode(document.documentElement);
    if (isExamDocumentFullscreen()) {
        return;
    }
    await tryNode(document.getElementById('exam-app'));
}

function fullscreenGateSupported() {
    /** @param {Element | null} node */
    const can = (node) =>
        !!(
            node &&
            (typeof node.requestFullscreen === 'function' ||
                typeof /** @type {Element & { webkitRequestFullscreen?: unknown }} */ (node).webkitRequestFullscreen ===
                    'function' ||
                typeof /** @type {Element & { mozRequestFullScreen?: unknown }} */ (node).mozRequestFullScreen ===
                    'function' ||
                typeof /** @type {Element & { msRequestFullscreen?: unknown }} */ (node).msRequestFullscreen === 'function')
        );
    return can(document.documentElement) || can(document.getElementById('exam-app'));
}

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
}

function setupAxios() {
    const token = meta('csrf-token');
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
}

async function captureVerificationJpegBlob(videoElement) {
    if (!videoElement || videoElement.readyState < 2) {
        return null;
    }
    const sourceWidth = videoElement.videoWidth || 640;
    const sourceHeight = videoElement.videoHeight || 480;
    const targetWidth = Math.min(480, sourceWidth);
    const scale = targetWidth / sourceWidth;
    const targetHeight = Math.max(1, Math.floor(sourceHeight * scale));

    const canvas = document.createElement('canvas');
    canvas.width = targetWidth;
    canvas.height = targetHeight;
    const context = canvas.getContext('2d');
    if (!context) {
        return null;
    }

    context.drawImage(videoElement, 0, 0, targetWidth, targetHeight);

    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.72);
    });
}

async function postVerificationImageOnce(videoElement, sessionIdStr) {
    const storageKey = `qs_verification_image_${sessionIdStr}`;
    try {
        if (typeof sessionStorage !== 'undefined' && sessionStorage.getItem(storageKey)) {
            return;
        }
        const blob = await captureVerificationJpegBlob(videoElement);
        if (!blob) {
            return;
        }
        const fd = new FormData();
        fd.append('snapshot', blob, 'verification.jpg');
        await axios.post(`/exam-sessions/${encodeURIComponent(sessionIdStr)}/verification-image`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (typeof sessionStorage !== 'undefined') {
            sessionStorage.setItem(storageKey, '1');
        }
    } catch {
        //
    }
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

/** @param {string} type */
function questionTypeLabel(type) {
    switch (type) {
        case 'mcq':
            return 'Multiple choice';
        case 'true_false':
            return 'True / false';
        case 'fill_blank':
            return 'Fill in the blank';
        case 'essay':
            return 'Written response';
        default:
            return type;
    }
}

function questionFlagStorageKey(sessionIdStr) {
    return `qs_exam_q_flags_${sessionIdStr}`;
}

/** @returns {Map<number, true>} */
function loadQuestionFlags(sessionIdStr) {
    const m = new Map();
    try {
        const raw = localStorage.getItem(questionFlagStorageKey(sessionIdStr));
        if (!raw) {
            return m;
        }
        const o = JSON.parse(raw);
        if (o && typeof o === 'object') {
            for (const [k, v] of Object.entries(o)) {
                if (v) {
                    const id = Number(k);
                    if (Number.isFinite(id)) {
                        m.set(id, true);
                    }
                }
            }
        }
    } catch {
        //
    }

    return m;
}

/** @param {Map<number, true>} map */
function persistQuestionFlags(sessionIdStr, map) {
    try {
        const o = {};
        for (const [k] of map) {
            o[String(k)] = true;
        }
        localStorage.setItem(questionFlagStorageKey(sessionIdStr), JSON.stringify(o));
    } catch {
        //
    }
}

function flattenQuestions(sections) {
    const out = [];
    for (const sec of sections || []) {
        for (const q of sec.questions || []) {
            out.push(q);
        }
    }
    return out;
}

function fillBlankCount(q) {
    const schema = q.answer_schema;
    const n = Number(schema?.blank_count ?? schema?.count ?? 0);
    if (n > 0) {
        return n;
    }
    const underscores = (q.question_text || '').match(/_{3,}/g);
    if (underscores && underscores.length > 0) {
        return underscores.length;
    }
    return 1;
}

async function main() {
    setupAxios();

    const sessionId = meta('exam-session-id');
    const examId = Number(meta('exam-id'));
    const studentId = Number(meta('student-id'));
    const enableLiveSockets = meta('qs-enable-live-sockets') === '1';
    const allowPollingFallback = meta('qs-allow-polling-fallback') === '1';
    const requireCameraMonitoring = meta('qs-require-camera-monitoring') === '1';
    const assignmentMode = meta('qs-assignment-mode') === '1';
    const assignmentClipboardBlock = meta('qs-assignment-clipboard-block') === '1';
    const examClipboardLock = meta('qs-exam-clipboard-lock') === '1';
    const examScreenshotMitigation = meta('qs-exam-screenshot-mitigation') === '1';
    const examScreenRecordMitigation = meta('qs-exam-screen-record-mitigation') === '1';
    if (!sessionId || !examId) {
        return;
    }

    const effectivePollMs = enableLiveSockets ? POLL_MS : 8000;

    const els = {
        title: document.getElementById('exam-title'),
        subtitle: document.getElementById('exam-subtitle'),
        timer: document.getElementById('exam-timer'),
        nav: document.getElementById('question-nav'),
        main: document.getElementById('question-container'),
        loading: document.getElementById('exam-loading'),
        banner: document.getElementById('exam-banner'),
        saveIndicator: document.getElementById('save-indicator'),
        btnSubmit: document.getElementById('btn-submit'),
        btnFullscreen: document.getElementById('btn-fullscreen'),
        fullscreenExitNotice: document.getElementById('fullscreen-exit-notice'),
        pauseOverlay: document.getElementById('exam-timer-pause-overlay'),
        btnResume: document.getElementById('btn-exam-resume'),
        video: document.getElementById('proctoring-video'),
        faceCanvas: document.getElementById('proctoring-face-canvas'),
        localProctorHint: document.getElementById('proctoring-local-hint'),
        videoStatus: document.getElementById('video-status'),
        essayClipboardToast: document.getElementById('essay-clipboard-toast'),
        recordingBadge: document.getElementById('exam-recording-badge'),
        fullscreenGate: document.getElementById('exam-fullscreen-gate'),
        btnFullscreenGate: document.getElementById('btn-fullscreen-gate'),
    };

    /** Always available so essay clipboard attempts log even if camera/proctoring init fails. */
    const proctoringBatcher = new ProctoringEventBatcher({
        examSessionKey: sessionId,
        apiClient: axios,
        flushIntervalMs: 4500,
        maxBatch: 14,
        onFlushResult: (data) => {
            if (!data || typeof data !== 'object') {
                return;
            }
            if (data.status === 'submitted_held' && !serverDone) {
                serverDone = true;
                submitInputLock = false;
                stopSubmitPersistence();
                stopTimer();
                stopHeartbeat();
                updateBanner(
                    typeof data.message === 'string' && data.message
                        ? data.message
                        : 'Your assessment has been submitted and is held for review.',
                    true,
                );
                examStateEngine.syncFromBackend({
                    ...(latestPayload || {}),
                    session_status: 'submitted',
                    exam_ui_state: 'held',
                    exam_status: 'submitted_held',
                });
                setSaveIndicator('Held for review', true);
                if (els.btnSubmit) {
                    els.btnSubmit.disabled = true;
                }
                syncControlDisabled();
                syncFullscreenGate();
                hideExamTabSwitchModal();
            }
            if (typeof data.client_message === 'string' && data.client_message !== '' && !serverDone) {
                updateBanner(data.client_message, true);
            }
        },
    });

    let essayClipboardToastTimer = null;
    let micLevelMeterFrame = null;
    function showEssayClipboardWarning() {
        const toast = els.essayClipboardToast;
        if (!toast) {
            return;
        }
        toast.textContent =
            assignmentMode && assignmentClipboardBlock
                ? 'Copy and paste is disabled for this assignment. Please type your answer directly.'
                : ESSAY_CLIPBOARD_WARNING_MESSAGE;
        toast.classList.remove('hidden');
        if (essayClipboardToastTimer) {
            window.clearTimeout(essayClipboardToastTimer);
        }
        essayClipboardToastTimer = window.setTimeout(() => {
            toast.classList.add('hidden');
            essayClipboardToastTimer = null;
        }, 3200);
    }

    function enqueueEssayClipboardAttempt(questionId, actionType) {
        void proctoringBatcher
            .enqueue({
                event_type: 'essay_clipboard_attempt',
                flagged: false,
                metadata: {
                    session_id: sessionId,
                    student_id: studentId,
                    exam_id: examId,
                    question_id: questionId,
                    action_type: actionType,
                },
            })
            .catch(() => {});
    }

    function enqueueExamIntegritySignal(signal, detail = {}) {
        const md = {
            session_id: sessionId,
            student_id: studentId,
            exam_id: examId,
            signal,
        };
        if (detail.question_id != null && Number.isFinite(Number(detail.question_id))) {
            md.question_id = Number(detail.question_id);
        }
        void proctoringBatcher
            .enqueue({
                event_type: 'exam_integrity_signal',
                flagged: false,
                metadata: md,
            })
            .catch(() => {});
        if (!assignmentMode && (signal === 'printscreen_key' || signal === 'capture_shortcut')) {
            void proctoringBatcher
                .enqueue({
                    event_type: 'possible_screenshot_attempt',
                    flagged: false,
                    metadata: {
                        ...md,
                        keys: signal,
                        detection_note: 'Best-effort only; browsers cannot detect every screenshot.',
                    },
                })
                .catch(() => {});
        }
    }

    void attachExamIntegritySurface({
        root: document.getElementById('exam-app'),
        assignmentMode,
        clipboardLock: examClipboardLock,
        screenshotMitigation: examScreenshotMitigation,
        screenRecordMitigation: examScreenRecordMitigation,
        enqueueSignal: enqueueExamIntegritySignal,
    });

    const examStateEngine = new ExamStateEngine();
    examStateEngine.configureApi(axios);
    examStateEngine.sessionRouteKey = sessionId;

    let flatQuestions = [];
    let currentIdx = 0;
    /** Furthest question index the student has reached (unlocks forward progress). */
    let furthestIdx = 0;
    /** @type {Map<number, true>} */
    const questionFlags = loadQuestionFlags(sessionId);
    let latestPayload = null;
    let timerHandle = null;
    /** Server has accepted submission (terminal for answers). */
    let serverDone = false;
    /** Invigilator / risk UI state (ExamStateEngine). */
    let riskInputsDisabled = false;
    /** True while submit is in progress or background retrying after failures. */
    let submitInputLock = false;
    let autoSubmitTriggered = false;
    const debouncers = new Map();
    const pendingSaveBuilders = new Map();
    /** Client-side payloads not yet confirmed on server (for UI + frontier checks). */
    const lastLocalPayload = new Map();
    const questionRevision = new Map();
    let serverSkewMs = 0;
    let examEndAtMs = null;
    let timedExam = false;
    let timeoutSubmitFired = false;
    let submitPersistTimer = null;
    let submitUiDefaultText = els.btnSubmit?.textContent ?? 'Submit';
    let fullscreenExitNoticeTimer = null;
    let examDocumentWasFullscreen = false;
    let timerPaused = false;
    let heartbeatHandle = null;

    function stopHeartbeat() {
        if (heartbeatHandle) {
            clearInterval(heartbeatHandle);
            heartbeatHandle = null;
        }
    }

    function startHeartbeat() {
        stopHeartbeat();
        heartbeatHandle = window.setInterval(() => {
            if (serverDone || timerPaused) {
                return;
            }
            void axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/heartbeat`).catch(() => {});
        }, 25000);
    }

    function syncPauseOverlay(show) {
        if (!els.pauseOverlay) {
            return;
        }
        els.pauseOverlay.classList.toggle('hidden', !show);
        if (show) {
            document.getElementById('exam-tab-switch-modal')?.classList.add('hidden');
        }
    }

    function applyTimerPausedFromState(data) {
        if (data.session_status === 'submitted') {
            syncPauseOverlay(false);
            stopHeartbeat();

            return;
        }
        const next = data.timer_paused === true || data.session_status === 'paused';
        if (next !== timerPaused) {
            timerPaused = next;
            if (timerPaused) {
                stopTimer();
                stopHeartbeat();
                updateBanner(
                    assignmentMode
                        ? 'Session paused — resume when your connection is stable.'
                        : 'Exam paused — your timer is frozen. Press Resume when you are ready.',
                    true,
                );
            } else {
                updateBanner('', false);
                startHeartbeat();
            }
        }
        syncPauseOverlay(timerPaused);
        const remLive = computeRemainingSeconds();
        if (timerPaused) {
            const fixed = Number(data.time_remaining_seconds ?? NaN);
            renderTimerDisplay(Number.isFinite(fixed) ? fixed : remLive);
        } else {
            renderTimerDisplay(remLive === null ? null : remLive);
        }
        syncControlDisabled();
    }

    function ensureTimerClockRunning() {
        if (serverDone || timerPaused || timerHandle) {
            return;
        }
        startTimerClock();
    }

    function isAnswerPayloadComplete(q, payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }
        if (q.type === 'mcq') {
            const sel = Array.isArray(payload.selected)
                ? payload.selected
                : payload.selected != null
                  ? [payload.selected]
                  : [];
            return sel.length > 0;
        }
        if (q.type === 'true_false') {
            return payload.value === true || payload.value === false;
        }
        if (q.type === 'fill_blank') {
            const n = fillBlankCount(q);
            const blanks = Array.isArray(payload.blanks) ? payload.blanks : [];
            if (blanks.length < n) {
                return false;
            }
            for (let i = 0; i < n; i += 1) {
                if (!String(blanks[i] ?? '').trim()) {
                    return false;
                }
            }
            return true;
        }
        if (q.type === 'essay') {
            return String(payload.text ?? '').trim().length > 0;
        }
        return false;
    }

    function getEffectivePayload(q) {
        if (lastLocalPayload.has(q.id)) {
            return lastLocalPayload.get(q.id);
        }
        return latestPayload?.saved_answers?.[String(q.id)]?.answer_payload ?? null;
    }

    function isQuestionFullyAnswered(q) {
        return isAnswerPayloadComplete(q, getEffectivePayload(q));
    }

    function firstIncompleteIndex() {
        for (let i = 0; i < flatQuestions.length; i += 1) {
            if (!isQuestionFullyAnswered(flatQuestions[i])) {
                return i;
            }
        }
        return flatQuestions.length;
    }

    function refreshFrontierFromAnswers() {
        if (!flatQuestions.length) {
            furthestIdx = 0;
            return;
        }
        const fi = firstIncompleteIndex();
        furthestIdx = fi >= flatQuestions.length ? flatQuestions.length - 1 : fi;
    }

    function pruneLocalPayloadFromServer() {
        const sa = latestPayload?.saved_answers;
        if (!sa || typeof sa !== 'object') {
            return;
        }
        for (const q of flatQuestions) {
            const server = sa[String(q.id)]?.answer_payload;
            if (!isAnswerPayloadComplete(q, server)) {
                continue;
            }
            const loc = lastLocalPayload.get(q.id);
            if (loc === undefined) {
                continue;
            }
            if (isAnswerPayloadComplete(q, loc)) {
                lastLocalPayload.delete(q.id);
            }
        }
    }

    function countAnsweredQuestions() {
        let c = 0;
        for (const q of flatQuestions) {
            if (isQuestionFullyAnswered(q)) {
                c += 1;
            }
        }
        return c;
    }

    function updateNavStats() {
        const answeredEl = document.getElementById('nav-count-answered');
        if (!answeredEl) {
            return;
        }
        const flaggedEl = document.getElementById('nav-count-flagged');
        const leftEl = document.getElementById('nav-count-left');
        const n = flatQuestions.length;
        const answered = countAnsweredQuestions();
        let flagged = 0;
        for (const q of flatQuestions) {
            if (questionFlags.has(q.id)) {
                flagged += 1;
            }
        }
        const left = Math.max(0, n - answered);
        if (answeredEl) {
            answeredEl.textContent = String(answered);
        }
        if (flaggedEl) {
            flaggedEl.textContent = String(flagged);
        }
        if (leftEl) {
            leftEl.textContent = String(left);
        }
    }

    function updateExamMetaPanel() {
        const label = document.getElementById('exam-current-q-label');
        const typeEl = document.getElementById('exam-current-q-type');
        const fill = document.getElementById('exam-progress-fill');
        const pl = document.getElementById('exam-progress-label');
        const n = flatQuestions.length;
        const q = flatQuestions[currentIdx];
        if (!label) {
            return;
        }
        if (!q || !n) {
            label.textContent = '—';
            if (typeEl) {
                typeEl.textContent = '—';
            }
            if (fill) {
                fill.style.width = '0%';
            }
            if (pl) {
                pl.textContent = '—';
            }

            return;
        }
        label.textContent = `Q${currentIdx + 1}`;
        if (typeEl) {
            typeEl.textContent = questionTypeLabel(q.type);
        }
        const pct = Math.round(((currentIdx + 1) / n) * 100);
        if (fill) {
            fill.style.width = `${pct}%`;
        }
        if (pl) {
            pl.textContent = `${pct}% · ${currentIdx + 1} / ${n}`;
        }
    }

    function toggleCurrentQuestionFlag() {
        const q = flatQuestions[currentIdx];
        if (!q || effectiveInputsLocked()) {
            return;
        }
        if (questionFlags.has(q.id)) {
            questionFlags.delete(q.id);
        } else {
            questionFlags.set(q.id, true);
        }
        persistQuestionFlags(sessionId, questionFlags);
        updateNavStats();
        renderNav();
        syncFlagButton();
    }

    function syncFlagButton() {
        const btn = document.getElementById('btn-flag-question');
        const q = flatQuestions[currentIdx];
        if (!btn || !q) {
            return;
        }
        const on = questionFlags.has(q.id);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.classList.toggle('border-amber-500', on);
        btn.classList.toggle('bg-amber-50', on);
        btn.classList.toggle('ring-2', on);
        btn.classList.toggle('ring-amber-200', on);
    }

    function syncNavButtonsInQuestion() {
        const n = flatQuestions.length;
        const q = flatQuestions[currentIdx];
        const atFrontier = currentIdx === furthestIdx;
        const frontierComplete = q ? isQuestionFullyAnswered(q) : true;
        const back = document.getElementById('btn-q-back');
        const next = document.getElementById('btn-q-next');
        if (back) {
            back.disabled = currentIdx <= 0;
        }
        if (next) {
            next.disabled = currentIdx >= n - 1 || (atFrontier && !frontierComplete);
        }
    }

    function updateAnswerSummary() {
        if (!flatQuestions.length) {
            return;
        }
        updateNavStats();
    }

    function setSaveIndicator(text, ok = true) {
        if (!els.saveIndicator) {
            return;
        }
        els.saveIndicator.textContent = text;
        els.saveIndicator.classList.toggle('text-slate-500', ok);
        els.saveIndicator.classList.toggle('text-rose-600', !ok);
        els.saveIndicator.classList.toggle('font-medium', !ok);
        els.saveIndicator.classList.remove('text-qs-muted', 'text-qs-danger');
    }

    function syncAssignmentStudentPanel(data) {
        if (!assignmentMode) {
            return;
        }
        const v = data?.assignment_student_view;
        if (!v || typeof v !== 'object') {
            return;
        }
        const statusEl = document.getElementById('assignment-status-line');
        const gradeEl = document.getElementById('assignment-grade-line');
        if (statusEl && typeof v.status_heading === 'string') {
            statusEl.textContent = v.status_heading;
        }
        if (!gradeEl) {
            return;
        }
        const parts = [];
        if (typeof v.grade_heading === 'string') {
            parts.push(v.grade_heading);
        }
        if (v.score != null && v.grades_visible_to_student) {
            const pct = v.score_percentage != null ? ` (${v.score_percentage}%)` : '';
            parts.push(`Score: ${v.score}${pct}`);
        }
        if (v.examiner_feedback) {
            parts.push(`Feedback: ${v.examiner_feedback}`);
        }
        gradeEl.textContent = parts.join(' · ');

        const ex = data?.exam;
        const fi = document.getElementById('assignment-file-input');
        const fst = document.getElementById('assignment-file-status');
        const slot = document.getElementById('assignment-file-upload-slot');
        if (slot && ex && typeof ex === 'object' && !ex.assignment_allows_files) {
            slot.classList.add('hidden');
        } else if (slot) {
            slot.classList.remove('hidden');
        }
        if (fi && fst && ex && typeof ex === 'object') {
            const allow = Boolean(ex.assignment_allows_files);
            const submitted = String(data?.session_status ?? '') === 'submitted';
            const attachmentRequired = Boolean(ex.assignment_attachment_required);
            fi.disabled = !allow || submitted || serverDone;
            if (allow && fst.textContent === '' && !submitted && !serverDone) {
                fst.textContent = attachmentRequired
                    ? 'A file upload is required before you can submit.'
                    : 'Optional: attach a supporting file if you want one on record.';
            }
            if (Array.isArray(ex.assignment_allowed_extensions) && ex.assignment_allowed_extensions.length) {
                fi.accept = ex.assignment_allowed_extensions
                    .map((x) => `.${String(x).replace(/^\./, '')}`)
                    .join(',');
            } else {
                fi.accept = '.pdf,.doc,.docx,.txt,.png,.jpg,.jpeg';
            }
            if (!fi.dataset.qsBound) {
                fi.dataset.qsBound = '1';
                fi.addEventListener('change', () => {
                    void (async () => {
                        const file = fi.files?.[0];
                        if (!file || serverDone) {
                            return;
                        }
                        fst.textContent = `Selected: ${file.name}`;
                        const fd = new FormData();
                        fd.append('file', file);
                        try {
                            fst.textContent = 'Uploading…';
                            await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/assignment-files`, fd);
                            fst.textContent = `Uploaded: ${file.name}`;
                            try {
                                await fetchState();
                            } catch {
                                //
                            }
                        } catch {
                            fst.textContent = 'Upload failed — check type/size, then try again.';
                        }
                    })();
                });
            }
        }
    }

    function updateBanner(message, show) {
        if (!els.banner) {
            return;
        }
        if (!show) {
            els.banner.classList.add('hidden');
            els.banner.textContent = '';
            return;
        }
        els.banner.textContent = message;
        els.banner.classList.remove('hidden');
    }

    function formatMmSs(sec) {
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    function stopTimer() {
        if (timerHandle) {
            clearInterval(timerHandle);
            timerHandle = null;
        }
    }

    function syncTimerAnchors(payload) {
        if (assignmentMode) {
            examEndAtMs = null;
            timedExam = false;

            return;
        }
        const st = payload?.server_time;
        if (st) {
            const serverMs = Date.parse(st);
            if (!Number.isNaN(serverMs)) {
                serverSkewMs = serverMs - Date.now();
            }
        }
        const endIso = payload?.exam_end_at;
        if (endIso) {
            const endMs = Date.parse(endIso);
            if (!Number.isNaN(endMs)) {
                examEndAtMs = endMs;
                timedExam = true;
                return;
            }
        }
        const dur = Number(payload?.duration_minutes ?? 0);
        if (dur > 0 && st) {
            const rem = Number(payload?.time_remaining_seconds ?? 0);
            const anchor = Date.parse(st);
            if (!Number.isNaN(anchor)) {
                examEndAtMs = anchor + Math.max(0, rem) * 1000;
                timedExam = true;
                return;
            }
        }
        examEndAtMs = null;
        timedExam = false;
    }

    function computeRemainingSeconds() {
        if (serverDone || !timedExam || examEndAtMs === null) {
            return null;
        }
        const serverNow = Date.now() + serverSkewMs;
        return Math.max(0, Math.floor((examEndAtMs - serverNow) / 1000));
    }

    function renderTimerDisplay(sec) {
        if (!els.timer) {
            return;
        }
        if (sec === null) {
            els.timer.textContent = '—';
            return;
        }
        els.timer.textContent = formatMmSs(sec);
    }

    function startTimerClock() {
        stopTimer();
        timerHandle = window.setInterval(() => {
            if (serverDone || timerPaused) {
                stopTimer();

                return;
            }
            const sec = computeRemainingSeconds();
            if (sec === null) {
                return;
            }
            renderTimerDisplay(sec);
            if (sec <= 0 && !timeoutSubmitFired) {
                timeoutSubmitFired = true;
                stopTimer();
                void submitExam('timeout');
            }
        }, 1000);
    }

    function effectiveInputsLocked() {
        return serverDone || riskInputsDisabled || submitInputLock || timerPaused;
    }

    function applyRiskInputsDisabled(disabled) {
        riskInputsDisabled = !!disabled;
        syncControlDisabled();
    }

    function syncControlDisabled() {
        const locked = effectiveInputsLocked();
        const controls = document.querySelectorAll(
            '#question-container input, #question-container textarea, #question-container button[data-q-action], #btn-fullscreen',
        );
        controls.forEach((el) => {
            el.disabled = locked;
        });
        syncSubmitButtonState();
    }

    function syncSubmitButtonState() {
        if (!els.btnSubmit) {
            return;
        }
        els.btnSubmit.disabled = serverDone || riskInputsDisabled || submitInputLock;
    }

    function setSubmitButtonSubmitting(isSubmitting) {
        if (!els.btnSubmit) {
            return;
        }
        if (isSubmitting) {
            els.btnSubmit.disabled = true;
            els.btnSubmit.textContent = 'Submitting…';
        } else if (!serverDone) {
            els.btnSubmit.disabled = riskInputsDisabled || submitInputLock;
            els.btnSubmit.textContent = submitUiDefaultText;
        }
    }

    function stopSubmitPersistence() {
        if (submitPersistTimer) {
            clearInterval(submitPersistTimer);
            submitPersistTimer = null;
        }
    }

    function sessionIsSubmittedForQueue() {
        return serverDone || latestPayload?.session_status === 'submitted';
    }

    function mergeRevisionsFromState(data) {
        const sa = data?.saved_answers;
        if (!sa || typeof sa !== 'object') {
            return;
        }
        for (const [qid, row] of Object.entries(sa)) {
            const id = Number(qid);
            if (!Number.isFinite(id)) {
                continue;
            }
            const r = Number(row?.client_revision ?? 0);
            if (!Number.isFinite(r) || r < 0) {
                continue;
            }
            const cur = questionRevision.get(id) ?? 0;
            if (r > cur) {
                questionRevision.set(id, r);
            }
        }
    }

    function applyServerRevisionHint(questionId, rev) {
        if (!Number.isFinite(rev)) {
            return;
        }
        const cur = questionRevision.get(questionId) ?? 0;
        if (rev > cur) {
            questionRevision.set(questionId, rev);
        }
    }

    function onSubmitSucceeded() {
        serverDone = true;
        submitInputLock = false;
        stopSubmitPersistence();
        stopTimer();
        void clearSessionPending(sessionId);
        syncControlDisabled();
        updateBanner(
            assignmentMode ? 'Assignment submitted. You may close this page.' : 'Exam submitted. You may close this page.',
            true,
        );
        setSaveIndicator('Submitted', true);
        if (els.btnSubmit) {
            els.btnSubmit.disabled = true;
            els.btnSubmit.textContent = submitUiDefaultText;
        }
        syncFullscreenGate();
        hideExamTabSwitchModal();
    }

    /**
     * @returns {Promise<'saved' | 'stale'>}
     */
    async function postAnswerOnce(questionId, payload, revision) {
        const { data } = await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/answers`, {
            question_id: questionId,
            answer_payload: payload,
            client_revision: revision,
        });
        if (data?.status === 'noop' && data?.reason === 'stale_revision') {
            applyServerRevisionHint(questionId, Number(data.client_revision));
            return 'stale';
        }
        applyServerRevisionHint(questionId, Number(data?.client_revision));
        return 'saved';
    }

    async function flushOfflineAnswerQueue() {
        if (!navigator.onLine || sessionIsSubmittedForQueue()) {
            return;
        }
        const pending = await listPendingForSession(sessionId);
        pending.sort((a, b) => a.questionId - b.questionId);
        for (const row of pending) {
            if (sessionIsSubmittedForQueue()) {
                return;
            }
            let ok = false;
            for (let a = 0; a < SAVE_RETRY && !ok; a += 1) {
                try {
                    const outcome = await postAnswerOnce(row.questionId, row.payload, row.revision);
                    if (outcome === 'saved' || outcome === 'stale') {
                        ok = true;
                    }
                } catch {
                    await new Promise((r) => setTimeout(r, 400 * (a + 1)));
                }
            }
            if (ok) {
                await clearPendingAnswer(sessionId, row.questionId);
            }
        }
    }

    async function sendAnswerWithOfflineQueue(questionId, payload) {
        if (sessionIsSubmittedForQueue()) {
            return true;
        }

        const rev = (questionRevision.get(questionId) ?? 0) + 1;
        questionRevision.set(questionId, rev);

        let attempt = 0;
        while (attempt < SAVE_RETRY) {
            if (sessionIsSubmittedForQueue()) {
                return true;
            }
            try {
                const outcome = await postAnswerOnce(questionId, payload, rev);
                if (outcome === 'saved' || outcome === 'stale') {
                    await clearPendingAnswer(sessionId, questionId);
                    await flushOfflineAnswerQueue();
                    return true;
                }
            } catch {
                attempt += 1;
                await new Promise((r) => setTimeout(r, 400 * attempt));
            }
        }
        await queuePendingAnswer(sessionId, questionId, rev, payload);
        return false;
    }

    function canNavigateTo(i) {
        if (!Number.isFinite(i) || i < 0 || i >= flatQuestions.length) {
            return false;
        }
        /** First incomplete question index is the frontier; nothing beyond it is reachable. */
        return i <= furthestIdx;
    }

    function goToQuestionIndex(i) {
        if (!canNavigateTo(i)) {
            return;
        }
        if (i !== currentIdx) {
            void flushDebouncerForQuestion(flatQuestions[currentIdx].id);
        }
        currentIdx = i;
        renderQuestion();
        renderNav();
        syncNavButtonsInQuestion();
    }

    async function advanceQuestionNext() {
        if (currentIdx >= flatQuestions.length - 1) {
            return;
        }
        const q = flatQuestions[currentIdx];
        if (currentIdx === furthestIdx && !isQuestionFullyAnswered(q)) {
            return;
        }
        await flushDebouncerForQuestion(q.id);
        currentIdx += 1;
        pruneLocalPayloadFromServer();
        refreshFrontierFromAnswers();
        renderQuestion();
        renderNav();
        updateAnswerSummary();
        syncNavButtonsInQuestion();
    }

    function retreatQuestionBack() {
        if (currentIdx <= 0) {
            return;
        }
        void flushDebouncerForQuestion(flatQuestions[currentIdx].id);
        currentIdx -= 1;
        renderQuestion();
        renderNav();
        syncNavButtonsInQuestion();
    }

    function renderNav() {
        if (!els.nav) {
            return;
        }
        els.nav.innerHTML = '';
        flatQuestions.forEach((q, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const allowed = canNavigateTo(i);
            const isCurrent = i === currentIdx;
            const answered = isQuestionFullyAnswered(q);
            const flagged = questionFlags.has(q.id);
            let cls =
                'relative flex h-8 min-w-8 shrink-0 items-center justify-center rounded-full border text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 ';
            if (isCurrent) {
                cls += 'border-slate-900 bg-slate-900 text-white shadow-sm ';
            } else if (!allowed) {
                cls += 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 ';
            } else if (answered) {
                cls += 'border-slate-300 bg-white text-slate-800 ring-2 ring-emerald-500/85 hover:border-slate-400 ';
            } else {
                cls += 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 ';
            }
            btn.className = cls.trim();
            btn.disabled = !allowed;
            btn.textContent = `${i + 1}`;
            if (flagged) {
                const dot = document.createElement('span');
                dot.className = 'absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-amber-500 ring-2 ring-white';
                dot.setAttribute('aria-hidden', 'true');
                btn.appendChild(dot);
            }
            btn.title = !allowed
                ? 'Answer earlier questions first.'
                : flagged
                  ? 'Flagged for review'
                  : answered
                    ? 'Answered'
                    : 'Not answered yet';
            btn.addEventListener('click', () => {
                if (!allowed) {
                    return;
                }
                goToQuestionIndex(i);
            });
            els.nav.appendChild(btn);
        });
        updateNavStats();
    }

    async function flushDebouncerForQuestion(questionId) {
        const prev = debouncers.get(questionId);
        if (prev) {
            clearTimeout(prev);
            debouncers.delete(questionId);
        }
        const build = pendingSaveBuilders.get(questionId);
        if (!build) {
            return;
        }
        pendingSaveBuilders.delete(questionId);
        const payload = build();
        if (!payload) {
            return;
        }
        setSaveIndicator('Saving…', true);
        const ok = await sendAnswerWithOfflineQueue(questionId, payload);
        setSaveIndicator(ok ? 'Saved' : 'Offline or unsaved — will retry when online', ok);
    }

    function bumpLocalAnswerUi() {
        pruneLocalPayloadFromServer();
        refreshFrontierFromAnswers();
        if (currentIdx > furthestIdx) {
            currentIdx = furthestIdx;
            renderQuestion();
        }
        renderNav();
        updateAnswerSummary();
        syncNavButtonsInQuestion();
    }

    function scheduleSave(questionId, buildPayload) {
        pendingSaveBuilders.set(questionId, buildPayload);
        const prev = debouncers.get(questionId);
        if (prev) {
            clearTimeout(prev);
        }
        setSaveIndicator('Saving…', true);
        debouncers.set(
            questionId,
            setTimeout(async () => {
                pendingSaveBuilders.delete(questionId);
                const payload = buildPayload();
                if (!payload) {
                    return;
                }
                const ok = await sendAnswerWithOfflineQueue(questionId, payload);
                setSaveIndicator(ok ? 'Saved' : 'Offline or unsaved — will retry when online', ok);
            }, DEBOUNCE_MS),
        );
    }

    function renderQuestion() {
        if (!els.main) {
            return;
        }
        const q = flatQuestions[currentIdx];
        els.main.innerHTML = '';
        if (!q) {
            return;
        }

        const root = document.createElement('div');
        root.className = 'space-y-6';
        root.dataset.questionId = String(q.id);

        const card = document.createElement('div');
        card.className = 'overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm';

        const head = document.createElement('div');
        head.className = 'border-b border-slate-100 px-5 py-5 sm:px-8 sm:py-7';
        const headRow = document.createElement('div');
        headRow.className = 'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between';

        const titleCol = document.createElement('div');
        titleCol.className = 'min-w-0';
        const meta = document.createElement('p');
        meta.className = 'text-xs font-extrabold uppercase tracking-widest text-slate-400';
        meta.textContent = `Question ${currentIdx + 1} of ${flatQuestions.length}`;
        const h = document.createElement('h2');
        h.className =
            'mt-4 max-w-3xl break-words text-2xl font-extrabold leading-snug text-slate-950 sm:text-3xl';
        h.innerHTML = escapeHtml(q.question_text || '').replace(/\n/g, '<br/>');
        const marks = document.createElement('p');
        marks.className = 'mt-2 text-sm font-semibold text-slate-500';
        marks.textContent = `${q.marks} marks · ${questionTypeLabel(q.type)}`;
        titleCol.appendChild(meta);
        titleCol.appendChild(h);
        titleCol.appendChild(marks);

        const flagBtn = document.createElement('button');
        flagBtn.type = 'button';
        flagBtn.id = 'btn-flag-question';
        flagBtn.className =
            'inline-flex shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50';
        flagBtn.innerHTML = '<i class="fa-regular fa-flag me-2" aria-hidden="true"></i>Flag';
        flagBtn.addEventListener('click', () => toggleCurrentQuestionFlag());

        headRow.appendChild(titleCol);
        headRow.appendChild(flagBtn);
        head.appendChild(headRow);

        const body = document.createElement('div');
        body.className = 'px-5 py-6 sm:px-8 sm:py-8';

        const saved = getEffectivePayload(q);

        const applyMcqCardClasses = (labelEl, on) => {
            labelEl.className =
                'flex h-full min-h-0 min-w-0 cursor-pointer flex-col rounded-2xl border p-3 transition sm:p-4 ' +
                (on ? 'border-2 border-slate-900 bg-slate-50' : 'border border-slate-200 bg-white hover:border-slate-400');
        };

        if (q.type === 'mcq') {
            const optsRoot = document.createElement('div');
            const opts = Array.isArray(q.options) ? q.options : [];
            const maxOptLen = opts.reduce((m, o) => Math.max(m, String(o ?? '').length), 0);
            const useTwoColGrid = opts.length >= 2 && maxOptLen <= 56;
            optsRoot.className = useTwoColGrid
                ? 'grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 sm:gap-4 [&>*]:min-w-0'
                : 'grid grid-cols-1 gap-3 [&>*]:min-w-0';
            const selected = new Set(
                Array.isArray(saved?.selected)
                    ? saved.selected
                    : saved?.selected != null
                      ? [saved.selected]
                      : [],
            );
            opts.forEach((label, idx) => {
                const letter = String.fromCharCode(65 + idx);
                const row = document.createElement('label');
                applyMcqCardClasses(row, selected.has(idx));
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'sr-only';
                cb.checked = selected.has(idx);
                const syncRowStyles = () => {
                    const sel = [];
                    optsRoot.querySelectorAll('label').forEach((lab, j) => {
                        const box = /** @type {HTMLInputElement | null} */ (lab.querySelector('input'));
                        const on = Boolean(box?.checked);
                        applyMcqCardClasses(lab, on);
                        const circ = lab.querySelector('[data-mcq-letter]');
                        if (circ) {
                            circ.className =
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-full border text-sm font-extrabold ' +
                                (on
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-300 text-slate-600');
                        }
                        const io = lab.querySelector('[data-mcq-off]');
                        const ii = lab.querySelector('[data-mcq-on]');
                        if (io && ii) {
                            io.classList.toggle('hidden', on);
                            ii.classList.toggle('hidden', !on);
                        }
                        if (on) {
                            sel.push(j);
                        }
                    });
                    return sel;
                };
                cb.addEventListener('change', () => {
                    const sel = syncRowStyles();
                    lastLocalPayload.set(q.id, { type: 'mcq', selected: sel });
                    bumpLocalAnswerUi();
                    if (sel.length === 0) {
                        const prev = debouncers.get(q.id);
                        if (prev) {
                            clearTimeout(prev);
                            debouncers.delete(q.id);
                        }
                        pendingSaveBuilders.delete(q.id);
                        return;
                    }
                    scheduleSave(q.id, () => ({ type: 'mcq', selected: sel }));
                });
                row.appendChild(cb);
                const inner = document.createElement('div');
                inner.className = 'flex min-h-0 min-w-0 w-full flex-1 items-center gap-3 sm:gap-4';
                const circle = document.createElement('div');
                circle.dataset.mcqLetter = '1';
                circle.textContent = letter;
                circle.className =
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-full border text-sm font-extrabold ' +
                    (selected.has(idx)
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-300 text-slate-600');
                const txt = document.createElement('div');
                txt.className = 'min-w-0 flex-1 overflow-hidden';
                const p = document.createElement('p');
                p.className = 'break-words text-sm font-bold leading-snug text-slate-900 sm:text-base';
                p.textContent = label;
                txt.appendChild(p);
                const iconOff = document.createElement('i');
                iconOff.dataset.mcqOff = '1';
                iconOff.className = selected.has(idx) ? 'hidden' : 'fa-regular fa-circle text-slate-300';
                iconOff.setAttribute('aria-hidden', 'true');
                const iconOn = document.createElement('i');
                iconOn.dataset.mcqOn = '1';
                iconOn.className = selected.has(idx)
                    ? 'fa-solid fa-circle-check text-slate-900'
                    : 'fa-solid fa-circle-check hidden text-slate-900';
                iconOn.setAttribute('aria-hidden', 'true');
                inner.appendChild(circle);
                inner.appendChild(txt);
                inner.appendChild(iconOff);
                inner.appendChild(iconOn);
                row.appendChild(inner);
                optsRoot.appendChild(row);
            });
            body.appendChild(optsRoot);
        } else if (q.type === 'true_false') {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 gap-3 min-[360px]:grid-cols-2 [&>*]:min-w-0';
            const mk = (val, lbl) => {
                const lab = document.createElement('label');
                const on = saved?.value === val;
                lab.className =
                    'flex min-h-[44px] w-full min-w-0 cursor-pointer items-center justify-center gap-2 rounded-2xl border px-4 py-3 text-center text-sm font-extrabold ' +
                    (on ? 'border-2 border-slate-900 bg-slate-50 text-slate-900' : 'border border-slate-200 bg-white text-slate-600');
                const rb = document.createElement('input');
                rb.type = 'radio';
                rb.name = `tf-${q.id}`;
                rb.className = 'sr-only peer';
                rb.checked = on;
                rb.addEventListener('change', () => {
                    lastLocalPayload.set(q.id, { type: 'true_false', value: val });
                    bumpLocalAnswerUi();
                    scheduleSave(q.id, () => ({ type: 'true_false', value: val }));
                });
                lab.appendChild(rb);
                lab.appendChild(document.createTextNode(lbl));
                return lab;
            };
            row.appendChild(mk(true, 'True'));
            row.appendChild(mk(false, 'False'));
            body.appendChild(row);
        } else if (q.type === 'fill_blank') {
            const n = fillBlankCount(q);
            const blanks = Array.isArray(saved?.blanks) ? saved.blanks : [];
            const blanksRoot = document.createElement('div');
            blanksRoot.className = 'space-y-3';
            for (let i = 0; i < n; i += 1) {
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.className =
                    'block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/15';
                inp.value = blanks[i] ?? '';
                inp.addEventListener('input', () => {
                    const vals = [];
                    blanksRoot.querySelectorAll('input[data-blank]').forEach((node) => vals.push(node.value));
                    lastLocalPayload.set(q.id, { type: 'fill_blank', blanks: vals });
                    bumpLocalAnswerUi();
                    scheduleSave(q.id, () => ({ type: 'fill_blank', blanks: vals }));
                });
                inp.dataset.blank = String(i);
                if (assignmentMode && assignmentClipboardBlock) {
                    attachEssayAntiClipboard(inp, {
                        showWarning: showEssayClipboardWarning,
                        onBlocked: (actionType) => enqueueEssayClipboardAttempt(q.id, actionType),
                    });
                }
                blanksRoot.appendChild(inp);
            }
            body.appendChild(blanksRoot);
        } else if (q.type === 'essay') {
            const ta = document.createElement('textarea');
            ta.rows = 10;
            ta.className =
                'block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 font-sans text-slate-900 shadow-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/15';
            ta.value = saved?.text ?? '';
            ta.addEventListener('input', () => {
                lastLocalPayload.set(q.id, { type: 'essay', text: ta.value });
                bumpLocalAnswerUi();
                scheduleSave(q.id, () => ({ type: 'essay', text: ta.value }));
            });
            const usePerQuestionEssayClipboard =
                !examClipboardLock && (!assignmentMode || assignmentClipboardBlock);
            if (usePerQuestionEssayClipboard) {
                attachEssayAntiClipboard(ta, {
                    showWarning: showEssayClipboardWarning,
                    onBlocked: (actionType) => enqueueEssayClipboardAttempt(q.id, actionType),
                });
            }
            body.appendChild(ta);
        }

        const foot = document.createElement('div');
        foot.className =
            'mt-8 flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-stretch sm:justify-between sm:gap-4';
        const backBtn = document.createElement('button');
        backBtn.type = 'button';
        backBtn.id = 'btn-q-back';
        backBtn.dataset.qAction = 'back';
        backBtn.className =
            'inline-flex min-h-[48px] w-full min-w-0 shrink-0 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-extrabold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto sm:min-w-[10.5rem] sm:px-5';
        backBtn.innerHTML = '<i class="fa-solid fa-arrow-left shrink-0" aria-hidden="true"></i><span class="min-w-0 truncate">Previous</span>';

        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.id = 'btn-q-next';
        nextBtn.dataset.qAction = 'next';
        nextBtn.className =
            'inline-flex min-h-[48px] w-full min-w-0 shrink-0 items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40 sm:ms-auto sm:w-auto sm:min-w-[10.5rem] sm:px-6';
        nextBtn.innerHTML =
            '<span class="min-w-0 truncate">Next question</span><i class="fa-solid fa-arrow-right shrink-0" aria-hidden="true"></i>';

        foot.appendChild(backBtn);
        foot.appendChild(nextBtn);

        card.appendChild(head);
        card.appendChild(body);
        card.appendChild(foot);
        root.appendChild(card);
        els.main.appendChild(root);

        backBtn.addEventListener('click', () => retreatQuestionBack());
        nextBtn.addEventListener('click', () => void advanceQuestionNext());

        updateExamMetaPanel();
        syncFlagButton();
        syncNavButtonsInQuestion();
        syncControlDisabled();
    }

    function applySubmittedFromState(data) {
        if (data.session_status === 'submitted') {
            serverDone = true;
            submitInputLock = false;
            stopSubmitPersistence();
            stopTimer();
            stopHeartbeat();
            const held = data.exam_ui_state === 'held';
            if (assignmentMode) {
                updateBanner(
                    held ? 'Under review — your result is held.' : 'Assignment submitted. Thank you.',
                    true,
                );
                setSaveIndicator(held ? 'Held for review' : 'Submitted', true);
            } else {
                updateBanner(held ? 'Under review — your result is held.' : 'Exam submitted.', true);
                setSaveIndicator('Submitted', true);
            }
            if (els.btnSubmit) {
                els.btnSubmit.disabled = true;
                els.btnSubmit.textContent = submitUiDefaultText;
            }
            syncControlDisabled();
            syncFullscreenGate();
            hideExamTabSwitchModal();
        }
    }

    async function fetchState() {
        const { data } = await axios.get(`/exam-sessions/${encodeURIComponent(sessionId)}/state`);
        latestPayload = data;
        mergeRevisionsFromState(data);
        examStateEngine.syncFromBackend(data);

        if (data.exam?.title && els.title) {
            els.title.textContent = data.exam.title;
        }
        if (els.subtitle && data.exam) {
            const c = data.exam.course;
            if (c && (c.code || c.title)) {
                const parts = [c.code, c.title].filter(Boolean);
                els.subtitle.textContent = parts.join(' · ');
                els.subtitle.classList.remove('hidden');
            } else if (data.exam.description) {
                els.subtitle.textContent = data.exam.description;
                els.subtitle.classList.remove('hidden');
            } else {
                els.subtitle.textContent = '';
                els.subtitle.classList.add('hidden');
            }
        }

        if (Array.isArray(data.sections)) {
            const isFirstHydrate = flatQuestions.length === 0;
            if (isFirstHydrate) {
                flatQuestions = flattenQuestions(data.sections);
            }
            if (flatQuestions.length) {
                pruneLocalPayloadFromServer();
                refreshFrontierFromAnswers();
                if (isFirstHydrate) {
                    currentIdx = furthestIdx;
                    if (els.loading) {
                        els.loading.classList.add('hidden');
                    }
                    els.main?.classList.remove('hidden');
                    renderQuestion();
                } else if (currentIdx > furthestIdx) {
                    currentIdx = furthestIdx;
                    renderQuestion();
                }
                renderNav();
                updateAnswerSummary();
                syncNavButtonsInQuestion();
            }
        }

        syncTimerAnchors(data);

        applySubmittedFromState(data);
        applyTimerPausedFromState(data);
        syncAssignmentStudentPanel(data);
        updateProctoringFromState(data);
        startHeartbeat();

        ensureTimerClockRunning();

        await flushOfflineAnswerQueue();

        syncFullscreenGate();

        return data;
    }

    async function submitExam(reason = 'manual') {
        if (serverDone) {
            return;
        }

        const exPre = latestPayload?.exam;
        const svPre = latestPayload?.assignment_student_view;
        if (
            assignmentMode &&
            exPre?.assignment_allows_files &&
            exPre?.assignment_attachment_required &&
            (svPre?.assignment_submitted_file_count ?? 0) < 1
        ) {
            updateBanner('Please upload the required file before submitting this assignment.', true);
            return;
        }

        submitInputLock = true;
        syncControlDisabled();
        setSaveIndicator(reason === 'timeout' ? 'Time expired — submitting…' : 'Submitting…', true);

        const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

        for (let attempt = 1; attempt <= SUBMIT_RETRIES; attempt += 1) {
            try {
                const { data } = await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/submit`);
                if (data?.status === 'submitted') {
                    onSubmitSucceeded();
                    return;
                }
            } catch (e) {
                if (
                    assignmentMode &&
                    e?.response?.status === 422 &&
                    e?.response?.data?.errors?.submit
                ) {
                    const err = e.response.data.errors.submit;
                    const msg = Array.isArray(err) ? err[0] : String(err);
                    updateBanner(msg || 'Cannot submit this assignment yet.', true);
                    submitInputLock = false;
                    syncControlDisabled();
                    setSaveIndicator('', true);
                    setSubmitButtonSubmitting(false);
                    return;
                }
                await sleep(500 * attempt);
            }
        }

        updateBanner(
            'Submission failed. Do not close this page. Retrying in the background…',
            true,
        );
        setSaveIndicator('Still submitting — keep this tab open', false);

        stopSubmitPersistence();
        const persistStartedAt = Date.now();
        submitPersistTimer = window.setInterval(() => {
            void (async () => {
                if (Date.now() - persistStartedAt > SUBMIT_PERSIST_MAX_MS) {
                    stopSubmitPersistence();
                    submitInputLock = false;
                    updateBanner(
                        'Could not confirm submission after several minutes. Check your connection, then use Submit again or refresh this page.',
                        true,
                    );
                    setSaveIndicator('Submit not confirmed', false);
                    syncControlDisabled();
                    setSubmitButtonSubmitting(false);
                    return;
                }
                if (serverDone) {
                    stopSubmitPersistence();
                    return;
                }
                try {
                    await fetchState();
                } catch {
                    //
                }
                if (serverDone) {
                    stopSubmitPersistence();
                    return;
                }
                try {
                    const { data } = await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/submit`);
                    if (data?.status === 'submitted') {
                        onSubmitSucceeded();
                    }
                } catch {
                    //
                }
            })();
        }, SUBMIT_PERSIST_INTERVAL_MS);
    }

    examStateEngine.subscribe(({ state, payload }) => {
        const merged = { ...(latestPayload ?? {}), ...(payload ?? {}) };
        updateProctoringFromState(merged);
        if (state === 'warning') {
            updateBanner('Warning: please stay focused on the exam.', true);
            if (!serverDone) {
                applyRiskInputsDisabled(false);
            }
        } else if (state === 'proctoring_blocked') {
            updateBanner('Screen check: adjust your display setup to continue.', true);
            applyRiskInputsDisabled(true);
        } else if (state === 'locked') {
            updateBanner('Exam locked by invigilator or policy.', true);
            applyRiskInputsDisabled(true);
        } else if (state === 'auto_submitting') {
            updateBanner('Submitting exam due to proctoring policy…', true);
            applyRiskInputsDisabled(true);
            stopTimer();
            if (!autoSubmitTriggered) {
                autoSubmitTriggered = true;
                void submitExam('auto');
            }
        } else if (state === 'held') {
            updateBanner('Under review — your result is held.', true);
            applyRiskInputsDisabled(true);
            stopTimer();
            syncControlDisabled();
        } else if (state === 'submitted') {
            updateBanner(assignmentMode ? 'Assignment submitted.' : 'Exam submitted.', true);
            applyRiskInputsDisabled(true);
            stopTimer();
            serverDone = true;
            submitInputLock = false;
            stopSubmitPersistence();
            setSaveIndicator('Submitted', true);
            if (els.btnSubmit) {
                els.btnSubmit.disabled = true;
                els.btnSubmit.textContent = submitUiDefaultText;
            }
            syncControlDisabled();
            syncFullscreenGate();
            hideExamTabSwitchModal();
        } else if (state === 'active') {
            if (!serverDone) {
                updateBanner('', false);
                applyRiskInputsDisabled(false);
            }
        }
    });

    function syncFullscreenGate() {
        if (!els.fullscreenGate) {
            return;
        }
        if (assignmentMode || serverDone || !fullscreenGateSupported()) {
            els.fullscreenGate.classList.add('hidden');
            return;
        }
        els.fullscreenGate.classList.toggle('hidden', isExamDocumentFullscreen());
    }

    function hideFullscreenExitNotice() {
        if (fullscreenExitNoticeTimer) {
            clearTimeout(fullscreenExitNoticeTimer);
            fullscreenExitNoticeTimer = null;
        }
        if (els.fullscreenExitNotice) {
            els.fullscreenExitNotice.classList.add('hidden');
            els.fullscreenExitNotice.textContent = '';
        }
    }

    function showFullscreenExitNotice() {
        if (!els.fullscreenExitNotice || serverDone) {
            return;
        }
        els.fullscreenExitNotice.textContent =
            'You left fullscreen. Return to fullscreen when you are ready (exam continues).';
        els.fullscreenExitNotice.classList.remove('hidden');
        if (fullscreenExitNoticeTimer) {
            clearTimeout(fullscreenExitNoticeTimer);
        }
        fullscreenExitNoticeTimer = window.setTimeout(() => hideFullscreenExitNotice(), FULLSCREEN_EXIT_NOTICE_MS);
    }

    function onExamDocumentFullscreenChange() {
        if (isExamDocumentFullscreen()) {
            examDocumentWasFullscreen = true;
            hideFullscreenExitNotice();
            syncFullscreenGate();
            return;
        }
        syncFullscreenGate();
        if (examDocumentWasFullscreen && !serverDone) {
            showFullscreenExitNotice();
        }
    }

    document.addEventListener('fullscreenchange', onExamDocumentFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onExamDocumentFullscreenChange);
    document.addEventListener('mozfullscreenchange', onExamDocumentFullscreenChange);
    document.addEventListener('MSFullscreenChange', onExamDocumentFullscreenChange);

    if (!assignmentMode) {
        window.addEventListener('pageshow', (ev) => {
            if (serverDone) {
                return;
            }
            const pe = /** @type {PageTransitionEvent} */ (ev);
            if (pe.persisted) {
                examDocumentWasFullscreen = isExamDocumentFullscreen();
            }
            requestAnimationFrame(() => {
                syncFullscreenGate();
            });
        });
    }

    if (!assignmentMode) {
        document.addEventListener(
            'click',
            () => {
                if (serverDone || isExamDocumentFullscreen()) {
                    return;
                }
                void requestExamFullscreen();
            },
            true,
        );
        void requestExamFullscreen();
        requestAnimationFrame(() => {
            void requestExamFullscreen();
        });
    }

    syncFullscreenGate();

    const TAB_SWITCH_WARN_MAX = 3;
    let tabSwitchDocWasHidden = false;
    let tabSwitchReturnCount = 0;
    let proctoringOverlayClearing = false;

    function proctoringBlurOverlayCopy(reason) {
        const r = String(reason || '');
        if (r === 'external_display') {
            return 'We logged a possible extra display or extended desktop. Use a single screen or mirror one display only, then continue.';
        }
        if (r === 'face_obstruction') {
            return 'We could not see your face clearly. Adjust your position so your face is unobstructed, then continue.';
        }
        return 'Adjust your screen setup to match your school’s rules, then continue when ready.';
    }

    function syncProctoringBlurOverlay(data) {
        const root = document.getElementById('proctoring-review-overlay');
        const btn = document.getElementById('btn-proctoring-overlay-continue');
        const desc = document.getElementById('proctoring-review-overlay-desc');
        if (!root) {
            return;
        }
        const overlay = data?.proctoring_overlay && typeof data.proctoring_overlay === 'object' ? data.proctoring_overlay : {};
        const active =
            Boolean(overlay.active) ||
            (typeof data?.exam_ui_state === 'string' && data.exam_ui_state === 'proctoring_blocked');
        if (serverDone || !active) {
            root.classList.add('hidden');
            if (btn) {
                btn.disabled = false;
            }
            return;
        }
        if (desc) {
            desc.textContent = proctoringBlurOverlayCopy(overlay.reason);
        }
        root.classList.remove('hidden');
        if (btn) {
            btn.disabled = proctoringOverlayClearing;
        }
    }

    function hideExamTabSwitchModal() {
        document.getElementById('exam-tab-switch-modal')?.classList.add('hidden');
        syncFullscreenGate();
    }

    function ensureExamTabSwitchDots() {
        const host = document.getElementById('exam-tab-switch-dots');
        if (!host || host.childElementCount > 0) {
            return;
        }
        for (let i = 0; i < TAB_SWITCH_WARN_MAX; i += 1) {
            const s = document.createElement('span');
            s.className = 'h-2 w-2 shrink-0 rounded-full bg-slate-200';
            host.appendChild(s);
        }
    }

    function showExamTabSwitchModal() {
        const root = document.getElementById('exam-tab-switch-modal');
        if (!root || assignmentMode || serverDone) {
            return;
        }
        if (els.pauseOverlay && !els.pauseOverlay.classList.contains('hidden')) {
            return;
        }
        tabSwitchReturnCount += 1;
        tabSwitchReturnCount = Math.max(tabSwitchReturnCount, Number(latestPayload?.tab_switch_count) || 0);
        const displayLevel = Math.min(tabSwitchReturnCount, TAB_SWITCH_WARN_MAX);
        ensureExamTabSwitchDots();
        const host = document.getElementById('exam-tab-switch-dots');
        const dots = host?.querySelectorAll('span');
        dots?.forEach((d, i) => {
            d.className = i < displayLevel ? 'h-2 w-2 shrink-0 rounded-full bg-red-500' : 'h-2 w-2 shrink-0 rounded-full bg-slate-200';
        });
        const lvl = document.getElementById('exam-tab-switch-level');
        if (lvl) {
            lvl.textContent = `Warning ${displayLevel} of ${TAB_SWITCH_WARN_MAX}`;
        }
        root.classList.remove('hidden');
    }

    if (!assignmentMode) {
        document.addEventListener('visibilitychange', () => {
            if (assignmentMode || serverDone) {
                return;
            }
            if (document.visibilityState === 'hidden') {
                tabSwitchDocWasHidden = true;
                return;
            }
            if (document.visibilityState === 'visible' && tabSwitchDocWasHidden) {
                tabSwitchDocWasHidden = false;
                showExamTabSwitchModal();
            }
            if (document.visibilityState === 'visible') {
                requestAnimationFrame(() => {
                    syncFullscreenGate();
                });
            }
        });
        document.getElementById('btn-tab-switch-dismiss')?.addEventListener('click', () => {
            hideExamTabSwitchModal();
        });
    }

    document.getElementById('btn-proctoring-overlay-continue')?.addEventListener('click', () => {
        if (serverDone || proctoringOverlayClearing) {
            return;
        }
        void (async () => {
            proctoringOverlayClearing = true;
            const btn = document.getElementById('btn-proctoring-overlay-continue');
            if (btn) {
                btn.disabled = true;
            }
            try {
                await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/proctoring-overlay/clear`, {
                    resolved_reason: 'student_cleared',
                });
                await fetchState();
            } catch {
                updateBanner('Could not clear the screen check. Try again or refresh if this persists.', true);
            } finally {
                proctoringOverlayClearing = false;
                if (btn && !serverDone) {
                    btn.disabled = false;
                }
                syncProctoringBlurOverlay(latestPayload ?? {});
            }
        })();
    });

    els.btnFullscreen?.addEventListener('click', () => {
        if (serverDone) {
            return;
        }
        void requestExamFullscreen();
    });

    els.btnFullscreenGate?.addEventListener('click', () => {
        if (serverDone) {
            return;
        }
        void requestExamFullscreen();
    });

    els.btnSubmit?.addEventListener('click', async () => {
        if (serverDone) {
            return;
        }
        if (!window.confirm('Submit your exam? You cannot undo this.')) {
            return;
        }
        setSubmitButtonSubmitting(true);
        await submitExam('manual');
        if (!serverDone) {
            setSubmitButtonSubmitting(false);
        }
    });

    function stopMicLevelMeter() {
        if (micLevelMeterFrame) {
            cancelAnimationFrame(micLevelMeterFrame);
            micLevelMeterFrame = null;
        }
    }

    /**
     * @param {MediaStream} stream
     */
    function startMicLevelMeter(stream) {
        stopMicLevelMeter();
        const bar = document.getElementById('exam-mic-level-bar');
        const label = document.getElementById('exam-mic-level-label');
        const track = stream.getAudioTracks?.()[0];
        if (!track || !bar) {
            if (label) {
                label.textContent = 'Off';
                label.className = 'text-slate-400';
            }

            return;
        }
        let ctx;
        try {
            ctx = new AudioContext();
        } catch {
            if (label) {
                label.textContent = '—';
            }

            return;
        }
        void ctx.resume().catch(() => {});
        const src = ctx.createMediaStreamSource(stream);
        const analyser = ctx.createAnalyser();
        analyser.fftSize = 256;
        src.connect(analyser);
        const buf = new Uint8Array(analyser.frequencyBinCount);
        const tick = () => {
            micLevelMeterFrame = requestAnimationFrame(tick);
            analyser.getByteFrequencyData(buf);
            let s = 0;
            for (let i = 0; i < buf.length; i += 1) {
                s += buf[i];
            }
            const avg = s / buf.length / 255;
            const pct = Math.min(100, Math.round(avg * 220 + 6));
            bar.style.width = `${pct}%`;
            if (label) {
                if (pct < 12) {
                    label.textContent = 'Quiet';
                    label.className = 'text-emerald-700';
                } else if (pct < 42) {
                    label.textContent = 'Normal';
                    label.className = 'text-emerald-700';
                } else {
                    label.textContent = 'Active';
                    label.className = 'text-amber-700';
                }
            }
        };
        tick();
    }

    function updateProctoringFromState(data) {
        const rs = document.getElementById('proctor-risk-score');
        if (rs && data && typeof data.violation_score === 'number') {
            rs.textContent = String(data.violation_score);
        }
        const risk = String(data?.risk_state ?? 'normal');
        const eye = document.getElementById('proctor-eye-status');
        if (eye) {
            if (risk === 'locked') {
                eye.textContent = 'Locked';
                eye.className = 'text-rose-300';
            } else if (['warning', 'suspicious', 'critical'].includes(risk)) {
                eye.textContent = 'Warning';
                eye.className = 'text-amber-300';
            } else {
                eye.textContent = 'Normal';
                eye.className = 'text-emerald-300';
            }
        }

        if (typeof data?.tab_switch_count === 'number' && !assignmentMode) {
            tabSwitchReturnCount = Math.max(tabSwitchReturnCount, data.tab_switch_count);
            const host = document.getElementById('exam-tab-switch-dots');
            if (host && host.childElementCount > 0) {
                const displayLevel = Math.min(Math.max(1, data.tab_switch_count), TAB_SWITCH_WARN_MAX);
                const dots = host.querySelectorAll('span');
                dots.forEach((d, i) => {
                    d.className =
                        i < displayLevel
                            ? 'h-2 w-2 shrink-0 rounded-full bg-red-500'
                            : 'h-2 w-2 shrink-0 rounded-full bg-slate-200';
                });
                const lvl = document.getElementById('exam-tab-switch-level');
                if (lvl) {
                    lvl.textContent = `Warning ${displayLevel} of ${TAB_SWITCH_WARN_MAX}`;
                }
            }
        }

        syncProctoringBlurOverlay(data);
    }

    function updateLocalProctoringHint(hint) {
        const el = els.localProctorHint;
        const map = {
            ok: 'Face detected — stay centred in the frame.',
            no_face: 'Warning: your face is not visible. Centre yourself in the camera.',
            multiple: 'Warning: more than one face in view. Only you may be on camera.',
            off_center: 'Adjust your position so your face fills the green outline.',
        };
        if (el) {
            el.textContent = map[hint] || '';
        }
        const face = document.getElementById('proctor-face-status');
        if (face) {
            if (hint === 'ok') {
                face.textContent = 'Detected';
            } else if (hint === 'no_face') {
                face.textContent = 'Not visible';
            } else if (hint === 'multiple') {
                face.textContent = 'Multiple';
            } else {
                face.textContent = 'Adjust';
            }
        }
        const eyeLine = document.getElementById('proctor-eye-line');
        if (eyeLine) {
            eyeLine.textContent = hint === 'ok' ? 'Eyes on screen' : 'Stay centred';
        }
    }

    els.btnResume?.addEventListener('click', async () => {
        if (serverDone) {
            return;
        }
        try {
            setSaveIndicator('Resuming…', true);
            await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/resume`);
            await fetchState();
            setSaveIndicator(latestPayload?.session_status === 'active' ? 'Ready' : 'Saved', true);
        } catch {
            setSaveIndicator('Could not resume — try refreshing', false);
            updateBanner('Could not resume this session. Refresh the page or go back to your dashboard.', true);
        }
    });

    window.addEventListener('online', () => {
        if (sessionIsSubmittedForQueue()) {
            return;
        }
        void flushOfflineAnswerQueue().then(() => {
            if (!serverDone) {
                setSaveIndicator('Back online', true);
            }
        });
    });

    try {
        await fetchState();
    } catch {
        updateBanner('Could not load exam state. Check your connection and refresh.', true);
        return;
    }

    window.setInterval(() => void fetchState().catch(() => {}), effectivePollMs);

    let echo = null;
    if (enableLiveSockets) {
        echo = createProctoringEcho();
        if (echo) {
            examStateEngine.attachRealtime(echo, sessionId, axios);
            const conn = echo?.connector?.pusher?.connection;
            if (conn && allowPollingFallback) {
                const onSocketIssue = () => {
                    void fetchState().catch(() => {});
                };
                conn.bind('error', onSocketIssue);
                conn.bind('unavailable', onSocketIssue);
            }
        }
    }

    try {
        if (!assignmentMode) {
            const cap = await fetchProctoringCapability(axios);
            const perf = { ...cap, ...(latestPayload?.proctoring_client || {}) };
            const runtime = new ProctoringRuntimeEngine({
                videoElement: els.video,
                sessionId,
                examId,
                studentId,
                apiClient: axios,
                performanceProfile: perf,
                eventBatcher: proctoringBatcher,
                previewCanvas: requireCameraMonitoring ? els.faceCanvas : null,
                onFramingHint: requireCameraMonitoring ? updateLocalProctoringHint : null,
            });

            if (requireCameraMonitoring) {
                els.videoStatus?.classList?.remove('hidden');
                let stream;
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                } catch {
                    stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                }
                if (els.recordingBadge) {
                    els.recordingBadge.classList.remove('hidden');
                    els.recordingBadge.classList.add('inline-flex');
                }
                startMicLevelMeter(stream);
                if (els.video) {
                    els.video.srcObject = stream;
                    await els.video.play().catch(() => {});
                }
                await runtime.init();
                await postVerificationImageOnce(els.video, sessionId);
            } else {
                await runtime.init({ browserOnly: true });
            }

            runtime.start();
        }
    } catch (e) {
        console.error(e);
        if (!assignmentMode) {
            updateBanner(
                'Camera or proctoring initialization failed. You may continue answering; contact support if issues persist.',
                true,
            );
        }
    } finally {
        if (!assignmentMode) {
            syncFullscreenGate();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => void main());
