/**
 * Live countdown labels on the student dashboard feed rows (due / closes / opens).
 */
function formatCountdown(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    const pad = (n) => String(n).padStart(2, '0');

    if (days > 0) {
        return `${days}d ${pad(hours)}h ${pad(minutes)}m ${pad(seconds)}s`;
    }

    if (hours > 0) {
        return `${hours}h ${pad(minutes)}m ${pad(seconds)}s`;
    }

    if (minutes > 0) {
        return `${minutes}m ${pad(seconds)}s`;
    }

    return `${seconds}s`;
}

function tickCountdownEl(el) {
    const endsRaw = el.getAttribute('data-qs-countdown-ends');
    if (!endsRaw) {
        return;
    }

    const endsAt = Date.parse(endsRaw);
    if (Number.isNaN(endsAt)) {
        return;
    }

    const remaining = endsAt - Date.now();
    const safeRemaining = Math.max(0, remaining);

    const total = Math.floor(safeRemaining / 1000);
    const days = Math.floor(total / 86400);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    const pad = (n) => String(n).padStart(2, '0');

    const daysEl = el.querySelector('[data-qs-countdown-days]');
    const hoursEl = el.querySelector('[data-qs-countdown-hours]');
    const minutesEl = el.querySelector('[data-qs-countdown-minutes]');
    const secondsEl = el.querySelector('[data-qs-countdown-seconds]');
    const timeEl = el.querySelector('.qs-std-dash-countdown__time');

    // When the host markup omits the days segment (e.g. the mobile wallet
    // hero, which is intentionally hh:mm:ss), roll the day overflow into
    // hours so a 30h timer shows as `30:00:00` instead of looping back to
    // `06:00:00`. Otherwise stick to the conventional 0-23h range.
    const hours = daysEl
        ? Math.floor((total % 86400) / 3600)
        : Math.floor(total / 3600);

    if (remaining <= 0) {
        if (daysEl) daysEl.textContent = '00';
        if (hoursEl) hoursEl.textContent = '00';
        if (minutesEl) minutesEl.textContent = '00';
        if (secondsEl) secondsEl.textContent = '00';
        if (timeEl) timeEl.textContent = '0s';
        el.classList.add('is-expired');

        // Hero countdowns (mobile wallet + desktop ticket card) opt in to
        // staying visible when the timer hits zero so CSS can swap the clock
        // for the dynamic post-expiry CTA ("Start now" / "Closed" / "Submit
        // now"). All other timers (worklist rows, dashboard pills) keep
        // their legacy hide-on-zero behaviour.
        if (
            el.classList.contains('qs-wl-countdown')
            || el.classList.contains('qs-std-countdown-pill')
            || el.hasAttribute('data-qs-countdown-keep-visible')
        ) {
            return;
        }
        el.hidden = true;
        return;
    }

    el.classList.remove('is-expired');
    el.hidden = false;

    if (daysEl || hoursEl || minutesEl || secondsEl) {
        if (daysEl) daysEl.textContent = pad(days);
        if (hoursEl) hoursEl.textContent = pad(hours);
        if (minutesEl) minutesEl.textContent = pad(minutes);
        if (secondsEl) secondsEl.textContent = pad(seconds);
    }

    if (timeEl) {
        timeEl.textContent = formatCountdown(remaining);
    }

    if (el.classList.contains('qs-std-countdown-pill') || el.classList.contains('qs-wl-countdown')) {
        el.classList.toggle('is-urgent', remaining <= 24 * 60 * 60 * 1000);
    }
}

function initStudentDashboardCountdowns(root = document) {
    const nodes = root.querySelectorAll('[data-qs-countdown]');
    if (nodes.length === 0) {
        return;
    }

    const tickAll = () => {
        nodes.forEach(tickCountdownEl);
    };

    tickAll();

    if (window.__qsDashCountdownInterval) {
        clearInterval(window.__qsDashCountdownInterval);
    }

    window.__qsDashCountdownInterval = window.setInterval(tickAll, 1000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initStudentDashboardCountdowns());
} else {
    initStudentDashboardCountdowns();
}

export { initStudentDashboardCountdowns };
