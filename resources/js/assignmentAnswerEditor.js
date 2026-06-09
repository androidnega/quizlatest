/**
 * Assignment answer editor.
 *
 * Two modes:
 *   - rich:  contenteditable with basic formatting toolbar (B/I/U/lists).
 *   - code:  textarea with live syntax highlighting (highlight.js overlay)
 *            and a language picker. Only available when the lecturer has
 *            explicitly enabled it for the assignment.
 */

import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import c from 'highlight.js/lib/languages/c';
import cpp from 'highlight.js/lib/languages/cpp';
import csharp from 'highlight.js/lib/languages/csharp';
import css from 'highlight.js/lib/languages/css';
import go from 'highlight.js/lib/languages/go';
import java from 'highlight.js/lib/languages/java';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import kotlin from 'highlight.js/lib/languages/kotlin';
import php from 'highlight.js/lib/languages/php';
import plaintext from 'highlight.js/lib/languages/plaintext';
import python from 'highlight.js/lib/languages/python';
import ruby from 'highlight.js/lib/languages/ruby';
import rust from 'highlight.js/lib/languages/rust';
import sql from 'highlight.js/lib/languages/sql';
import swift from 'highlight.js/lib/languages/swift';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import 'highlight.js/styles/atom-one-dark.css';

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('c', c);
hljs.registerLanguage('cpp', cpp);
hljs.registerLanguage('csharp', csharp);
hljs.registerLanguage('css', css);
hljs.registerLanguage('go', go);
hljs.registerLanguage('java', java);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('json', json);
hljs.registerLanguage('kotlin', kotlin);
hljs.registerLanguage('php', php);
hljs.registerLanguage('plaintext', plaintext);
hljs.registerLanguage('python', python);
hljs.registerLanguage('ruby', ruby);
hljs.registerLanguage('rust', rust);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('swift', swift);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('xml', xml);

const LANGUAGES = [
    { id: 'auto', label: 'Auto detect' },
    { id: 'javascript', label: 'JavaScript' },
    { id: 'typescript', label: 'TypeScript' },
    { id: 'python', label: 'Python' },
    { id: 'java', label: 'Java' },
    { id: 'kotlin', label: 'Kotlin' },
    { id: 'c', label: 'C' },
    { id: 'cpp', label: 'C++' },
    { id: 'csharp', label: 'C#' },
    { id: 'go', label: 'Go' },
    { id: 'rust', label: 'Rust' },
    { id: 'ruby', label: 'Ruby' },
    { id: 'swift', label: 'Swift' },
    { id: 'php', label: 'PHP' },
    { id: 'sql', label: 'SQL' },
    { id: 'json', label: 'JSON' },
    { id: 'xml', label: 'HTML / XML' },
    { id: 'css', label: 'CSS' },
    { id: 'bash', label: 'Shell / Bash' },
    { id: 'plaintext', label: 'Plain text' },
];

const AUTO_LANG_SUBSET = [
    'javascript',
    'typescript',
    'python',
    'java',
    'kotlin',
    'c',
    'cpp',
    'csharp',
    'go',
    'rust',
    'ruby',
    'swift',
    'php',
    'sql',
    'json',
    'xml',
    'css',
    'bash',
];

const CODE_LINE_RE =
    /^\s*(#include|import\s|from\s|package\s|using\s|def\s|class\s|function\s|const\s|let\s|var\s|public\s|private\s|protected\s|fn\s|func\s|<\?php|namespace\s|interface\s|struct\s|enum\s|console\.|System\.out|println|printf|SELECT\s|INSERT\s|CREATE\s)/i;

/**
 * @param {string} text
 */
export function textLooksLikeCode(text) {
    const sample = String(text || '').trim();
    if (sample.length < 8) {
        return false;
    }
    const lines = sample.split(/\r?\n/).filter((l) => l.trim() !== '');
    if (lines.length === 0) {
        return false;
    }
    let codeish = 0;
    for (const line of lines.slice(0, 12)) {
        if (CODE_LINE_RE.test(line)) {
            codeish += 1;
        }
        if (/[{}`;]/.test(line) && /^\s{2,}\S/.test(line)) {
            codeish += 0.5;
        }
    }
    if (codeish >= 1) {
        return true;
    }
    const braceBalance = (sample.match(/[{}]/g) || []).length;
    return lines.length >= 3 && braceBalance >= 2;
}

/**
 * @param {HTMLElement} container
 * @param {{
 *   initialText?: string,
 *   clipboardBlock?: boolean,
 *   allowCode?: boolean,
 *   onInput?: (text: string) => void,
 *   attachClipboardGuard?: (el: HTMLElement) => void,
 * }} options
 */
export function mountAssignmentAnswerEditor(container, options = {}) {
    const {
        initialText = '',
        allowCode = false,
        onInput = () => {},
        attachClipboardGuard = null,
    } = options;

    let mode = allowCode ? 'code' : 'rich';
    let destroyed = false;
    let currentLanguage = 'auto';

    const root = document.createElement('div');
    root.className = 'qs-at-editor';
    if (allowCode) {
        root.classList.add('qs-at-editor--code-enabled');
    } else {
        root.classList.add('qs-at-editor--rich-only');
    }

    const modeBar = document.createElement('div');
    modeBar.className = 'qs-at-editor__modebar';

    const modeLabel = document.createElement('span');
    modeLabel.className = 'qs-at-editor__mode-label';

    const switchBtn = document.createElement('button');
    switchBtn.type = 'button';
    switchBtn.className = 'qs-at-editor__mode-switch';

    const body = document.createElement('div');
    body.className = 'qs-at-editor__body';

    if (allowCode) {
        root.append(modeBar, body);
    } else {
        root.append(body);
    }
    container.replaceChildren(root);

    /** @type {HTMLElement | HTMLTextAreaElement | null} */
    let activeInput = null;
    /** @type {any} */
    let tinymceEditor = null;

    function syncModeUi() {
        const isCode = mode === 'code';
        modeLabel.textContent = isCode ? 'Code editor' : 'Rich text';
        switchBtn.textContent = isCode ? 'Switch to rich text' : 'Switch to code editor';
        root.classList.toggle('qs-at-editor--code', isCode);
    }

    function emit() {
        if (destroyed) {
            return;
        }
        onInput(api.getValue());
    }

    function waitForTinyMce(timeoutMs = 8000) {
        if (typeof window === 'undefined') {
            return Promise.reject(new Error('No window available'));
        }
        if (window.tinymce) {
            return Promise.resolve(window.tinymce);
        }
        return new Promise((resolve, reject) => {
            const started = Date.now();
            const tick = () => {
                if (window.tinymce) {
                    resolve(window.tinymce);
                    return;
                }
                if (Date.now() - started > timeoutMs) {
                    reject(new Error('TinyMCE failed to load'));
                    return;
                }
                window.setTimeout(tick, 75);
            };
            tick();
        });
    }

    function teardownTinyMce() {
        if (tinymceEditor && typeof tinymceEditor.destroy === 'function') {
            try {
                tinymceEditor.destroy();
            } catch (err) {
                /* ignore */
            }
        }
        tinymceEditor = null;
    }

    function buildRich(initial) {
        teardownTinyMce();
        body.replaceChildren();

        const wrap = document.createElement('div');
        wrap.className = 'qs-at-editor__rich-wrap';

        const fallback = document.createElement('textarea');
        fallback.className = 'qs-at-editor__rich-fallback';
        fallback.setAttribute('aria-label', 'Response answer');
        fallback.placeholder = 'Write your response here…';
        fallback.value = initial || '';

        const status = document.createElement('p');
        status.className = 'qs-at-editor__rich-status';
        status.textContent = 'Loading editor…';

        const fallbackInputHandler = () => {
            if (allowCode && textLooksLikeCode(fallback.value || '')) {
                rebuild('code', fallback.value || '');
                return;
            }
            emit();
        };
        fallback.addEventListener('input', fallbackInputHandler);

        wrap.append(status, fallback);
        body.append(wrap);
        activeInput = fallback;

        if (typeof attachClipboardGuard === 'function') {
            attachClipboardGuard(fallback);
        }

        waitForTinyMce()
            .then((tinymce) => {
                if (destroyed || mode !== 'rich') {
                    return;
                }
                fallback.removeEventListener('input', fallbackInputHandler);

                return tinymce
                    .init({
                        target: fallback,
                        license_key: 'gpl',
                        height: 420,
                        min_height: 360,
                        menubar: false,
                        statusbar: false,
                        branding: false,
                        promotion: false,
                        placeholder: 'Write your response here…',
                        plugins: 'lists link autolink autoresize wordcount paste',
                        toolbar:
                            'undo redo | blocks | bold italic underline | bullist numlist | link | removeformat',
                        paste_data_images: false,
                        smart_paste: true,
                        autoresize_bottom_margin: 24,
                        content_style:
                            'body{font-family:Inter,-apple-system,BlinkMacSystemFont,Segoe UI,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#0f172a;padding:14px 16px;}p{margin:0 0 0.65em;}ul,ol{padding-left:1.4em;margin:0 0 0.6em;}a{color:#0369a1;}',
                        skin: 'oxide',
                        content_css: 'default',
                        setup(editor) {
                            tinymceEditor = editor;
                            editor.on('init', () => {
                                if (destroyed || mode !== 'rich') {
                                    return;
                                }
                                if (initial) {
                                    editor.setContent(coerceInitialHtml(initial));
                                }
                                status.remove();
                                if (typeof attachClipboardGuard === 'function') {
                                    try {
                                        const iframe = editor.iframeElement;
                                        const doc = iframe && iframe.contentDocument;
                                        if (doc && doc.body) {
                                            attachClipboardGuard(doc.body);
                                        }
                                    } catch (err) {
                                        /* ignore cross-doc errors */
                                    }
                                }
                            });
                            editor.on('input change keyup paste undo redo blur', () => {
                                if (destroyed || mode !== 'rich') {
                                    return;
                                }
                                if (allowCode) {
                                    const plain = editor.getContent({ format: 'text' });
                                    if (textLooksLikeCode(plain || '')) {
                                        rebuild('code', plain || '');
                                        return;
                                    }
                                }
                                emit();
                            });
                        },
                    })
                    .catch(() => {
                        /* If init rejects we keep the textarea fallback. */
                    });
            })
            .catch(() => {
                status.textContent =
                    'Rich editor unavailable — typing in plain text. Your work still autosaves.';
                status.classList.add('qs-at-editor__rich-status--error');
            });
    }

    function coerceInitialHtml(value) {
        const str = String(value || '');
        if (!str) {
            return '';
        }
        if (/<\s*(p|br|strong|em|u|b|i|ul|ol|li|h[1-6]|span|div|a)\b/i.test(str)) {
            return str;
        }
        return escapeHtml(str).replace(/\n/g, '<br>');
    }

    function highlightInto(codeEl, text, language) {
        const source = text.length === 0 ? '\u200B' : text;
        let html;
        try {
            if (language === 'auto') {
                const result = hljs.highlightAuto(source, AUTO_LANG_SUBSET);
                html = result.value;
                codeEl.dataset.detectedLanguage = result.language || '';
            } else {
                const result = hljs.highlight(source, { language, ignoreIllegals: true });
                html = result.value;
                codeEl.dataset.detectedLanguage = language;
            }
        } catch (err) {
            html = escapeHtml(source);
            codeEl.dataset.detectedLanguage = '';
        }
        // Keep textarea/pre heights in lock-step when the buffer ends in a newline.
        if (text.endsWith('\n')) {
            html += ' ';
        }
        codeEl.innerHTML = html;
    }

    function buildCode(initial) {
        body.replaceChildren();

        const toolbar = document.createElement('div');
        toolbar.className = 'qs-at-editor__toolbar qs-at-editor__toolbar--code';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Code options');

        const langWrap = document.createElement('label');
        langWrap.className = 'qs-at-editor__lang';

        const langCaption = document.createElement('span');
        langCaption.className = 'qs-at-editor__lang-caption';
        langCaption.textContent = 'Language';

        const langSelect = document.createElement('select');
        langSelect.className = 'qs-at-editor__lang-select';
        for (const lang of LANGUAGES) {
            const opt = document.createElement('option');
            opt.value = lang.id;
            opt.textContent = lang.label;
            langSelect.appendChild(opt);
        }
        langSelect.value = currentLanguage;

        langWrap.append(langCaption, langSelect);
        toolbar.append(langWrap);

        const editorRow = document.createElement('div');
        editorRow.className = 'qs-at-editor__code-wrap';

        const gutter = document.createElement('pre');
        gutter.className = 'qs-at-editor__gutter';
        gutter.setAttribute('aria-hidden', 'true');

        const pane = document.createElement('div');
        pane.className = 'qs-at-editor__code-pane';

        const preEl = document.createElement('pre');
        preEl.className = 'qs-at-editor__code-pre';
        preEl.setAttribute('aria-hidden', 'true');

        const codeEl = document.createElement('code');
        codeEl.className = 'hljs';
        preEl.appendChild(codeEl);

        const ta = document.createElement('textarea');
        ta.className = 'qs-at-editor__code-input';
        ta.spellcheck = false;
        ta.autocapitalize = 'off';
        ta.autocomplete = 'off';
        ta.autocorrect = 'off';
        ta.value = initial;
        ta.setAttribute('aria-label', 'Code answer');

        const placeholder = document.createElement('div');
        placeholder.className = 'qs-at-editor__code-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.textContent = 'Write your code here…';

        const togglePlaceholder = () => {
            placeholder.style.display = ta.value.length === 0 ? 'block' : 'none';
        };

        const syncGutter = () => {
            const n = Math.max(1, ta.value.split('\n').length);
            let s = '';
            for (let i = 1; i <= n; i += 1) {
                s += `${i}\n`;
            }
            gutter.textContent = s;
        };

        const syncScroll = () => {
            preEl.scrollTop = ta.scrollTop;
            preEl.scrollLeft = ta.scrollLeft;
            gutter.scrollTop = ta.scrollTop;
        };

        const rerender = () => {
            highlightInto(codeEl, ta.value, currentLanguage);
        };

        ta.addEventListener('input', () => {
            syncGutter();
            rerender();
            syncScroll();
            togglePlaceholder();
            emit();
        });
        ta.addEventListener('scroll', syncScroll);
        ta.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                ta.value = `${ta.value.slice(0, start)}    ${ta.value.slice(end)}`;
                ta.selectionStart = ta.selectionEnd = start + 4;
                syncGutter();
                rerender();
                syncScroll();
                emit();
            }
        });

        langSelect.addEventListener('change', () => {
            currentLanguage = langSelect.value;
            rerender();
        });

        syncGutter();
        rerender();
        togglePlaceholder();

        pane.append(preEl, placeholder, ta);
        editorRow.append(gutter, pane);
        body.append(toolbar, editorRow);
        activeInput = ta;
        if (typeof attachClipboardGuard === 'function') {
            attachClipboardGuard(ta);
        }
    }

    function htmlToText(html) {
        if (!html) {
            return '';
        }
        const tmp = document.createElement('div');
        tmp.innerHTML = String(html);
        return (tmp.innerText || tmp.textContent || '').replace(/\u00a0/g, ' ').replace(/\r\n/g, '\n');
    }

    function rebuild(nextMode, text) {
        if (!allowCode && nextMode === 'code') {
            nextMode = 'rich';
        }
        const prevMode = mode;
        mode = nextMode;
        syncModeUi();
        if (prevMode === 'rich' && mode !== 'rich') {
            teardownTinyMce();
        }
        if (mode === 'code') {
            buildCode(text);
        } else {
            buildRich(text);
        }
    }

    switchBtn.addEventListener('click', () => {
        if (!allowCode) {
            return;
        }
        // When leaving rich, convert HTML to plain so the code editor receives text.
        // When leaving code, plain text is already fine to seed the rich editor.
        const value = api.getValue();
        const next = mode === 'code' ? 'rich' : 'code';
        const seed = mode === 'rich' ? htmlToText(value) : value;
        rebuild(next, seed);
        emit();
    });

    if (allowCode) {
        modeBar.append(modeLabel, switchBtn);
    }

    rebuild(mode, initialText);

    const api = {
        getValue() {
            if (!activeInput) {
                return '';
            }
            if (mode === 'code') {
                return /** @type {HTMLTextAreaElement} */ (activeInput).value;
            }
            if (tinymceEditor && typeof tinymceEditor.getContent === 'function') {
                return tinymceEditor.getContent();
            }
            // Fallback: textarea (used until TinyMCE finishes loading)
            return /** @type {HTMLTextAreaElement} */ (activeInput).value || '';
        },
        focus() {
            if (mode === 'rich' && tinymceEditor && typeof tinymceEditor.focus === 'function') {
                tinymceEditor.focus();
                return;
            }
            activeInput?.focus?.();
        },
        destroy() {
            destroyed = true;
            teardownTinyMce();
            root.remove();
            activeInput = null;
        },
    };

    return api;
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
