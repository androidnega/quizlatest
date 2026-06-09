/**
 * Floating action button (mobile) — toggles a quick-nav menu on the student
 * shell. Only attaches when the FAB markup is present, so it is safe on every
 * student route.
 */

function initFab(root) {
    if (!root || root.__qsFabReady) {
        return;
    }

    const toggle = root.querySelector('[data-qs-fab-toggle]');
    const menu = root.querySelector('[data-qs-fab-menu]');
    const backdrop = root.querySelector('[data-qs-fab-backdrop]');
    const items = menu ? Array.from(menu.querySelectorAll('a, button')) : [];

    if (!toggle || !menu) {
        return;
    }

    root.__qsFabReady = true;

    const setOpen = (open) => {
        root.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute(
            'aria-label',
            open ? toggle.dataset.labelClose || 'Close quick navigation' : toggle.dataset.labelOpen || 'Open quick navigation',
        );
        if (open) {
            const first = items[0];
            if (first) {
                window.setTimeout(() => {
                    try { first.focus({ preventScroll: true }); } catch (e) { /* noop */ }
                }, 120);
            }
        }
    };

    toggle.addEventListener('click', (e) => {
        e.preventDefault();
        setOpen(!root.classList.contains('is-open'));
    });

    if (backdrop) {
        backdrop.addEventListener('click', () => setOpen(false));
    }

    items.forEach((item) => {
        item.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-open')) {
            setOpen(false);
            try { toggle.focus({ preventScroll: true }); } catch (e) { /* noop */ }
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.classList.contains('is-open')) {
            return;
        }
        if (root.contains(event.target)) {
            return;
        }
        setOpen(false);
    });
}

function boot() {
    document.querySelectorAll('[data-qs-fab]').forEach(initFab);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
