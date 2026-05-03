/** Student exam runtime only: block clipboard/drag on essay fields and notify server. */

const WARNING_TEXT = 'Copy and paste are not allowed for essay answers.';
const TOAST_COOLDOWN_MS = 2200;

/**
 * @param {HTMLTextAreaElement} textarea
 * @param {{
 *   onBlocked: (actionType: 'paste'|'copy'|'cut'|'drop'|'contextmenu') => void,
 *   showWarning?: () => void,
 * }} options
 */
export function attachEssayAntiClipboard(textarea, options) {
    const { onBlocked, showWarning } = options;

    let lastToastAt = 0;
    const bumpToast = () => {
        const now = Date.now();
        if (now - lastToastAt < TOAST_COOLDOWN_MS) {
            return;
        }
        lastToastAt = now;
        if (typeof showWarning === 'function') {
            showWarning();
        }
    };

    const block = (actionType, ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        bumpToast();
        try {
            onBlocked(actionType);
        } catch {
            //
        }
    };

    textarea.addEventListener('paste', (e) => block('paste', e));
    textarea.addEventListener('copy', (e) => block('copy', e));
    textarea.addEventListener('cut', (e) => block('cut', e));
    textarea.addEventListener('contextmenu', (e) => block('contextmenu', e));
    textarea.addEventListener('drop', (e) => block('drop', e));
    textarea.addEventListener('dragover', (e) => {
        e.preventDefault();
    });
}

export const ESSAY_CLIPBOARD_WARNING_MESSAGE = WARNING_TEXT;
