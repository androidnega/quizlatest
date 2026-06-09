<x-layouts.coordinator>
    <x-slot name="title">{{ __('Exam Command Center') }}</x-slot>
    <x-slot name="subtitle">{{ __('Real-time view of every active exam in your university.') }}</x-slot>

    <div
        class="space-y-6"
        data-cc-root
        data-cc-metrics-url="{{ $metricsUrl }}"
    >
        {{-- Header strip: live status + last refresh --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
            <div class="flex items-center gap-2 text-sm">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                </span>
                <span class="font-semibold text-slate-900" data-cc-status-label>{{ __('Connecting…') }}</span>
                <span class="text-slate-500">·</span>
                <span class="text-slate-500">{{ __('Refreshes every 10 seconds.') }}</span>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span data-cc-snapshot-age>{{ __('Snapshot: pending') }}</span>
                <button
                    type="button"
                    class="rounded-md border border-slate-200 bg-white px-2 py-1 text-slate-700 hover:bg-slate-50"
                    data-cc-refresh
                >{{ __('Refresh now') }}</button>
            </div>
        </div>

        {{-- Active sessions row --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['students_writing', __('Students writing'), 'emerald'],
                ['active', __('Active sessions'), 'sky'],
                ['paused', __('Paused (disconnect)'), 'amber'],
                ['exams_running', __('Exams running'), 'violet'],
            ] as [$key, $label, $tone])
                <div class="rounded-xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 p-4">
                    <p class="text-[11px] font-medium text-{{ $tone }}-900/70">{{ $label }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums text-{{ $tone }}-950" data-cc-sessions="{{ $key }}">—</p>
                </div>
            @endforeach
        </div>

        {{-- Submission activity --}}
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <header class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Submissions today') }}</h3>
                <span class="text-xs text-slate-500" data-cc-section-time>—</span>
            </header>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[11px] font-medium text-slate-500">{{ __('Submitted') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900" data-cc-subs="submitted_today">—</p>
                </div>
                <div class="rounded-lg border border-rose-200 bg-rose-50 p-3">
                    <p class="text-[11px] font-medium text-rose-900/70">{{ __('Held for review') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-rose-950" data-cc-subs="held_today">—</p>
                </div>
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-3">
                    <p class="text-[11px] font-medium text-orange-900/70">{{ __('Auto-submitted') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-orange-950" data-cc-subs="auto_submitted_today">—</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-[11px] font-medium text-emerald-900/70">{{ __('Graded') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-950" data-cc-subs="graded_today">—</p>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ __('Auto-submit reasons') }}</p>
                <ul class="mt-2 flex flex-wrap gap-2 text-xs" data-cc-auto-breakdown>
                    <li class="text-slate-400">{{ __('No auto-submits today.') }}</li>
                </ul>
            </div>
        </section>

        {{-- Violations --}}
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <header class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Live violations (last hour)') }}</h3>
            </header>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    ['with_any_violation', __('With any violation')],
                    ['critical_risk_now', __('Critical risk now')],
                    ['face_missing', __('Face missing')],
                    ['phone_detected', __('Phone detected')],
                    ['tab_switch', __('Tab switches')],
                    ['overlay_dismissals', __('Overlay dismissals')],
                ] as [$key, $label])
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900" data-cc-violations="{{ $key }}">—</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Server health snapshot from qs:monitor:snapshot --}}
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <header class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Server health snapshot') }}</h3>
                <span class="text-xs text-slate-500">{{ __('Captured by qs:monitor:snapshot every 5 minutes.') }}</span>
            </header>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    ['log_size_mb', __('Log size (MB)')],
                    ['private_storage_mb', __('Private storage (MB)')],
                    ['failed_requests_per_hour', __('5xx / h')],
                    ['stale_paused_sessions', __('Stale paused')],
                    ['proctoring_events_per_minute', __('Events / min')],
                ] as [$key, $label])
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[11px] font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900" data-cc-snapshot="{{ $key }}">—</p>
                    </div>
                @endforeach
            </div>
            <ul class="mt-3 space-y-1 text-xs" data-cc-alerts></ul>
        </section>
    </div>

    @push('scripts')
    <script>
    (function () {
        const root = document.querySelector('[data-cc-root]');
        if (!root) return;
        const metricsUrl = root.dataset.ccMetricsUrl;
        const set = (selector, value) => {
            root.querySelectorAll(selector).forEach((el) => { el.textContent = String(value ?? '—'); });
        };
        const setData = (attr, key, value) => {
            const el = root.querySelector(`[data-cc-${attr}="${key}"]`);
            if (el) el.textContent = (value === undefined || value === null) ? '—' : String(value);
        };
        const fmtTime = () => new Date().toLocaleTimeString();

        async function poll() {
            try {
                const r = await fetch(metricsUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const m = await r.json();
                set('[data-cc-status-label]', '{{ __('Live') }}');
                set('[data-cc-section-time]', fmtTime());

                const sess = m.sessions ?? {};
                Object.entries(sess).forEach(([k, v]) => setData('sessions', k, v));

                const subs = m.submissions ?? {};
                Object.entries(subs).forEach(([k, v]) => {
                    if (k === 'auto_submit_breakdown') return;
                    setData('subs', k, v);
                });

                const breakdown = (m.submissions || {}).auto_submit_breakdown || {};
                const wrap = root.querySelector('[data-cc-auto-breakdown]');
                if (wrap) {
                    const entries = Object.entries(breakdown);
                    if (entries.length === 0) {
                        wrap.innerHTML = '<li class="text-slate-400">{{ __('No auto-submits today.') }}</li>';
                    } else {
                        wrap.innerHTML = entries.map(([reason, n]) =>
                            `<li class="rounded-md border border-orange-200 bg-orange-50 px-2 py-1 text-orange-900"><span class="font-mono text-[10px] uppercase tracking-wide">${reason}</span> · <strong>${n}</strong></li>`
                        ).join('');
                    }
                }

                const v = m.violations ?? {};
                Object.entries(v).forEach(([k, val]) => setData('violations', k, val));

                const snap = m.snapshot ?? {};
                Object.entries(snap).forEach(([k, val]) => {
                    if (k === 'alerts') return;
                    setData('snapshot', k, val);
                });

                const alerts = Array.isArray(snap?.alerts) ? snap.alerts : [];
                const alertsEl = root.querySelector('[data-cc-alerts]');
                if (alertsEl) {
                    if (alerts.length === 0) {
                        alertsEl.innerHTML = '<li class="text-emerald-700">{{ __('All thresholds nominal.') }}</li>';
                    } else {
                        alertsEl.innerHTML = alerts.map((a) => {
                            const tone = a.severity === 'critical' ? 'rose' : 'amber';
                            return `<li class="rounded-md border border-${tone}-200 bg-${tone}-50 px-2 py-1 text-${tone}-900"><strong>[${a.severity}]</strong> ${a.key} = ${a.value} (limit ${a.threshold})</li>`;
                        }).join('');
                    }
                }

                const ageEl = root.querySelector('[data-cc-snapshot-age]');
                if (ageEl) {
                    const age = m.latency?.snapshot_age_seconds;
                    ageEl.textContent = (age === undefined || age < 0)
                        ? '{{ __('Snapshot: never captured') }}'
                        : '{{ __('Snapshot age:') }} ' + age + 's';
                }
            } catch (err) {
                set('[data-cc-status-label]', '{{ __('Disconnected') }}');
            }
        }

        poll();
        setInterval(poll, 10000);
        root.querySelector('[data-cc-refresh]')?.addEventListener('click', () => poll());
    })();
    </script>
    @endpush
</x-layouts.coordinator>
