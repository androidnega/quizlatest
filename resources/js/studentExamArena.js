/**
 * Student exam runtime — "arena" (gamified) presentation mode.
 *
 * Shares ALL backend behaviour with the classic runtime:
 *   - GET  /exam-sessions/{id}/state              (hydrate questions + session)
 *   - POST /exam-sessions/{id}/answers            (save each answer, debounced)
 *   - POST /exam-sessions/{id}/submit             (final submit)
 *   - POST /exam-sessions/{id}/heartbeat          (keep session alive)
 *   - POST /exam-sessions/{id}/proctoring-events… (tab switches, camera events, etc.)
 *   - POST /exam-sessions/{id}/proctoring-overlay/clear
 *   - POST /exam-sessions/{id}/resume             (from pause)
 *
 * Only the *presentation* differs: a single Kahoot-style card with colored A/B/C/D
 * tiles, a 3-step rail, animated "Answer locked in" sweep, floating camera PiP,
 * and an "Assessment Complete" review screen before submit.
 */

import './preventViewportZoom';
import axios from 'axios';
import {
    ProctoringRuntimeEngine,
    fetchProctoringCapability,
} from './proctoringRuntimeEngine';
import { ProctoringEventBatcher } from './proctoringEventBatcher';
import { ExamStateEngine } from './examStateEngine';
import {
    queuePendingAnswer,
    clearPendingAnswer,
    clearSessionPending,
    listPendingForSession,
} from './examAnswerOfflineQueue';
import { attachEssayAntiClipboard, ESSAY_CLIPBOARD_WARNING_MESSAGE } from './essayAntiClipboard';
import { attachExamIntegritySurface } from './examIntegritySurface';
import { createExamPayloadHydrator } from './examRuntimePayloadHydrator';

const SAVE_DEBOUNCE_MS = 700;
const SAVE_RETRY = 3;
const SUBMIT_RETRIES = 3;
const HEARTBEAT_MS = 25000;
// Brief delay so the student SEES their selection visually settle (dim
// non-selected) before the next question takes over. No interstitial
// overlay, no animated sweep — just a quick visual breath.
const AUTO_ADVANCE_DELAY_MS = 350;
const TAB_SWITCH_WARN_MAX = 3;

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

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
}

function isDocumentFullscreen() {
    const d = document;
    return !!(
        d.fullscreenElement ||
        d.webkitFullscreenElement ||
        d.mozFullScreenElement ||
        d.msFullscreenElement
    );
}

async function requestFullscreen() {
    if (isDocumentFullscreen()) {
        return;
    }
    /** @param {Element | null} node */
    async function tryNode(node) {
        if (!node) return;
        const el = /** @type {any} */ (node);
        for (const fn of [
            'requestFullscreen',
            'webkitRequestFullscreen',
            'mozRequestFullScreen',
            'msRequestFullscreen',
        ]) {
            if (typeof el[fn] === 'function') {
                try {
                    await Promise.resolve(el[fn]());
                } catch {
                    // ignore
                }
                if (isDocumentFullscreen()) return;
            }
        }
    }
    await tryNode(document.documentElement);
    if (!isDocumentFullscreen()) {
        await tryNode(document.getElementById('exam-app'));
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
    if (n > 0) return n;
    const underscores = (q.question_text || '').match(/_{3,}/g);
    if (underscores && underscores.length > 0) return underscores.length;
    return 1;
}

function isAnswerComplete(q, payload) {
    if (!payload || typeof payload !== 'object') return false;
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
        if (blanks.length < n) return false;
        for (let i = 0; i < n; i += 1) {
            if (!String(blanks[i] ?? '').trim()) return false;
        }
        return true;
    }
    if (q.type === 'essay') {
        return String(payload.text ?? '').trim().length > 0;
    }
    return false;
}

function formatMmSs(sec) {
    const safe = Math.max(0, Math.floor(sec || 0));
    const m = Math.floor(safe / 60);
    const s = safe % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/**
 * Continuous progress: just track answered vs unanswered to drive a single
 * top-bar progress fill (no Step 1 / 2 / 3 buckets). Returns the percent
 * answered (0–100) so the UI can paint a one-shot bar.
 */
function computeContinuousProgress(flatQuestions, isQuestionFullyAnswered) {
    if (!flatQuestions.length) return { answered: 0, total: 0, pct: 0 };
    let answered = 0;
    for (const q of flatQuestions) {
        if (q && isQuestionFullyAnswered(q)) answered += 1;
    }
    return {
        answered,
        total: flatQuestions.length,
        pct: Math.round((answered / flatQuestions.length) * 100),
    };
}

/* ---------------- Camera PiP drag + collapse ---------------- */
function attachCameraPipBehaviour(rootId, toggleId) {
    const root = document.getElementById(rootId);
    const toggle = document.getElementById(toggleId);
    if (!root) return;

    let dragging = false;
    let offsetX = 0;
    let offsetY = 0;

    const handle = root.querySelector('[data-pip-handle="1"]');
    if (handle) {
        handle.addEventListener('pointerdown', (ev) => {
            if (ev.target instanceof HTMLElement && ev.target.closest('button')) {
                return;
            }
            dragging = true;
            const rect = root.getBoundingClientRect();
            offsetX = ev.clientX - rect.left;
            offsetY = ev.clientY - rect.top;
            handle.setPointerCapture?.(ev.pointerId);
        });
        handle.addEventListener('pointermove', (ev) => {
            if (!dragging) return;
            const w = root.offsetWidth;
            const h = root.offsetHeight;
            const maxX = window.innerWidth - w - 4;
            const maxY = window.innerHeight - h - 4;
            const left = Math.max(4, Math.min(maxX, ev.clientX - offsetX));
            const top = Math.max(4, Math.min(maxY, ev.clientY - offsetY));
            root.style.left = `${left}px`;
            root.style.top = `${top}px`;
            root.style.right = 'auto';
            root.style.bottom = 'auto';
        });
        const endDrag = (ev) => {
            if (!dragging) return;
            dragging = false;
            handle.releasePointerCapture?.(ev.pointerId);
        };
        handle.addEventListener('pointerup', endDrag);
        handle.addEventListener('pointercancel', endDrag);
    }

    if (toggle) {
        toggle.addEventListener('click', () => {
            const collapsed = root.getAttribute('data-collapsed') === '1';
            root.setAttribute('data-collapsed', collapsed ? '0' : '1');
            toggle.innerHTML = collapsed
                ? '<i class="fa-solid fa-minus" aria-hidden="true"></i>'
                : '<i class="fa-solid fa-plus" aria-hidden="true"></i>';
            toggle.setAttribute('aria-label', collapsed ? 'Minimize camera' : 'Restore camera');
        });
    }
}

/* ---------------- Main ---------------- */
async function main() {
    setupAxios();

    const sessionId = meta('exam-session-id');
    const examId = Number(meta('exam-id'));
    const studentId = Number(meta('student-id'));
    const requireCameraMonitoring = meta('qs-require-camera-monitoring') === '1';
    const examClipboardLock = meta('qs-exam-clipboard-lock') === '1';
    const examScreenshotMitigation = meta('qs-exam-screenshot-mitigation') === '1';
    const examScreenRecordMitigation = meta('qs-exam-screen-record-mitigation') === '1';

    if (!sessionId || !examId) {
        return;
    }

    /* ---- DOM refs ---- */
    const els = {
        loading: document.getElementById('exam-loading'),
        title: document.getElementById('exam-title'),
        subtitle: document.getElementById('exam-subtitle'),
        timer: document.getElementById('exam-timer'),
        timerCard: document.getElementById('arena-q-timer-text'),
        banner: document.getElementById('exam-banner'),
        recordingBadge: document.getElementById('exam-recording-badge'),
        fullscreenGate: document.getElementById('exam-fullscreen-gate'),
        btnFullscreen: document.getElementById('btn-fullscreen'),
        btnFullscreenGate: document.getElementById('btn-fullscreen-gate'),
        fsNotice: document.getElementById('fullscreen-exit-notice'),
        btnSubmit: document.getElementById('btn-submit'),
        questionContainer: document.getElementById('question-container'),
        qStep: document.getElementById('arena-q-step'),
        qText: document.getElementById('arena-q-text'),
        qOptions: document.getElementById('arena-q-options'),
        btnBack: document.getElementById('btn-q-back'),
        btnNext: document.getElementById('btn-q-next'),
        saveIndicator: document.getElementById('save-indicator'),
        progressBar: document.getElementById('arena-progress-bar'),
        progressLabel: document.getElementById('arena-progress-label'),
        completion: document.getElementById('arena-completion'),
        completionList: document.getElementById('arena-completion-list'),
        btnArenaSubmit: document.getElementById('btn-arena-submit'),
        btnArenaReview: document.getElementById('btn-arena-review'),
        tabSwitchModal: document.getElementById('exam-tab-switch-modal'),
        tabSwitchDots: document.getElementById('exam-tab-switch-dots'),
        tabSwitchLevel: document.getElementById('exam-tab-switch-level'),
        btnTabSwitchDismiss: document.getElementById('btn-tab-switch-dismiss'),
        pauseOverlay: document.getElementById('exam-timer-pause-overlay'),
        btnResume: document.getElementById('btn-exam-resume'),
        reviewOverlay: document.getElementById('proctoring-review-overlay'),
        btnReviewContinue: document.getElementById('btn-proctoring-overlay-continue'),
        reviewDesc: document.getElementById('proctoring-review-overlay-desc'),
        clipboardToast: document.getElementById('essay-clipboard-toast'),
        proctoringVideo: document.getElementById('proctoring-video'),
        proctoringCanvas: document.getElementById('proctoring-face-canvas'),
        proctoringHint: document.getElementById('proctoring-local-hint'),
        proctoringEye: document.getElementById('proctor-eye-status'),
        proctoringFace: document.getElementById('proctor-face-status'),
        micBar: document.getElementById('arena-mic-bar'),
        micLabel: document.getElementById('arena-mic-label'),
        feedDot: document.getElementById('arena-feed-dot'),
        feedLabel: document.getElementById('arena-feed-label'),
    };

    /* ---- Shared infra ---- */
    const proctoringBatcher = new ProctoringEventBatcher({
        examSessionKey: sessionId,
        apiClient: axios,
        flushIntervalMs: 4500,
        maxBatch: 14,
        onFlushResult: (data) => applyTabSwitchServerDecision(data),
    });

    void attachExamIntegritySurface({
        root: document.getElementById('exam-app'),
        assignmentMode: false,
        clipboardLock: examClipboardLock,
        screenshotMitigation: examScreenshotMitigation,
        screenRecordMitigation: examScreenRecordMitigation,
        enqueueSignal: enqueueExamIntegritySignal,
    });

    const examStateEngine = new ExamStateEngine();
    examStateEngine.configureApi(axios);
    examStateEngine.sessionRouteKey = sessionId;

    /* ---- State ---- */
    let flatQuestions = [];
    let currentIdx = 0;
    let furthestIdx = 0;
    let showingCompletion = false;
    let latestPayload = null;
    let micMeterFrame = null;
    let micAudioContext = null;
    /** @type {Map<number, any>} */
    const lastLocalPayload = new Map();
    /** @type {Map<number, number>} */
    const questionRevision = new Map();
    /** @type {Map<number, ReturnType<typeof setTimeout>>} */
    const debouncers = new Map();
    /** @type {Map<number, () => any>} */
    const pendingSaveBuilders = new Map();
    let serverDone = false;
    let submitInputLock = false;
    let riskInputsDisabled = false;
    let timerPaused = false;
    let serverSkewMs = 0;
    let examEndAtMs = null;
    let timedExam = false;
    let timerHandle = null;
    let timeoutSubmitFired = false;
    let heartbeatHandle = null;
    let autoSubmitTriggered = false;
    let tabSwitchDocWasHidden = false;
    let tabSwitchReturnCount = 0;
    let tabSwitchRecordInFlight = false;
    let autoAdvanceTimer = null;
    let clipboardToastTimer = null;
    let proctoringOverlayClearing = false;
    let documentWasFullscreen = false;

    /* ---- Helpers ---- */
    function getEffectivePayload(q) {
        if (lastLocalPayload.has(q.id)) {
            return lastLocalPayload.get(q.id);
        }
        return latestPayload?.saved_answers?.[String(q.id)]?.answer_payload ?? null;
    }
    function isQuestionFullyAnswered(q) {
        return isAnswerComplete(q, getEffectivePayload(q));
    }
    function countAnsweredInRange(startIdx, endIdx) {
        let n = 0;
        for (let i = startIdx; i <= endIdx; i += 1) {
            const q = flatQuestions[i];
            if (q && isQuestionFullyAnswered(q)) {
                n += 1;
            }
        }
        return n;
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
        const next = fi >= flatQuestions.length ? flatQuestions.length - 1 : fi;
        if (next > furthestIdx) {
            furthestIdx = next;
        }
    }
    function pruneLocalPayloadFromServer() {
        const sa = latestPayload?.saved_answers;
        if (!sa || typeof sa !== 'object') return;
        for (const q of flatQuestions) {
            const server = sa[String(q.id)]?.answer_payload;
            if (!isAnswerComplete(q, server)) continue;
            const loc = lastLocalPayload.get(q.id);
            if (loc === undefined) continue;
            if (isAnswerComplete(q, loc)) {
                lastLocalPayload.delete(q.id);
            }
        }
    }
    function effectiveInputsLocked() {
        return serverDone || riskInputsDisabled || submitInputLock || timerPaused;
    }
    function updateBanner(msg, show) {
        if (!els.banner) return;
        if (!show || !msg) {
            els.banner.style.display = 'none';
            els.banner.textContent = '';
            return;
        }
        els.banner.style.display = 'block';
        els.banner.textContent = msg;
    }
    function setSaveIndicator(text, ok = true) {
        if (!els.saveIndicator) return;
        els.saveIndicator.textContent = text;
        els.saveIndicator.style.color = ok ? '#64748b' : '#dc2626';
    }

    /* ---- Timer ---- */
    function syncTimerAnchors(payload) {
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
        if (serverDone || !timedExam || examEndAtMs === null) return null;
        const serverNow = Date.now() + serverSkewMs;
        return Math.max(0, Math.floor((examEndAtMs - serverNow) / 1000));
    }
    function renderTimer(sec) {
        const txt = sec === null ? '--:--' : formatMmSs(sec);
        if (els.timer) els.timer.textContent = txt;
        if (els.timerCard) els.timerCard.textContent = txt;
    }
    function stopTimer() {
        if (timerHandle) {
            window.clearInterval(timerHandle);
            timerHandle = null;
        }
    }
    function startTimer() {
        stopTimer();
        timerHandle = window.setInterval(() => {
            if (serverDone || timerPaused) {
                stopTimer();
                return;
            }
            const sec = computeRemainingSeconds();
            if (sec === null) return;
            renderTimer(sec);
            if (sec <= 0 && !timeoutSubmitFired) {
                timeoutSubmitFired = true;
                stopTimer();
                void submitExam('timeout');
            }
        }, 1000);
    }

    /* ---- Heartbeat ---- */
    function startHeartbeat() {
        if (heartbeatHandle) return;
        heartbeatHandle = window.setInterval(() => {
            if (serverDone || timerPaused) return;
            void axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/heartbeat`).catch(() => {});
        }, HEARTBEAT_MS);
    }
    function stopHeartbeat() {
        if (heartbeatHandle) {
            window.clearInterval(heartbeatHandle);
            heartbeatHandle = null;
        }
    }

    /* ---- Proctoring signals (clipboard / integrity / tab switch) ---- */
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
        if (signal === 'printscreen_key' || signal === 'capture_shortcut') {
            void proctoringBatcher
                .enqueue({
                    event_type: 'possible_screenshot_attempt',
                    flagged: true,
                    metadata: {
                        ...md,
                        keys: signal,
                        detection_note: 'Best-effort only; browsers cannot detect every screenshot.',
                    },
                })
                .catch(() => {});
        }
        if (signal === 'screen_record_shortcut' || signal === 'display_capture_request') {
            void proctoringBatcher
                .enqueue({
                    event_type: 'possible_screen_record_attempt',
                    flagged: true,
                    metadata: {
                        ...md,
                        source: signal,
                        detection_note: 'Best-effort only; OS-level recorders cannot be fully detected.',
                    },
                })
                .catch(() => {});
        }
    }
    function showEssayClipboardWarning() {
        const toast = els.clipboardToast;
        if (!toast) return;
        toast.textContent = ESSAY_CLIPBOARD_WARNING_MESSAGE;
        toast.classList.remove('qs-arena__hidden');
        if (clipboardToastTimer) window.clearTimeout(clipboardToastTimer);
        clipboardToastTimer = window.setTimeout(() => {
            toast.classList.add('qs-arena__hidden');
            clipboardToastTimer = null;
        }, 3200);
    }

    /* ---- Save / submit ---- */
    async function postAnswerOnce(questionId, payload, revision) {
        const { data } = await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/answers`, {
            question_id: questionId,
            answer_payload: payload,
            client_revision: revision,
        });
        if (data?.status === 'noop' && data?.reason === 'stale_revision') {
            const r = Number(data.client_revision);
            if (Number.isFinite(r)) {
                const cur = questionRevision.get(questionId) ?? 0;
                if (r > cur) questionRevision.set(questionId, r);
            }
            payloadHydrator.invalidateAnswers();
            return 'stale';
        }
        const r = Number(data?.client_revision);
        if (Number.isFinite(r)) {
            const cur = questionRevision.get(questionId) ?? 0;
            if (r > cur) questionRevision.set(questionId, r);
        }
        // Architecture Review Phase 1+4: a successful save advances the
        // /answers ETag — invalidate so the next hydrate pulls the
        // freshest map (almost always 304).
        payloadHydrator.invalidateAnswers();
        return 'saved';
    }
    async function flushOfflineQueue() {
        if (!navigator.onLine || serverDone) return;
        const pending = await listPendingForSession(sessionId);
        pending.sort((a, b) => a.questionId - b.questionId);
        for (const row of pending) {
            if (serverDone) return;
            let ok = false;
            for (let a = 0; a < SAVE_RETRY && !ok; a += 1) {
                try {
                    const outcome = await postAnswerOnce(row.questionId, row.payload, row.revision);
                    if (outcome === 'saved' || outcome === 'stale') ok = true;
                } catch {
                    await new Promise((r) => setTimeout(r, 400 * (a + 1)));
                }
            }
            if (ok) {
                await clearPendingAnswer(sessionId, row.questionId);
            }
        }
    }
    async function sendAnswerWithQueue(questionId, payload) {
        if (serverDone) return true;
        const rev = (questionRevision.get(questionId) ?? 0) + 1;
        questionRevision.set(questionId, rev);
        let attempt = 0;
        while (attempt < SAVE_RETRY) {
            if (serverDone) return true;
            try {
                const outcome = await postAnswerOnce(questionId, payload, rev);
                if (outcome === 'saved' || outcome === 'stale') {
                    await clearPendingAnswer(sessionId, questionId);
                    await flushOfflineQueue();
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
    function scheduleSave(questionId, buildPayload) {
        pendingSaveBuilders.set(questionId, buildPayload);
        const prev = debouncers.get(questionId);
        if (prev) window.clearTimeout(prev);
        setSaveIndicator('Saving…', true);
        debouncers.set(
            questionId,
            window.setTimeout(async () => {
                pendingSaveBuilders.delete(questionId);
                const payload = buildPayload();
                if (!payload) return;
                const ok = await sendAnswerWithQueue(questionId, payload);
                setSaveIndicator(ok ? 'Saved ✓' : 'Offline — will retry', ok);
            }, SAVE_DEBOUNCE_MS),
        );
    }
    async function flushDebouncerFor(questionId) {
        const prev = debouncers.get(questionId);
        if (prev) {
            window.clearTimeout(prev);
            debouncers.delete(questionId);
        }
        const build = pendingSaveBuilders.get(questionId);
        if (!build) return;
        pendingSaveBuilders.delete(questionId);
        const payload = build();
        if (!payload) return;
        setSaveIndicator('Saving…', true);
        const ok = await sendAnswerWithQueue(questionId, payload);
        setSaveIndicator(ok ? 'Saved ✓' : 'Offline — will retry', ok);
    }

    /* ---- Continuous progress bar (single flow — no Step 1/2/3) ---- */
    function renderProgress() {
        const stats = computeContinuousProgress(flatQuestions, isQuestionFullyAnswered);
        if (els.progressBar) {
            els.progressBar.style.width = `${stats.pct}%`;
            els.progressBar.setAttribute('aria-valuenow', String(stats.answered));
            els.progressBar.setAttribute('aria-valuemax', String(stats.total));
        }
        if (els.progressLabel) {
            els.progressLabel.textContent = `${stats.answered}/${stats.total}`;
        }
    }

    /* ---- Render: question card ---- */
    function renderQuestion() {
        if (!els.questionContainer || !flatQuestions.length) {
            return;
        }
        const q = flatQuestions[currentIdx];
        if (!q) return;
        els.questionContainer.classList.remove('qs-arena__hidden');
        if (els.completion) els.completion.classList.add('qs-arena__hidden');

        if (els.qStep) {
            els.qStep.textContent = `Question ${currentIdx + 1} of ${flatQuestions.length}`;
        }
        if (els.qText) {
            els.qText.innerHTML = escapeHtml(q.question_text || '').replace(/\n/g, '<br/>');
        }
        renderOptions(q);
        if (els.btnBack) els.btnBack.disabled = currentIdx <= 0;
        if (els.btnNext) {
            els.btnNext.disabled = false;
            const onLast = currentIdx >= flatQuestions.length - 1;
            els.btnNext.dataset.qLast = onLast ? '1' : '0';
            els.btnNext.innerHTML = onLast
                ? '<span>Review answers</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>'
                : '<span>Next</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>';
        }
        syncControlsDisabled();
        renderProgress();
    }

    function renderOptions(q) {
        if (!els.qOptions) return;
        els.qOptions.innerHTML = '';
        const saved = getEffectivePayload(q);

        if (q.type === 'mcq') {
            const opts = Array.isArray(q.options) ? q.options : [];
            els.qOptions.dataset.count = String(opts.length);
            const selected = new Set(
                Array.isArray(saved?.selected)
                    ? saved.selected
                    : saved?.selected != null
                      ? [saved.selected]
                      : [],
            );
            opts.forEach((label, idx) => {
                const letter = String.fromCharCode(65 + idx);
                const tone = ['A', 'B', 'C', 'D'][idx % 4];
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'qs-arena__opt';
                btn.dataset.tone = tone;
                btn.dataset.letter = letter;
                btn.setAttribute('aria-pressed', selected.has(idx) ? 'true' : 'false');
                btn.disabled = effectiveInputsLocked();
                if (selected.size > 0) {
                    btn.dataset.state = selected.has(idx) ? 'selected' : 'dim';
                }

                const ltrChip = document.createElement('span');
                ltrChip.className = 'qs-arena__opt-letter';
                ltrChip.textContent = letter;
                const decoTL = document.createElement('span');
                decoTL.className = 'qs-arena__opt-deco qs-arena__opt-deco--tl';
                const decoBR = document.createElement('span');
                decoBR.className = 'qs-arena__opt-deco qs-arena__opt-deco--br';
                const txt = document.createElement('span');
                txt.className = 'qs-arena__opt-text';
                txt.textContent = String(label ?? '');

                btn.append(ltrChip, decoTL, decoBR, txt);
                btn.addEventListener('click', () => onMcqSelect(q, idx, btn));
                els.qOptions.appendChild(btn);
            });
        } else if (q.type === 'true_false') {
            els.qOptions.dataset.count = '2';
            const choices = [
                { value: true, label: 'True', tone: 'D' },
                { value: false, label: 'False', tone: 'A' },
            ];
            choices.forEach(({ value, label, tone }) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'qs-arena__opt';
                btn.dataset.tone = tone;
                btn.disabled = effectiveInputsLocked();
                const on = saved?.value === value;
                if (saved && (saved.value === true || saved.value === false)) {
                    btn.dataset.state = on ? 'selected' : 'dim';
                }
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                const ltr = document.createElement('span');
                ltr.className = 'qs-arena__opt-letter';
                ltr.textContent = label.charAt(0);
                const txt = document.createElement('span');
                txt.className = 'qs-arena__opt-text';
                txt.textContent = label;
                btn.append(ltr, txt);
                btn.addEventListener('click', () => onTrueFalseSelect(q, value, btn));
                els.qOptions.appendChild(btn);
            });
        } else if (q.type === 'fill_blank') {
            els.qOptions.dataset.count = '1';
            const wrap = document.createElement('div');
            wrap.className = 'qs-arena__textstack';
            const n = fillBlankCount(q);
            const blanks = Array.isArray(saved?.blanks) ? saved.blanks : [];
            for (let i = 0; i < n; i += 1) {
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.className = 'qs-arena__textfield';
                inp.placeholder = n === 1 ? 'Type your answer…' : `Blank ${i + 1}`;
                inp.value = blanks[i] ?? '';
                inp.dataset.blank = String(i);
                inp.disabled = effectiveInputsLocked();
                inp.addEventListener('input', () => onFillBlankInput(q, wrap));
                wrap.appendChild(inp);
            }
            els.qOptions.appendChild(wrap);
        } else if (q.type === 'essay') {
            els.qOptions.dataset.count = '1';
            const ta = document.createElement('textarea');
            ta.className = 'qs-arena__textfield qs-arena__textarea';
            ta.placeholder = 'Type your response…';
            ta.value = saved?.text ?? '';
            ta.disabled = effectiveInputsLocked();
            ta.addEventListener('input', () => onEssayInput(q, ta));
            if (examClipboardLock) {
                attachEssayAntiClipboard(ta, {
                    showWarning: showEssayClipboardWarning,
                    onBlocked: (actionType) =>
                        proctoringBatcher
                            .enqueue({
                                event_type: 'essay_clipboard_attempt',
                                flagged: false,
                                metadata: {
                                    session_id: sessionId,
                                    student_id: studentId,
                                    exam_id: examId,
                                    question_id: q.id,
                                    action_type: actionType,
                                },
                            })
                            .catch(() => {}),
                });
            }
            els.qOptions.appendChild(ta);
        }
    }

    function onMcqSelect(q, idx, btn) {
        if (effectiveInputsLocked()) return;
        const saved = getEffectivePayload(q);
        const prev = new Set(
            Array.isArray(saved?.selected)
                ? saved.selected
                : saved?.selected != null
                  ? [saved.selected]
                  : [],
        );
        const multi = Boolean(q.allow_multiple);
        let next;
        if (multi) {
            const nextSet = new Set(prev);
            if (nextSet.has(idx)) nextSet.delete(idx);
            else nextSet.add(idx);
            next = Array.from(nextSet).sort((a, b) => a - b);
        } else {
            next = [idx];
        }
        const payload = { type: 'mcq', selected: next };
        lastLocalPayload.set(q.id, payload);
        // Visually dim non-selected immediately for a Kahoot-style "lock-in" effect.
        els.qOptions?.querySelectorAll('.qs-arena__opt').forEach((node, i) => {
            const isSelected = next.includes(i);
            node.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            node.dataset.state = isSelected ? 'selected' : 'dim';
        });
        if (multi) {
            scheduleSave(q.id, () => payload);
            renderProgress();
        } else {
            // Single-select MCQ: save and quietly advance to the next
            // question. No "Answer locked in" interstitial — the colored
            // selection is the feedback.
            void (async () => {
                setSaveIndicator('Saving…', true);
                const ok = await sendAnswerWithQueue(q.id, payload);
                setSaveIndicator(ok ? 'Saved ✓' : 'Offline — will retry', ok);
                refreshFrontierFromAnswers();
                renderProgress();
                scheduleAutoAdvance();
            })();
        }
    }

    function onTrueFalseSelect(q, value) {
        if (effectiveInputsLocked()) return;
        const payload = { type: 'true_false', value };
        lastLocalPayload.set(q.id, payload);
        els.qOptions?.querySelectorAll('.qs-arena__opt').forEach((node) => {
            const isFalse = node.dataset.tone === 'A';
            const matches = (isFalse && value === false) || (!isFalse && value === true);
            node.setAttribute('aria-pressed', matches ? 'true' : 'false');
            node.dataset.state = matches ? 'selected' : 'dim';
        });
        void (async () => {
            setSaveIndicator('Saving…', true);
            const ok = await sendAnswerWithQueue(q.id, payload);
            setSaveIndicator(ok ? 'Saved ✓' : 'Offline — will retry', ok);
            refreshFrontierFromAnswers();
            renderProgress();
            scheduleAutoAdvance();
        })();
    }

    function onFillBlankInput(q, wrap) {
        const vals = [];
        wrap.querySelectorAll('input[data-blank]').forEach((node) => {
            vals.push(node.value);
        });
        const payload = { type: 'fill_blank', blanks: vals };
        lastLocalPayload.set(q.id, payload);
        scheduleSave(q.id, () => payload);
        renderProgress();
    }

    function onEssayInput(q, ta) {
        const payload = { type: 'essay', text: ta.value };
        lastLocalPayload.set(q.id, payload);
        scheduleSave(q.id, () => payload);
        renderProgress();
    }

    function scheduleAutoAdvance() {
        if (autoAdvanceTimer) {
            window.clearTimeout(autoAdvanceTimer);
            autoAdvanceTimer = null;
        }
        autoAdvanceTimer = window.setTimeout(() => {
            autoAdvanceTimer = null;
            if (showingCompletion || effectiveInputsLocked()) return;
            advanceQuestion();
        }, AUTO_ADVANCE_DELAY_MS);
    }

    function syncControlsDisabled() {
        const locked = effectiveInputsLocked();
        els.qOptions
            ?.querySelectorAll('button, input, textarea')
            .forEach((el) => {
                el.disabled = locked;
            });
        if (els.btnBack) els.btnBack.disabled = locked || currentIdx <= 0;
        if (els.btnNext) els.btnNext.disabled = locked;
        if (els.btnSubmit) els.btnSubmit.disabled = serverDone || riskInputsDisabled || submitInputLock;
        if (els.btnArenaSubmit) els.btnArenaSubmit.disabled = serverDone || riskInputsDisabled || submitInputLock;
    }

    /* ---- Navigation ---- */
    function canNavigateTo(i) {
        if (!Number.isFinite(i) || i < 0 || i >= flatQuestions.length) return false;
        return i <= furthestIdx;
    }
    function goToQuestion(i) {
        if (!canNavigateTo(i)) return;
        if (i !== currentIdx) {
            void flushDebouncerFor(flatQuestions[currentIdx]?.id);
        }
        currentIdx = i;
        showingCompletion = false;
        renderQuestion();
    }
    function advanceQuestion() {
        if (effectiveInputsLocked()) return;
        const q = flatQuestions[currentIdx];
        if (q) void flushDebouncerFor(q.id);
        if (currentIdx >= flatQuestions.length - 1) {
            showCompletionView();
            return;
        }
        currentIdx += 1;
        if (currentIdx > furthestIdx) furthestIdx = currentIdx;
        pruneLocalPayloadFromServer();
        refreshFrontierFromAnswers();
        renderQuestion();
    }
    function retreatQuestion() {
        if (currentIdx <= 0) return;
        void flushDebouncerFor(flatQuestions[currentIdx]?.id);
        currentIdx -= 1;
        showingCompletion = false;
        renderQuestion();
    }

    /* ---- Completion view (continuous grid; no step buckets) ---- */
    function showCompletionView() {
        showingCompletion = true;
        if (els.questionContainer) els.questionContainer.classList.add('qs-arena__hidden');
        if (!els.completion || !els.completionList) return;
        els.completion.classList.remove('qs-arena__hidden');
        els.completionList.innerHTML = '';
        const frag = document.createDocumentFragment();
        flatQuestions.forEach((q, i) => {
            const li = document.createElement('li');
            li.className = 'qs-arena__completion-cell';
            const answered = q && isQuestionFullyAnswered(q);
            li.dataset.answered = answered ? '1' : '0';
            const label = document.createElement('span');
            label.className = 'qs-arena__completion-cell-num';
            label.textContent = String(i + 1);
            const icon = document.createElement('i');
            icon.className = answered ? 'fa-solid fa-check' : 'fa-regular fa-circle';
            icon.setAttribute('aria-hidden', 'true');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'qs-arena__completion-cell-btn';
            btn.appendChild(icon);
            btn.appendChild(label);
            btn.addEventListener('click', () => {
                showQuestionView();
                goToQuestion(Math.min(i, furthestIdx));
            });
            li.appendChild(btn);
            frag.appendChild(li);
        });
        els.completionList.appendChild(frag);
    }
    function showQuestionView() {
        showingCompletion = false;
        if (els.completion) els.completion.classList.add('qs-arena__hidden');
        if (els.questionContainer) els.questionContainer.classList.remove('qs-arena__hidden');
        renderQuestion();
    }

    /* ---- Submit ---- */
    async function submitExam(reason = 'manual') {
        if (serverDone) return;
        submitInputLock = true;
        syncControlsDisabled();
        setSaveIndicator(reason === 'timeout' ? 'Time expired — submitting…' : 'Submitting…', true);

        // Flush any pending answer save before submit.
        for (const qid of Array.from(debouncers.keys())) {
            await flushDebouncerFor(qid);
        }

        for (let attempt = 1; attempt <= SUBMIT_RETRIES; attempt += 1) {
            try {
                const { data } = await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/submit`);
                if (data?.status === 'submitted') {
                    serverDone = true;
                    submitInputLock = false;
                    stopTimer();
                    stopHeartbeat();
                    void clearSessionPending(sessionId);
                    hideTabSwitchModal();
                    window.location.assign(`/student/exam/${encodeURIComponent(sessionId)}/submitted`);
                    return;
                }
            } catch {
                await new Promise((r) => setTimeout(r, 400 * attempt));
            }
        }
        updateBanner('Submission failed. Stay on this page; we are retrying in the background.', true);
        setSaveIndicator('Still submitting…', false);
    }

    function confirmAndSubmit() {
        if (serverDone) return;
        const unanswered = flatQuestions.reduce(
            (n, q) => (q && !isQuestionFullyAnswered(q) ? n + 1 : n),
            0,
        );
        const base = 'Submit your assessment? You cannot undo this.';
        const msg = unanswered > 0
            ? `${unanswered} question${unanswered === 1 ? '' : 's'} unanswered. ${base}`
            : base;
        if (!window.confirm(msg)) return;
        void submitExam('manual');
    }

    /* ---- Fullscreen gate + exit notice ---- */
    function syncFullscreenGate() {
        if (!els.fullscreenGate) return;
        if (serverDone) {
            els.fullscreenGate.classList.add('hidden');
            return;
        }
        els.fullscreenGate.classList.toggle('hidden', isDocumentFullscreen());
    }
    function showFsExitNotice() {
        if (!els.fsNotice || serverDone) return;
        els.fsNotice.textContent = 'Left fullscreen — return when ready (exam continues).';
        els.fsNotice.style.display = 'inline';
        window.setTimeout(() => {
            if (els.fsNotice) els.fsNotice.style.display = 'none';
        }, 7000);
    }
    function onFullscreenChange() {
        if (isDocumentFullscreen()) {
            documentWasFullscreen = true;
            if (els.fsNotice) els.fsNotice.style.display = 'none';
            syncFullscreenGate();
            return;
        }
        syncFullscreenGate();
        if (documentWasFullscreen && !serverDone) {
            showFsExitNotice();
        }
    }
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    document.addEventListener('mozfullscreenchange', onFullscreenChange);
    document.addEventListener('MSFullscreenChange', onFullscreenChange);

    /* ---- Tab switch handling ---- */
    function ensureTabSwitchDots() {
        if (!els.tabSwitchDots || els.tabSwitchDots.childElementCount > 0) return;
        for (let i = 0; i < TAB_SWITCH_WARN_MAX; i += 1) {
            const s = document.createElement('span');
            els.tabSwitchDots.appendChild(s);
        }
    }
    function showTabSwitchModal() {
        if (!els.tabSwitchModal || serverDone) return;
        const lvl = Math.min(Math.max(tabSwitchReturnCount, 1), TAB_SWITCH_WARN_MAX);
        ensureTabSwitchDots();
        els.tabSwitchDots?.querySelectorAll('span').forEach((d, i) => {
            d.dataset.on = i < lvl ? '1' : '0';
        });
        if (els.tabSwitchLevel) els.tabSwitchLevel.textContent = `Warning ${lvl} of ${TAB_SWITCH_WARN_MAX}`;
        els.tabSwitchModal.classList.remove('qs-arena__hidden');
    }
    function hideTabSwitchModal() {
        els.tabSwitchModal?.classList.add('qs-arena__hidden');
    }
    function applyTabSwitchServerDecision(data) {
        if (!data || typeof data !== 'object' || serverDone) return;
        if (typeof data.tab_switch_count === 'number') {
            tabSwitchReturnCount = Math.max(0, data.tab_switch_count);
        }
        if (data.status === 'submitted_held') {
            updateBanner(
                typeof data.message === 'string' && data.message
                    ? data.message
                    : 'Your assessment has been submitted because you left the screen three times.',
                true,
            );
            serverDone = true;
            stopTimer();
            stopHeartbeat();
            hideTabSwitchModal();
            void fetchState().catch(() => {});
            return;
        }
        if (typeof data.client_message === 'string' && data.client_message !== '') {
            updateBanner(data.client_message, true);
        }
        if (data.status === 'logged' && typeof data.tab_switch_count === 'number') {
            showTabSwitchModal();
        }
    }
    async function recordTabSwitchStrike() {
        if (serverDone || tabSwitchRecordInFlight) return;
        tabSwitchRecordInFlight = true;
        try {
            await proctoringBatcher.enqueue({
                event_type: 'tab_switch',
                flagged: true,
                metadata: { session_id: sessionId, student_id: studentId, exam_id: examId },
            });
            const data = await proctoringBatcher.flush();
            applyTabSwitchServerDecision(data);
        } catch {
            updateBanner('Could not record leaving the exam page. Please stay on this tab.', true);
        } finally {
            tabSwitchRecordInFlight = false;
        }
    }
    document.addEventListener('visibilitychange', () => {
        if (serverDone) return;
        if (document.visibilityState === 'hidden') {
            tabSwitchDocWasHidden = true;
            return;
        }
        if (document.visibilityState === 'visible' && tabSwitchDocWasHidden) {
            tabSwitchDocWasHidden = false;
            void recordTabSwitchStrike();
        }
    });
    window.addEventListener('blur', () => {
        if (serverDone) return;
        tabSwitchDocWasHidden = true;
    });
    window.addEventListener('focus', () => {
        if (serverDone || !tabSwitchDocWasHidden) return;
        tabSwitchDocWasHidden = false;
        void recordTabSwitchStrike();
    });

    /* ---- Pause overlay ---- */
    function syncPauseOverlay(show) {
        if (!els.pauseOverlay) return;
        els.pauseOverlay.classList.toggle('qs-arena__hidden', !show);
    }
    function applyTimerPausedFromState(data) {
        if (data?.session_status === 'submitted') {
            syncPauseOverlay(false);
            stopHeartbeat();
            return;
        }
        const next = data?.timer_paused === true || data?.session_status === 'paused';
        if (next !== timerPaused) {
            timerPaused = next;
            if (timerPaused) {
                stopTimer();
                stopHeartbeat();
                updateBanner('Exam paused — your timer is frozen. Press Resume when you are ready.', true);
            } else {
                updateBanner('', false);
                startHeartbeat();
            }
        }
        syncPauseOverlay(timerPaused);
        const sec = computeRemainingSeconds();
        renderTimer(timerPaused ? Number(data?.time_remaining_seconds ?? sec) : sec);
        syncControlsDisabled();
    }
    els.btnResume?.addEventListener('click', async () => {
        try {
            await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/resume`);
            await fetchState();
        } catch {
            updateBanner('Could not resume — refresh the page if this persists.', true);
        }
    });

    /* ---- Proctoring overlay (screen check) ---- */
    function syncProctoringOverlay(data) {
        if (!els.reviewOverlay) return;
        const overlay = data?.proctoring_overlay && typeof data.proctoring_overlay === 'object'
            ? data.proctoring_overlay
            : {};
        const active = Boolean(overlay.active)
            || (typeof data?.exam_ui_state === 'string' && data.exam_ui_state === 'proctoring_blocked');
        if (serverDone || !active) {
            els.reviewOverlay.classList.add('qs-arena__hidden');
            if (els.btnReviewContinue) els.btnReviewContinue.disabled = false;
            return;
        }
        if (els.reviewDesc) {
            const r = String(overlay.reason || '');
            els.reviewDesc.textContent = r === 'external_display'
                ? 'We logged a possible extra display or extended desktop. Use a single screen, then continue.'
                : r === 'face_obstruction'
                  ? 'We could not see your face clearly. Adjust your position so your face is unobstructed, then continue.'
                  : 'Adjust your screen setup to match your school’s rules, then continue when ready.';
        }
        els.reviewOverlay.classList.remove('qs-arena__hidden');
        if (els.btnReviewContinue) els.btnReviewContinue.disabled = proctoringOverlayClearing;
    }
    els.btnReviewContinue?.addEventListener('click', () => {
        if (serverDone || proctoringOverlayClearing) return;
        proctoringOverlayClearing = true;
        if (els.btnReviewContinue) els.btnReviewContinue.disabled = true;
        void (async () => {
            try {
                await axios.post(
                    `/exam-sessions/${encodeURIComponent(sessionId)}/proctoring-overlay/clear`,
                    { resolved_reason: 'student_cleared' },
                );
                await fetchState();
            } catch {
                updateBanner('Could not clear the screen check. Try again or refresh.', true);
            } finally {
                proctoringOverlayClearing = false;
                if (els.btnReviewContinue && !serverDone) els.btnReviewContinue.disabled = false;
                syncProctoringOverlay(latestPayload ?? {});
            }
        })();
    });

    /* ---- State engine listener for risk transitions ---- */
    examStateEngine.subscribe(({ state, payload }) => {
        const merged = { ...(latestPayload ?? {}), ...(payload ?? {}) };
        syncProctoringOverlay(merged);
        if (state === 'warning') {
            updateBanner('Warning: please stay focused on the exam.', true);
            if (!serverDone) riskInputsDisabled = false;
        } else if (state === 'proctoring_blocked') {
            updateBanner('Screen check: adjust your display setup to continue.', true);
            riskInputsDisabled = true;
        } else if (state === 'locked') {
            updateBanner('Exam locked by invigilator or policy.', true);
            riskInputsDisabled = true;
        } else if (state === 'auto_submitting') {
            updateBanner('Submitting exam due to proctoring policy…', true);
            riskInputsDisabled = true;
            stopTimer();
            if (!autoSubmitTriggered) {
                autoSubmitTriggered = true;
                void submitExam('auto');
            }
        } else if (state === 'held') {
            updateBanner('Under review — your result is held.', true);
            riskInputsDisabled = true;
            stopTimer();
        } else if (state === 'submitted') {
            updateBanner('Exam submitted.', true);
            riskInputsDisabled = true;
            stopTimer();
            serverDone = true;
            submitInputLock = false;
            hideTabSwitchModal();
        } else if (state === 'active') {
            if (!serverDone) {
                updateBanner('', false);
                riskInputsDisabled = false;
            }
        }
        syncControlsDisabled();
    });

    /* ---- Fetch state ---- */
    // Architecture Review Phase 1+4: hydrator merges /state (volatile)
    // + /exam-structure (cached, ETag-revalidated) + /answers (cached,
    // ETag-revalidated) into a single combined payload of the same
    // shape as the legacy /state response.
    const payloadHydrator = createExamPayloadHydrator(sessionId);

    async function fetchState() {
        const data = await payloadHydrator.hydrate();
        latestPayload = data;
        examStateEngine.syncFromBackend(data);

        if (data?.exam?.title && els.title) els.title.textContent = data.exam.title;
        if (els.subtitle && data?.exam) {
            const c = data.exam.course;
            if (c && (c.code || c.title)) {
                els.subtitle.textContent = [c.code, c.title].filter(Boolean).join(' · ');
            } else if (data.exam.description) {
                els.subtitle.textContent = data.exam.description;
            } else {
                els.subtitle.textContent = '';
            }
        }
        if (typeof data?.tab_switch_count === 'number') {
            tabSwitchReturnCount = Math.max(tabSwitchReturnCount, data.tab_switch_count);
        }
        if (requireCameraMonitoring && els.recordingBadge) {
            els.recordingBadge.style.display = 'inline-flex';
        }

        if (Array.isArray(data?.sections)) {
            const firstHydrate = flatQuestions.length === 0;
            if (firstHydrate) {
                flatQuestions = flattenQuestions(data.sections);
            }
            if (flatQuestions.length) {
                // merge server saved_answers revisions
                const sa = data.saved_answers;
                if (sa && typeof sa === 'object') {
                    for (const [qid, row] of Object.entries(sa)) {
                        const id = Number(qid);
                        if (!Number.isFinite(id)) continue;
                        const r = Number(row?.client_revision ?? 0);
                        if (Number.isFinite(r)) {
                            const cur = questionRevision.get(id) ?? 0;
                            if (r > cur) questionRevision.set(id, r);
                        }
                    }
                }
                pruneLocalPayloadFromServer();
                refreshFrontierFromAnswers();
                if (firstHydrate) {
                    currentIdx = Math.min(furthestIdx, flatQuestions.length - 1);
                    if (els.loading) els.loading.style.display = 'none';
                    renderQuestion();
                }
            }
        }

        syncTimerAnchors(data);
        if (data?.session_status === 'submitted') {
            serverDone = true;
            stopTimer();
            stopHeartbeat();
            window.location.assign(`/student/exam/${encodeURIComponent(sessionId)}/submitted`);
            return;
        }
        applyTimerPausedFromState(data);
        syncProctoringOverlay(data);
        if (!timerPaused) {
            startTimer();
            startHeartbeat();
        }
        renderTimer(computeRemainingSeconds());
        syncFullscreenGate();
        await flushOfflineQueue();
    }

    /* ---- Bind UI ---- */
    els.btnBack?.addEventListener('click', () => retreatQuestion());
    els.btnNext?.addEventListener('click', () => {
        if (effectiveInputsLocked()) return;
        advanceQuestion();
    });
    els.btnArenaReview?.addEventListener('click', () => showQuestionView());
    els.btnArenaSubmit?.addEventListener('click', () => confirmAndSubmit());
    els.btnSubmit?.addEventListener('click', () => confirmAndSubmit());
    els.btnTabSwitchDismiss?.addEventListener('click', () => hideTabSwitchModal());
    els.btnFullscreen?.addEventListener('click', () => {
        if (serverDone) return;
        void requestFullscreen();
    });
    els.btnFullscreenGate?.addEventListener('click', () => {
        if (serverDone) return;
        void requestFullscreen();
    });
    // First click anywhere requests fullscreen too (browsers require user gesture).
    document.addEventListener(
        'click',
        () => {
            if (serverDone || isDocumentFullscreen()) return;
            void requestFullscreen();
        },
        true,
    );

    attachCameraPipBehaviour('arena-cam-pip', 'btn-arena-cam-toggle');

    /* ---- Mic meter (live audio bar under the camera) ----
     * Mirrors the classic runtime's analyser pattern. The bar lives under
     * the camera tile in the arena layout so the student can see their
     * audio register and the invigilator gets a clear "I hear you" cue.
     */
    function setFeedStatus(text, state) {
        if (els.feedLabel) els.feedLabel.textContent = text;
        if (els.feedDot) els.feedDot.dataset.state = state || 'idle';
    }
    function startMicMeter(stream) {
        const track = stream?.getAudioTracks?.()[0];
        if (!track || !els.micBar) {
            if (els.micLabel) els.micLabel.textContent = 'Off';
            return;
        }
        try {
            micAudioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch {
            if (els.micLabel) els.micLabel.textContent = '—';
            return;
        }
        void micAudioContext.resume().catch(() => {});
        const src = micAudioContext.createMediaStreamSource(stream);
        const analyser = micAudioContext.createAnalyser();
        analyser.fftSize = 256;
        src.connect(analyser);
        const buf = new Uint8Array(analyser.frequencyBinCount);
        const tick = () => {
            micMeterFrame = window.requestAnimationFrame(tick);
            analyser.getByteFrequencyData(buf);
            let sum = 0;
            for (let i = 0; i < buf.length; i += 1) sum += buf[i];
            const avg = sum / buf.length / 255;
            const pct = Math.min(100, Math.round(avg * 220 + 6));
            els.micBar.style.width = `${pct}%`;
            if (els.micLabel) {
                if (pct < 12) {
                    els.micLabel.textContent = 'Quiet';
                    els.micLabel.dataset.tone = 'quiet';
                } else if (pct < 42) {
                    els.micLabel.textContent = 'Normal';
                    els.micLabel.dataset.tone = 'normal';
                } else {
                    els.micLabel.textContent = 'Active';
                    els.micLabel.dataset.tone = 'active';
                }
            }
        };
        tick();
    }

    /* ---- Live camera + proctoring runtime ----
     * Same acquisition flow as the classic runtime so the camera, recording
     * badge, and mic meter all light up the moment the page loads.
     */
    if (requireCameraMonitoring && els.proctoringVideo instanceof HTMLVideoElement) {
        setFeedStatus('Connecting…', 'pending');
        try {
            const capability = await fetchProctoringCapability(axios);
            const performanceProfile = capability?.performance_profile ?? {};
            const { qsProctoringMediaRequest } = await import('./cameraConstraints.js');

            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia(qsProctoringMediaRequest(true));
            } catch {
                stream = await navigator.mediaDevices.getUserMedia(qsProctoringMediaRequest(false));
            }

            els.proctoringVideo.srcObject = stream;
            els.proctoringVideo.muted = true;
            els.proctoringVideo.playsInline = true;
            await els.proctoringVideo.play().catch(() => {});

            if (els.recordingBadge) els.recordingBadge.style.display = 'inline-flex';
            startMicMeter(stream);
            setFeedStatus('Live', 'live');

            const engine = new ProctoringRuntimeEngine({
                videoElement: els.proctoringVideo,
                sessionId,
                examId,
                studentId,
                apiClient: axios,
                performanceProfile,
                previewCanvas: els.proctoringCanvas instanceof HTMLCanvasElement ? els.proctoringCanvas : null,
                eventBatcher: proctoringBatcher,
                onFramingHint: (hint) => {
                    if (els.proctoringFace) {
                        els.proctoringFace.textContent = hint === 'ok'
                            ? 'Framed'
                            : hint === 'multiple'
                              ? 'Multiple'
                              : hint === 'no_face'
                                ? 'No face'
                                : 'Off-center';
                    }
                    if (els.proctoringHint) {
                        els.proctoringHint.textContent = hint === 'ok'
                            ? ''
                            : hint === 'no_face'
                              ? 'Move into the camera so your face is visible.'
                              : 'Center your face in the frame.';
                    }
                    if (hint === 'no_face' || hint === 'multiple') {
                        setFeedStatus(hint === 'no_face' ? 'No face' : 'Multiple', 'warn');
                    } else {
                        setFeedStatus('Live', 'live');
                    }
                },
            });
            if (typeof engine.init === 'function') {
                await engine.init();
            }
            engine.start();
        } catch {
            setFeedStatus('Camera blocked', 'error');
            if (els.micLabel) els.micLabel.textContent = 'Off';
            updateBanner(
                'Camera or microphone is blocked. Allow access in your browser and refresh.',
                true,
            );
        }
    } else {
        setFeedStatus('Disabled', 'idle');
    }

    /* ---- Initial fetch ---- */
    try {
        await fetchState();
    } catch {
        updateBanner('Could not load the exam. Refresh and try again.', true);
    }

    // Initial fullscreen prompt + soft retry.
    void requestFullscreen();
    window.requestAnimationFrame(() => void requestFullscreen());
}

document.addEventListener('DOMContentLoaded', () => {
    void main();
});
