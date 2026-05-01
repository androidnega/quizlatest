import axios from 'axios';
import {
    ProctoringRuntimeEngine,
    fetchProctoringCapability,
} from './proctoringRuntimeEngine';
import { ProctoringEventBatcher } from './proctoringEventBatcher';
import { ProctoringUploadManager } from './proctoringUploadManager';
import { createProctoringEcho } from './proctoringRealtime';
import { ExamStateEngine } from './examStateEngine';

const DEBOUNCE_MS = 1600;
const POLL_MS = 12000;
const SAVE_RETRY = 3;

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

async function postAnswerWithRetry(sessionId, questionId, payload) {
    let attempt = 0;
    let lastErr = null;
    while (attempt < SAVE_RETRY) {
        try {
            await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/answers`, {
                question_id: questionId,
                answer_payload: payload,
            });
            return true;
        } catch (e) {
            lastErr = e;
            attempt += 1;
            await new Promise((r) => setTimeout(r, 400 * attempt));
        }
    }
    console.error(lastErr);
    return false;
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
    if (!sessionId || !examId) {
        return;
    }

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
        video: document.getElementById('proctoring-video'),
    };

    const examStateEngine = new ExamStateEngine();
    examStateEngine.configureApi(axios);
    examStateEngine.sessionRouteKey = sessionId;

    let flatQuestions = [];
    let currentIdx = 0;
    let latestPayload = null;
    let timerRemainingSec = 0;
    let timerHandle = null;
    let submittedLocked = false;
    let inputsDisabled = false;
    let autoSubmitTriggered = false;
    const debouncers = new Map();

    function setSaveIndicator(text, ok = true) {
        if (!els.saveIndicator) {
            return;
        }
        els.saveIndicator.textContent = text;
        els.saveIndicator.classList.toggle('text-emerald-700', ok);
        els.saveIndicator.classList.toggle('text-red-600', !ok);
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

    function syncTimerFromPayload(payload) {
        const r = Number(payload?.time_remaining_seconds ?? 0);
        timerRemainingSec = Number.isFinite(r) ? Math.max(0, r) : 0;
        if (els.timer) {
            els.timer.textContent = formatMmSs(timerRemainingSec);
        }
    }

    function startTimerClock() {
        stopTimer();
        timerHandle = window.setInterval(() => {
            if (submittedLocked) {
                stopTimer();
                return;
            }
            timerRemainingSec = Math.max(0, timerRemainingSec - 1);
            if (els.timer) {
                els.timer.textContent = formatMmSs(timerRemainingSec);
            }
            if (timerRemainingSec <= 0) {
                stopTimer();
                void submitExam('timeout');
            }
        }, 1000);
    }

    function applyInputsDisabled(disabled) {
        inputsDisabled = disabled;
        const controls = document.querySelectorAll(
            '#question-container input, #question-container textarea, #question-container button[data-q-action]',
        );
        controls.forEach((el) => {
            el.disabled = !!disabled;
        });
        if (els.btnSubmit) {
            els.btnSubmit.disabled = !!disabled || submittedLocked;
        }
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
                'text-left text-sm px-2 py-1 rounded border border-slate-200 hover:bg-slate-50 w-full md:w-auto ' +
                (i === currentIdx ? 'bg-[#CFAC81]/30 border-[#CFAC81]' : '');
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
                const ok = await postAnswerWithRetry(sessionId, questionId, payload);
                setSaveIndicator(ok ? 'Saved' : 'Could not save — check connection', ok);
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
        title.className = 'text-sm text-slate-500';
        title.textContent = `Question ${currentIdx + 1} of ${flatQuestions.length} · ${q.marks} marks`;

        const body = document.createElement('div');
        body.className = 'prose prose-slate max-w-none';
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
                inp.className = 'block w-full border rounded px-3 py-2 border-slate-300';
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
            ta.className = 'block w-full border rounded px-3 py-2 border-slate-300 font-sans';
            ta.value = saved?.text ?? '';
            ta.addEventListener('input', () => {
                scheduleSave(q.id, () => ({ type: 'essay', text: ta.value }));
            });
            wrap.appendChild(ta);
        }

        els.main.appendChild(wrap);
        applyInputsDisabled(inputsDisabled);
    }

    async function fetchState() {
        const { data } = await axios.get(`/exam-sessions/${encodeURIComponent(sessionId)}/state`);
        latestPayload = data;
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

        syncTimerFromPayload(data);

        if (data.session_status === 'submitted') {
            submittedLocked = true;
            stopTimer();
            applyInputsDisabled(true);
            updateBanner(
                data.exam_ui_state === 'held' ? 'Under review — your result is held.' : 'Exam submitted.',
                true,
            );
            setSaveIndicator('Submitted', true);
        }

        return data;
    }

    async function submitExam(reason = 'manual') {
        if (submittedLocked) {
            return;
        }
        submittedLocked = true;
        applyInputsDisabled(true);
        setSaveIndicator(reason === 'timeout' ? 'Time expired — submitting…' : 'Submitting…', true);
        try {
            await axios.post(`/exam-sessions/${encodeURIComponent(sessionId)}/submit`);
            updateBanner('Exam submitted. You may close this page.', true);
            setSaveIndicator('Submitted', true);
            stopTimer();
        } catch {
            submittedLocked = false;
            applyInputsDisabled(inputsDisabled);
            setSaveIndicator('Submit failed — try again', false);
        }
    }

    examStateEngine.subscribe(({ state }) => {
        if (state === 'warning') {
            updateBanner('Warning: please stay focused on the exam.', true);
            applyInputsDisabled(false);
        } else if (state === 'locked') {
            updateBanner('Exam locked by invigilator or policy.', true);
            applyInputsDisabled(true);
        } else if (state === 'auto_submitting') {
            updateBanner('Submitting exam due to proctoring policy…', true);
            if (!autoSubmitTriggered) {
                autoSubmitTriggered = true;
                void submitExam('auto');
            }
        } else if (state === 'held') {
            updateBanner('Under review — your result is held.', true);
            applyInputsDisabled(true);
        } else if (state === 'submitted') {
            updateBanner('Exam submitted.', true);
            applyInputsDisabled(true);
            submittedLocked = true;
            stopTimer();
        } else if (state === 'active') {
            updateBanner('', false);
            applyInputsDisabled(false);
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
        if (submittedLocked) {
            return;
        }
        if (!window.confirm('Submit your exam? You cannot undo this.')) {
            return;
        }
        await submitExam('manual');
    });

    await fetchState();
    startTimerClock();
    window.setInterval(() => void fetchState().catch(() => {}), POLL_MS);

    const echo = createProctoringEcho();
    if (echo) {
        examStateEngine.attachRealtime(echo, sessionId, axios);
    }

    try {
        const perf = await fetchProctoringCapability(axios);
        const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        if (els.video) {
            els.video.srcObject = stream;
            await els.video.play().catch(() => {});
        }

        const batcher = new ProctoringEventBatcher({
            examSessionKey: sessionId,
            apiClient: axios,
        });

        const runtime = new ProctoringRuntimeEngine({
            videoElement: els.video,
            sessionId,
            examId,
            studentId,
            apiClient: axios,
            performanceProfile: perf,
            eventBatcher: batcher,
        });
        await runtime.init();
        runtime.start();

        const uploads = new ProctoringUploadManager({
            videoElement: els.video,
            sessionId,
            quizId: examId,
        });
        uploads.start();
    } catch (e) {
        console.error(e);
        updateBanner(
            'Camera or proctoring initialization failed. You may continue answering; contact support if issues persist.',
            true,
        );
    }
}

document.addEventListener('DOMContentLoaded', () => void main());
