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

const DEBOUNCE_MS = 1600;
const POLL_MS = 12000;
const SAVE_RETRY = 3;
const SUBMIT_RETRIES = 3;
const SUBMIT_PERSIST_INTERVAL_MS = 12000;
/** Max wall-clock time for background submit retries after initial failures. */
const SUBMIT_PERSIST_MAX_MS = 15 * 60 * 1000;
const FULLSCREEN_EXIT_NOTICE_MS = 7000;

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
        video: document.getElementById('proctoring-video'),
        essayClipboardToast: document.getElementById('essay-clipboard-toast'),
    };

    /** Always available so essay clipboard attempts log even if camera/proctoring init fails. */
    const proctoringBatcher = new ProctoringEventBatcher({
        examSessionKey: sessionId,
        apiClient: axios,
    });

    let essayClipboardToastTimer = null;
    function showEssayClipboardWarning() {
        const toast = els.essayClipboardToast;
        if (!toast) {
            return;
        }
        toast.textContent = ESSAY_CLIPBOARD_WARNING_MESSAGE;
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

    const examStateEngine = new ExamStateEngine();
    examStateEngine.configureApi(axios);
    examStateEngine.sessionRouteKey = sessionId;

    let flatQuestions = [];
    let currentIdx = 0;
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
    const questionRevision = new Map();
    let serverSkewMs = 0;
    let examEndAtMs = null;
    let timedExam = false;
    let timeoutSubmitFired = false;
    let submitPersistTimer = null;
    let submitUiDefaultText = els.btnSubmit?.textContent ?? 'Submit';
    let fullscreenExitNoticeTimer = null;
    let examDocumentWasFullscreen = false;

    function setSaveIndicator(text, ok = true) {
        if (!els.saveIndicator) {
            return;
        }
        els.saveIndicator.textContent = text;
        els.saveIndicator.classList.toggle('text-qs-muted', ok);
        els.saveIndicator.classList.toggle('text-qs-danger', !ok);
        els.saveIndicator.classList.toggle('font-medium', !ok);
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
            if (serverDone) {
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
        return serverDone || riskInputsDisabled || submitInputLock;
    }

    function applyRiskInputsDisabled(disabled) {
        riskInputsDisabled = !!disabled;
        syncControlDisabled();
    }

    function syncControlDisabled() {
        const locked = effectiveInputsLocked();
        const controls = document.querySelectorAll(
            '#question-container input, #question-container textarea, #question-container button[data-q-action]',
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
        updateBanner('Exam submitted. You may close this page.', true);
        setSaveIndicator('Submitted', true);
        if (els.btnSubmit) {
            els.btnSubmit.disabled = true;
            els.btnSubmit.textContent = submitUiDefaultText;
        }
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

    function renderNav() {
        if (!els.nav) {
            return;
        }
        els.nav.innerHTML = '';
        flatQuestions.forEach((q, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className =
                'text-left text-sm px-2 py-1 rounded border border-qs-soft hover:bg-qs-card w-full md:w-auto ' +
                (i === currentIdx ? 'bg-qs-accent/25 border-qs-accent' : '');
            btn.textContent = `${i + 1}`;
            btn.addEventListener('click', () => {
                currentIdx = i;
                renderQuestion();
                renderNav();
            });
            els.nav.appendChild(btn);
        });
    }

    function scheduleSave(questionId, buildPayload) {
        const prev = debouncers.get(questionId);
        if (prev) {
            clearTimeout(prev);
        }
        setSaveIndicator('Saving…', true);
        debouncers.set(
            questionId,
            setTimeout(async () => {
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

        const wrap = document.createElement('div');
        wrap.className = 'space-y-4 max-w-3xl';

        const title = document.createElement('div');
        title.className = 'text-sm text-qs-muted';
        title.textContent = `Question ${currentIdx + 1} of ${flatQuestions.length} · ${q.marks} marks`;

        const body = document.createElement('div');
        body.className = 'max-w-none text-base leading-relaxed text-qs-text';
        body.innerHTML = escapeHtml(q.question_text || '').replace(/\n/g, '<br/>');

        wrap.appendChild(title);
        wrap.appendChild(body);

        const saved = latestPayload?.saved_answers?.[String(q.id)]?.answer_payload;

        if (q.type === 'mcq') {
            const optsRoot = document.createElement('div');
            optsRoot.className = 'space-y-2';
            const opts = Array.isArray(q.options) ? q.options : [];
            const selected = new Set(
                Array.isArray(saved?.selected)
                    ? saved.selected
                    : saved?.selected != null
                      ? [saved.selected]
                      : [],
            );
            opts.forEach((label, idx) => {
                const row = document.createElement('label');
                row.className = 'flex items-start gap-2 cursor-pointer';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'mt-1';
                cb.checked = selected.has(idx);
                cb.addEventListener('change', () => {
                    const sel = [];
                    optsRoot.querySelectorAll('input[type=checkbox]').forEach((box, j) => {
                        if (box.checked) {
                            sel.push(j);
                        }
                    });
                    if (sel.length === 0) {
                        return;
                    }
                    scheduleSave(q.id, () => ({ type: 'mcq', selected: sel }));
                });
                row.appendChild(cb);
                const span = document.createElement('span');
                span.textContent = label;
                row.appendChild(span);
                optsRoot.appendChild(row);
            });
            wrap.appendChild(optsRoot);
        } else if (q.type === 'true_false') {
            const row = document.createElement('div');
            row.className = 'flex gap-4';
            const mk = (val, label) => {
                const lab = document.createElement('label');
                lab.className = 'inline-flex items-center gap-2';
                const rb = document.createElement('input');
                rb.type = 'radio';
                rb.name = `tf-${q.id}`;
                rb.checked = saved?.value === val;
                rb.addEventListener('change', () => {
                    scheduleSave(q.id, () => ({ type: 'true_false', value: val }));
                });
                lab.appendChild(rb);
                lab.appendChild(document.createTextNode(label));
                return lab;
            };
            row.appendChild(mk(true, 'True'));
            row.appendChild(mk(false, 'False'));
            wrap.appendChild(row);
        } else if (q.type === 'fill_blank') {
            const n = fillBlankCount(q);
            const blanks = Array.isArray(saved?.blanks) ? saved.blanks : [];
            const blanksRoot = document.createElement('div');
            blanksRoot.className = 'space-y-2';
            for (let i = 0; i < n; i += 1) {
                const inp = document.createElement('input');
                inp.type = 'text';
                inp.className =
                    'block w-full rounded border border-qs-soft bg-qs-bg px-3 py-2 text-qs-text shadow-sm focus:border-qs-accent focus:outline-none focus:ring-2 focus:ring-qs-accent/40';
                inp.value = blanks[i] ?? '';
                inp.addEventListener('input', () => {
                    const vals = [];
                    blanksRoot.querySelectorAll('input[data-blank]').forEach((node) => vals.push(node.value));
                    scheduleSave(q.id, () => ({ type: 'fill_blank', blanks: vals }));
                });
                inp.dataset.blank = String(i);
                blanksRoot.appendChild(inp);
            }
            wrap.appendChild(blanksRoot);
        } else if (q.type === 'essay') {
            const ta = document.createElement('textarea');
            ta.rows = 10;
            ta.className =
                'block w-full rounded border border-qs-soft bg-qs-bg px-3 py-2 font-sans text-qs-text shadow-sm focus:border-qs-accent focus:outline-none focus:ring-2 focus:ring-qs-accent/40';
            ta.value = saved?.text ?? '';
            ta.addEventListener('input', () => {
                scheduleSave(q.id, () => ({ type: 'essay', text: ta.value }));
            });
            attachEssayAntiClipboard(ta, {
                showWarning: showEssayClipboardWarning,
                onBlocked: (actionType) => enqueueEssayClipboardAttempt(q.id, actionType),
            });
            wrap.appendChild(ta);
        }

        els.main.appendChild(wrap);
        syncControlDisabled();
    }

    function applySubmittedFromState(data) {
        if (data.session_status === 'submitted') {
            serverDone = true;
            submitInputLock = false;
            stopSubmitPersistence();
            stopTimer();
            syncControlDisabled();
            updateBanner(
                data.exam_ui_state === 'held' ? 'Under review — your result is held.' : 'Exam submitted.',
                true,
            );
            setSaveIndicator('Submitted', true);
            if (els.btnSubmit) {
                els.btnSubmit.disabled = true;
                els.btnSubmit.textContent = submitUiDefaultText;
            }
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
        if (els.subtitle && data.exam?.description) {
            els.subtitle.textContent = data.exam.description;
            els.subtitle.classList.remove('hidden');
        }

        if (Array.isArray(data.sections) && flatQuestions.length === 0) {
            flatQuestions = flattenQuestions(data.sections);
            renderNav();
            if (els.loading) {
                els.loading.classList.add('hidden');
            }
            els.main?.classList.remove('hidden');
            renderQuestion();
        }

        syncTimerAnchors(data);
        const rem = computeRemainingSeconds();
        renderTimerDisplay(rem === null ? null : rem);

        applySubmittedFromState(data);

        await flushOfflineAnswerQueue();

        return data;
    }

    async function submitExam(reason = 'manual') {
        if (serverDone) {
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
            } catch {
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

    examStateEngine.subscribe(({ state }) => {
        if (state === 'warning') {
            updateBanner('Warning: please stay focused on the exam.', true);
            if (!serverDone) {
                applyRiskInputsDisabled(false);
            }
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
            updateBanner('Exam submitted.', true);
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
        } else if (state === 'active') {
            if (!serverDone) {
                updateBanner('', false);
                applyRiskInputsDisabled(false);
            }
        }
    });

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

    document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement === document.documentElement) {
            examDocumentWasFullscreen = true;
            hideFullscreenExitNotice();
            return;
        }
        if (examDocumentWasFullscreen && !serverDone) {
            showFullscreenExitNotice();
        }
    });

    els.btnFullscreen?.addEventListener('click', async () => {
        try {
            if (!document.fullscreenElement) {
                await document.documentElement.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch {
            //
        }
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

    if (!serverDone) {
        startTimerClock();
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
        const perf = await fetchProctoringCapability(axios);
        const runtime = new ProctoringRuntimeEngine({
            videoElement: els.video,
            sessionId,
            examId,
            studentId,
            apiClient: axios,
            performanceProfile: perf,
            eventBatcher: proctoringBatcher,
        });

        if (requireCameraMonitoring) {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
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
    } catch (e) {
        console.error(e);
        updateBanner(
            'Camera or proctoring initialization failed. You may continue answering; contact support if issues persist.',
            true,
        );
    }
}

document.addEventListener('DOMContentLoaded', () => void main());
