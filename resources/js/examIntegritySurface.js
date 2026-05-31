/**
 * Best-effort exam surface integrity: clipboard, capture keys, context menu.
 * OS-level screenshots/screen recorders cannot be stopped from a web page; this layer
 * deters common in-browser paths and logs signals for review when proctoring is on.
 */

/** @param {HTMLElement | null} root */
function isInsideExamSurface(root, target) {
    if (!root || !target || !(target instanceof Node)) {
        return false;
    }
    return root.contains(target);
}

/** @param {Event} ev */
function detailFromQuestionHost(ev) {
    const t = ev.target;
    if (!(t instanceof Element)) {
        return {};
    }
    const host = t.closest('[data-question-id]');
    if (!host) {
        return {};
    }
    const id = Number(host.getAttribute('data-question-id'));
    return Number.isFinite(id) ? { question_id: id } : {};
}

/**
 * @param {{
 *   root: HTMLElement | null,
 *   assignmentMode: boolean,
 *   clipboardLock: boolean,
 *   screenshotMitigation: boolean,
 *   screenRecordMitigation: boolean,
 *   enqueueSignal: (signal: string, detail?: { question_id?: number }) => void,
 * }} opts
 * @returns {() => void} detach
 */
export function attachExamIntegritySurface(opts) {
    const { root, assignmentMode, clipboardLock, screenshotMitigation, screenRecordMitigation, enqueueSignal } = opts;
    if (!root || assignmentMode) {
        return () => {};
    }

    const ac = new AbortController();
    const { signal } = ac;

    if (clipboardLock) {
        const blockClipboard = (kind, ev) => {
            if (!isInsideExamSurface(root, /** @type {Node} */ (ev.target))) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            enqueueSignal(kind, detailFromQuestionHost(ev));
        };
        root.addEventListener('copy', (e) => blockClipboard('copy', e), { capture: true, signal });
        root.addEventListener('cut', (e) => blockClipboard('cut', e), { capture: true, signal });
        root.addEventListener('paste', (e) => blockClipboard('paste', e), { capture: true, signal });
        root.addEventListener(
            'beforeinput',
            (e) => {
                if (e.inputType === 'insertFromPaste' || e.inputType === 'insertFromDrop') {
                    blockClipboard(e.inputType === 'insertFromDrop' ? 'drop' : 'paste', e);
                }
            },
            { capture: true, signal },
        );
    }

    if (screenshotMitigation || screenRecordMitigation) {
        const allowCaptureKeys = screenshotMitigation || screenRecordMitigation;

        /**
         * Windows / Linux: PrintScreen (incl. Alt/Shift variants). macOS: F13 on some Apple / external keyboards.
         */
        const isPrintScreenKey = (/** @type {KeyboardEvent} */ e) => {
            if (!allowCaptureKeys) {
                return false;
            }
            if (e.key === 'PrintScreen' || e.code === 'PrintScreen' || e.key === 'Print' || e.code === 'Print') {
                return true;
            }
            return e.code === 'F13' || e.key === 'F13';
        };

        /**
         * macOS screenshot chords: ⌘⇧3 (full), ⌘⇧4 (selection), ⌘⇧6 (Touch Bar).
         */
        const isMacOsScreenshotChord = (/** @type {KeyboardEvent} */ e) => {
            if (!allowCaptureKeys || !e.metaKey || !e.shiftKey) {
                return false;
            }
            const codes = new Set(['Digit3', 'Digit4', 'Digit6', 'Numpad3', 'Numpad4', 'Numpad6']);
            if (codes.has(e.code)) {
                return true;
            }
            const ch = e.key.length === 1 ? e.key : '';
            return ch === '3' || ch === '4' || ch === '6';
        };

        /**
         * macOS screen-recording chord: ⌘⇧5 opens the screenshot/screen-recording
         * toolbar from which the user can capture stills or start a recording.
         */
        const isMacOsScreenRecordChord = (/** @type {KeyboardEvent} */ e) => {
            if (!screenRecordMitigation || !e.metaKey || !e.shiftKey) {
                return false;
            }
            if (e.code === 'Digit5' || e.code === 'Numpad5') {
                return true;
            }
            return e.key === '5';
        };

        /**
         * Windows Game Bar (screen recorder): Win+G — meta key + KeyG.
         */
        const isWindowsScreenRecordChord = (/** @type {KeyboardEvent} */ e) => {
            if (!screenRecordMitigation || !e.metaKey || e.ctrlKey || e.altKey || e.shiftKey) {
                return false;
            }
            return e.code === 'KeyG' || e.key === 'g' || e.key === 'G';
        };

        /**
         * Windows Snip & Sketch: Win+Shift+S (often meta+shift+s in Chromium); some setups use Ctrl+Shift+S.
         */
        const isSnippingToolChord = (/** @type {KeyboardEvent} */ e) => {
            if (!screenshotMitigation) {
                return false;
            }
            if (!e.shiftKey || (e.key !== 's' && e.key !== 'S')) {
                return false;
            }
            return e.metaKey || e.ctrlKey;
        };

        const onKeyDown = (e) => {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                return;
            }

            let signalType = null;
            if (isMacOsScreenRecordChord(e) || isWindowsScreenRecordChord(e)) {
                signalType = 'screen_record_shortcut';
            } else if (isPrintScreenKey(e)) {
                signalType = 'printscreen_key';
            } else if (isMacOsScreenshotChord(e) || isSnippingToolChord(e)) {
                signalType = 'capture_shortcut';
            }

            if (!signalType) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            enqueueSignal(signalType);
        };
        window.addEventListener('keydown', onKeyDown, { capture: true, signal });
    }

    if (screenRecordMitigation) {
        // Best-effort: detect any in-page call to navigator.mediaDevices.getDisplayMedia,
        // which is how browser-driven screen capture is initiated. We cannot block
        // OS-level screen recorders from a web page, but we can catch the Web API path
        // and surface it to the proctor.
        try {
            const md = /** @type {MediaDevices | undefined} */ (navigator?.mediaDevices);
            if (md && typeof md.getDisplayMedia === 'function' && !md.__quizsnapDisplayCaptureWrapped) {
                const original = md.getDisplayMedia.bind(md);
                md.getDisplayMedia = function patched(...args) {
                    try {
                        enqueueSignal('display_capture_request');
                    } catch {
                        // never let logging block the underlying call
                    }
                    return original(...args);
                };
                md.__quizsnapDisplayCaptureWrapped = true;
            }
        } catch {
            // Ignore — `navigator.mediaDevices` may be undefined in some embed contexts.
        }
    }

    if (screenshotMitigation) {
        root.addEventListener(
            'contextmenu',
            (e) => {
                if (!isInsideExamSurface(root, /** @type {Node} */ (e.target))) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                enqueueSignal('contextmenu', detailFromQuestionHost(e));
            },
            { capture: true, signal },
        );
    }

    let triedKeyboardLock = false;
    const tryKeyboardLock = () => {
        if (!screenRecordMitigation || triedKeyboardLock) {
            return;
        }
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            return;
        }
        const nav = /** @type {{ keyboard?: { lock?: (keys: string[]) => Promise<void> } } } } */ (navigator);
        if (!nav.keyboard?.lock) {
            return;
        }
        triedKeyboardLock = true;
        void nav.keyboard
            .lock(['PrintScreen'])
            .catch(() => {
                triedKeyboardLock = false;
            });
    };

    const onFs = () => {
        tryKeyboardLock();
    };
    document.addEventListener('fullscreenchange', onFs, { signal });
    document.addEventListener('webkitfullscreenchange', onFs, { signal });
    queueMicrotask(() => tryKeyboardLock());

    return () => {
        ac.abort();
    };
}
