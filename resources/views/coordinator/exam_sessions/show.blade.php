<x-layouts.coordinator>
    <x-slot name="title">Session review</x-slot>
    <x-slot name="subtitle">{{ $session->exam?->title }}</x-slot>

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('coordinator.exams.sessions.index', $session->exam) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">← Sessions for this exam</a>
    </div>

    <div class="space-y-6">
        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-soft">Student</h3>
            <p class="mt-2 text-sm text-qs-text">{{ $session->student?->name ?? '—' }}</p>
            @if ($session->student?->index_number)
                <p class="mt-1 text-sm text-qs-soft">Index: {{ $session->student->index_number }}</p>
            @endif
        </div>

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-soft">Exam</h3>
            <p class="mt-2 text-sm font-medium text-qs-text">{{ $session->exam?->title }}</p>
            <p class="mt-1 text-sm text-qs-soft">{{ $session->exam?->course?->code }} — {{ $session->exam?->course?->title }}</p>
        </div>

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-soft">Score summary</h3>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-qs-soft">Workflow</dt>
                    <dd class="font-medium text-qs-text">{{ str_replace('_', ' ', $workflowStatus) }}</dd>
                </div>
                <div>
                    <dt class="text-qs-soft">Score</dt>
                    <dd class="font-medium text-qs-text">{{ $session->result ? $session->result->score : '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-soft">Risk &amp; violations</h3>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-qs-soft">Risk state</dt>
                    <dd class="font-medium text-qs-text">{{ $session->risk_state }}</dd>
                </div>
                <div>
                    <dt class="text-qs-soft">Violation score</dt>
                    <dd class="font-medium text-qs-text">{{ $session->violation_score }}</dd>
                </div>
                <div>
                    <dt class="text-qs-soft">Violation count</dt>
                    <dd class="font-medium text-qs-text">{{ $session->violation_count }}</dd>
                </div>
            </dl>
        </div>

        @if ($isHeld)
            <div class="qs-card rounded-xl border border-qs-accent/40 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-qs-text">Held result review</h3>
                <p class="mt-2 text-sm text-qs-soft">Choose an action. Changes apply immediately.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" id="held-release" class="qs-btn-primary px-4 py-2 text-sm">Release result</button>
                    <button type="button" id="held-confirm" class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Confirm violation</button>
                    <button type="button" id="held-override" class="qs-btn-secondary px-4 py-2 text-sm">Override</button>
                </div>
                <div class="mt-4">
                    <label for="override-note" class="mb-1 block text-xs font-semibold text-qs-soft">Override note (optional)</label>
                    <textarea id="override-note" rows="2" class="qs-input w-full max-w-lg py-2 text-sm" placeholder="Short note for audit trail"></textarea>
                </div>
            </div>
        @endif

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-soft">Proctoring timeline</h3>
            <div class="qs-table-wrap mt-4 overflow-x-auto rounded-lg border border-qs-soft">
                <table class="qs-table min-w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Time</th>
                            <th class="text-left">Event</th>
                            <th class="text-left">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($timeline as $ev)
                            <tr @class([
                                'bg-red-50/70' => $ev['is_auto_submit'],
                                'bg-amber-50/80' => $ev['is_warning'] && ! $ev['is_auto_submit'],
                            ])>
                                <td class="whitespace-nowrap text-sm text-qs-text">{{ $ev['at']?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
                                <td class="text-sm text-qs-text">{{ $ev['event_type'] }}</td>
                                <td class="text-sm font-medium text-qs-text">{{ $ev['action'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-sm text-qs-soft">No proctoring events recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (count($thumbnails) > 0)
            <div class="qs-card rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-soft">Image evidence</h3>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($thumbnails as $img)
                        <figure class="text-center">
                            <a href="{{ $img['url'] }}" target="_blank" rel="noopener noreferrer" class="block">
                                <img src="{{ $img['url'] }}" alt="" loading="lazy" decoding="async" class="h-24 w-24 rounded-lg border border-qs-soft object-cover" />
                            </a>
                            <figcaption class="mt-1 max-w-[6rem] truncate text-xs text-qs-soft">{{ $img['event_type'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($isHeld)
        @push('scripts')
            <script>
                (function () {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    async function postJson(url, body) {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrf || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(body ?? {}),
                        });
                        if (!res.ok) {
                            let msg = 'Request failed.';
                            try {
                                const j = await res.json();
                                msg = j.message || msg;
                            } catch (_) {}
                            alert(msg);
                            return false;
                        }
                        return true;
                    }
                    document.getElementById('held-release')?.addEventListener('click', async function () {
                        if (!confirm('Release this result to the student?')) return;
                        if (await postJson(@json($releaseUrl), {})) location.reload();
                    });
                    document.getElementById('held-confirm')?.addEventListener('click', async function () {
                        if (!confirm('Confirm violation and fail this result?')) return;
                        if (await postJson(@json($confirmFailUrl), {})) location.reload();
                    });
                    document.getElementById('held-override')?.addEventListener('click', async function () {
                        const note = document.getElementById('override-note')?.value || '';
                        if (!confirm('Override the held decision?')) return;
                        if (await postJson(@json($overrideUrl), { note })) location.reload();
                    });
                })();
            </script>
        @endpush
    @endif
</x-layouts.coordinator>
