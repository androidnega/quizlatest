<x-layouts.examiner>
    <x-slot name="title">
        {{ ($isAssignmentSession ?? false) ? __('Submission review') : __('Session') }}
        @if ($session->student?->index_number) — {{ $session->student->index_number }} @endif
    </x-slot>
    <x-slot name="subtitle">{{ $session->exam?->title }}</x-slot>

    @php
        $tz = config('app.timezone');

        $fmtMark = static function ($value) {
            if ($value === null || $value === '') {
                return '0';
            }
            $s = number_format((float) $value, 2, '.', '');
            if (str_contains($s, '.')) {
                $s = rtrim(rtrim($s, '0'), '.');
            }

            return $s === '' ? '0' : $s;
        };

        $maxMark = (float) ($session->exam?->total_marks ?? 0);
        $scoreVal = $session->result?->score;
        $scorePct = ($maxMark > 0 && $scoreVal !== null)
            ? round(((float) $scoreVal / $maxMark) * 100, 1)
            : null;

        $startAt = $session->writing_started_at ?? $session->start_time;
        $endAt = $session->end_time;
        $durationSec = null;
        if ($startAt !== null && $endAt !== null) {
            $durationSec = max(0, $endAt->getTimestamp() - $startAt->getTimestamp());
        }
        $fmtDur = static function (?int $secs) {
            if ($secs === null) {
                return '—';
            }
            $h = intdiv($secs, 3600);
            $m = intdiv($secs % 3600, 60);
            $s = $secs % 60;

            return $h > 0
                ? sprintf('%d:%02d:%02d', $h, $m, $s)
                : sprintf('%d:%02d', $m, $s);
        };

        $violationCount = (int) ($session->violation_count ?? 0);
        $riskState = (string) ($session->risk_state ?? 'normal');

        $statusBadgeClass = match ($riskState) {
            'critical', 'locked' => 'bg-rose-50 text-rose-800 ring-1 ring-rose-200',
            'suspicious', 'warning' => 'bg-amber-50 text-amber-900 ring-1 ring-amber-200',
            default => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
        };

        $back = route('examiner.quizzes.workspace', ['exam' => $session->exam, 'tab' => 'sessions']);
    @endphp

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    {{-- Top breadcrumb / heading row --}}
    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
        <a href="{{ $back }}" class="font-medium text-qs-text underline-offset-2 hover:underline">← {{ __('Back to scores') }}</a>
        <span class="hidden text-qs-muted sm:inline">·</span>
        <span class="font-semibold uppercase tracking-wide text-qs-muted">{{ __('Quiz HUD') }}</span>
        <span class="hidden text-qs-muted sm:inline">·</span>
        <span class="text-qs-muted">{{ __('Index') }}</span>
        <span class="font-mono text-qs-text">{{ $session->student?->index_number ?: '—' }}</span>
    </div>

    {{-- Assignment hint banner (only for coursework) --}}
    @if (! empty($assignmentSessionContext) && $session->exam)
        @php $ac = $assignmentSessionContext; @endphp
        <div class="mb-5 rounded-xl border border-sky-200/80 bg-sky-50/60 p-4 text-sm text-slate-800">
            <p class="text-sm font-semibold text-slate-900">{{ __('Assignment submission') }}</p>
            <p class="mt-1 text-xs text-slate-700">{{ __('This attempt is coursework. Use the grading queue for marks and feedback.') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('examiner.quizzes.workspace', ['exam' => $session->exam, 'tab' => 'overview']) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">{{ __('Assignment workspace') }}</a>
                <a href="{{ route('examiner.grading.pending') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">{{ __('Grading queue') }}</a>
            </div>
        </div>
    @endif

    {{-- 1. Summary panel --}}
    <section class="qs-surface rounded-2xl border border-qs-soft bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-qs-soft pb-3">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-semibold text-qs-text">{{ __('Summary') }}</h2>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $statusBadgeClass }}">{{ str_replace('_', ' ', $riskState) }}</span>
                <span class="inline-flex items-center rounded-full bg-qs-soft/60 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ str_replace('_', ' ', $workflowStatus) }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @if (! empty($invalidateForRetakeUrl))
                    <form method="POST" action="{{ $invalidateForRetakeUrl }}" onsubmit="return confirm(@json(__('Clear this attempt permanently? The student will be able to start the exam again.')));">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-800 shadow-sm hover:bg-rose-50">
                            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                            {{ __('Allow another attempt') }}
                        </button>
                    </form>
                @endif
                @if ($isHeld && $canManageResults)
                    <button type="button" id="held-release" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        {{ __('Release result') }}
                    </button>
                    <button type="button" id="held-confirm" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-800 shadow-sm hover:bg-rose-50">
                        <i class="fa-solid fa-ban" aria-hidden="true"></i>
                        {{ __('Confirm violation') }}
                    </button>
                    <button type="button" id="held-override" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        {{ __('Override') }}
                    </button>
                @endif
            </div>
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-qs-soft/70 bg-qs-soft/15 px-3 py-2.5">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Index') }}</dt>
                <dd class="mt-1 font-mono text-sm text-qs-text">{{ $session->student?->index_number ?: '—' }}</dd>
            </div>
            <div class="rounded-xl border border-qs-soft/70 bg-qs-soft/15 px-3 py-2.5">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Time') }}</dt>
                <dd class="mt-1 text-sm text-qs-text">
                    @if ($startAt)
                        <span class="font-medium">{{ $startAt->timezone($tz)->format('M j, H:i') }}</span>
                        @if ($endAt) <span class="text-qs-muted">→ {{ $endAt->timezone($tz)->format('H:i') }}</span> @endif
                    @else — @endif
                </dd>
            </div>
            <div class="rounded-xl border border-qs-soft/70 bg-qs-soft/15 px-3 py-2.5">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Time on phase') }}</dt>
                <dd class="mt-1 text-sm font-semibold tabular-nums text-qs-text">{{ $fmtDur($durationSec) }}</dd>
            </div>
            <div class="rounded-xl border border-qs-soft/70 bg-qs-soft/15 px-3 py-2.5">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Marks') }}</dt>
                <dd class="mt-1 text-sm tabular-nums text-qs-text">
                    @if ($session->result)
                        <span class="font-semibold">{{ $fmtMark($scoreVal) }}</span><span class="text-qs-muted">/{{ $fmtMark($maxMark) }}</span>
                        @if ($scorePct !== null)
                            <span class="ml-1 text-[11px] font-semibold text-qs-muted">({{ $scorePct }}%)</span>
                        @endif
                    @else — @endif
                </dd>
            </div>
            <div class="rounded-xl border border-qs-soft/70 bg-qs-soft/15 px-3 py-2.5">
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Violations') }}</dt>
                <dd class="mt-1 text-sm tabular-nums text-qs-text">
                    <span class="font-semibold">{{ $violationCount }}</span>
                    <span class="text-qs-muted">· {{ __('score :s', ['s' => (int) $session->violation_score]) }}</span>
                </dd>
            </div>
        </dl>

        @if ($isHeld && $canManageResults)
            <div class="mt-4 rounded-lg border border-qs-soft bg-qs-soft/10 px-3 py-2">
                <label for="override-note" class="block text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Override note (optional)') }}</label>
                <textarea id="override-note" rows="2" class="mt-1 w-full max-w-xl rounded-md border border-qs-soft bg-white px-2 py-1.5 text-sm text-qs-text focus:border-qs-primary focus:outline-none focus:ring-1 focus:ring-qs-primary/25" placeholder="{{ __('Short note for audit trail') }}"></textarea>
            </div>
        @endif
    </section>

    {{-- 2. Face Capture + Violation Log --}}
    <div class="mt-5 grid gap-4 lg:grid-cols-5">
        {{-- Face capture --}}
        <section class="qs-surface rounded-2xl border border-qs-soft bg-white p-4 shadow-sm sm:p-5 lg:col-span-2">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Face capture') }}</h3>
            <p class="mt-0.5 text-xs text-qs-muted">{{ __('Verification photo and snapshots captured during the session.') }}</p>

            @if ($verificationEvidenceUrl && ! ($isAssignmentSession ?? false))
                <a href="{{ $verificationEvidenceUrl }}" target="_blank" rel="noopener noreferrer" class="mt-3 block">
                    <img src="{{ $verificationEvidenceUrl }}" alt="" loading="lazy" decoding="async" class="aspect-square w-full max-w-[14rem] rounded-lg border border-qs-soft object-cover" />
                </a>
                <p class="mt-1 text-[11px] text-qs-muted">{{ __('Verification photo at exam start.') }}</p>
            @endif

            @if (count($thumbnails) > 0)
                <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @foreach ($thumbnails as $img)
                        <a href="{{ $img['url'] }}" target="_blank" rel="noopener noreferrer" class="block">
                            <img src="{{ $img['url'] }}" alt="{{ $img['event_type'] }}" title="{{ $img['event_type'] }}" loading="lazy" decoding="async" class="aspect-square w-full rounded-md border border-qs-soft object-cover" />
                        </a>
                    @endforeach
                </div>
            @elseif (! $verificationEvidenceUrl)
                <p class="mt-3 rounded-md border border-dashed border-qs-soft bg-qs-soft/10 px-3 py-4 text-center text-xs text-qs-muted">{{ __('No face capture images recorded.') }}</p>
            @endif
        </section>

        {{-- Violation log --}}
        <section class="qs-surface rounded-2xl border border-qs-soft bg-white p-4 shadow-sm sm:p-5 lg:col-span-3">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-qs-text">{{ __('Violation log') }}</h3>
                    <p class="mt-0.5 text-xs text-qs-muted">{{ __('Latest 200 events, oldest first.') }}</p>
                </div>
                <div class="text-right text-xs">
                    <div class="text-qs-muted">{{ __('Tab switches') }} <span class="font-semibold text-qs-text">{{ (int) ($session->tab_switch_count ?? 0) }}</span></div>
                    <div class="text-qs-muted">{{ __('Face strikes') }} <span class="font-semibold text-qs-text">{{ (int) ($session->face_covered_strike_count ?? 0) }}</span></div>
                </div>
            </div>

            <div class="mt-3 max-h-96 overflow-y-auto rounded-lg border border-qs-soft">
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="sticky top-0 z-10 bg-qs-soft/30 text-[10px] font-semibold uppercase tracking-wide text-qs-muted">
                        <tr>
                            <th class="px-3 py-1.5">{{ __('Time') }}</th>
                            <th class="px-3 py-1.5">{{ __('Event') }}</th>
                            <th class="px-3 py-1.5">{{ __('Action') }}</th>
                            <th class="px-3 py-1.5">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-qs-soft/60">
                        @forelse ($timeline as $ev)
                            <tr @class([
                                'bg-rose-50/70' => $ev['is_auto_submit'],
                                'bg-amber-50/70' => $ev['is_warning'] && ! $ev['is_auto_submit'],
                            ])>
                                <td class="whitespace-nowrap px-3 py-1.5 text-qs-text">{{ $ev['at']?->timezone($tz)->format('H:i:s') }}</td>
                                <td class="px-3 py-1.5 text-qs-text">{{ $ev['event_type'] }}</td>
                                <td class="px-3 py-1.5 font-medium text-qs-text">{{ $ev['action'] }}</td>
                                <td class="px-3 py-1.5 text-qs-muted">{{ $ev['metadata_summary'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-qs-muted">{{ __('No violations recorded.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- 3. Question review (collapsed accordion) --}}
    @if (! empty($questionReview))
        <section class="mt-5 qs-surface rounded-2xl border border-qs-soft bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-qs-text">{{ __('Question review') }}</h3>
                    <p class="mt-0.5 text-xs text-qs-muted">{{ __('Tap a question to expand. Each row shows the student answer, the correct answer, and the outcome.') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" data-qs-expand-all class="rounded-md border border-qs-soft bg-qs-soft/15 px-2.5 py-1 text-[11px] font-semibold text-qs-text hover:bg-qs-soft/30">{{ __('Expand all') }}</button>
                    <button type="button" data-qs-collapse-all class="rounded-md border border-qs-soft bg-qs-soft/15 px-2.5 py-1 text-[11px] font-semibold text-qs-text hover:bg-qs-soft/30">{{ __('Collapse all') }}</button>
                </div>
            </div>

            <ol class="mt-3 space-y-2" data-qs-question-list>
                @foreach ($questionReview as $row)
                    @php
                        $outcome = $row['outcome'] ?? 'neutral';
                        $outcomeLabel = match ($outcome) {
                            'correct' => __('Correct'),
                            'incorrect' => __('Wrong'),
                            'partial' => __('Partial'),
                            default => __('Ungraded'),
                        };
                        $outcomeChip = match ($outcome) {
                            'correct' => 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200',
                            'incorrect' => 'bg-rose-50 text-rose-800 ring-1 ring-rose-200',
                            'partial' => 'bg-amber-50 text-amber-900 ring-1 ring-amber-200',
                            default => 'bg-slate-50 text-slate-700 ring-1 ring-slate-200',
                        };
                    @endphp
                    <li>
                        <details class="group rounded-xl border border-qs-soft bg-white open:border-qs-primary/40 open:shadow-sm">
                            <summary class="flex cursor-pointer list-none items-start gap-3 rounded-xl px-3 py-2.5 hover:bg-qs-soft/15 sm:px-4">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-qs-soft/40 text-[11px] font-semibold text-qs-text">{{ $row['number'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-qs-text group-open:whitespace-normal group-open:overflow-visible group-open:text-clip">{{ $row['question_text'] }}</span>
                                    <span class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-qs-muted">
                                        <span class="rounded bg-qs-soft/40 px-1.5 py-0.5 font-semibold uppercase tracking-wide">{{ $row['type_label'] }}</span>
                                        <span class="tabular-nums">{{ $fmtMark($row['points']) }}/{{ $fmtMark($row['max']) }} {{ __('marks') }}</span>
                                    </span>
                                </span>
                                <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $outcomeChip }}">{{ $outcomeLabel }}</span>
                                <i class="fa-solid fa-chevron-down ml-1 mt-1 text-qs-muted transition-transform group-open:rotate-180" aria-hidden="true"></i>
                            </summary>
                            <div class="border-t border-qs-soft px-3 py-3 sm:px-4">
                                @if (! empty($row['options']))
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Options') }}</p>
                                    <ol class="mt-1 list-[upper-alpha] space-y-0.5 pl-5 text-sm text-qs-text">
                                        @foreach ($row['options'] as $opt)
                                            <li>{{ $opt }}</li>
                                        @endforeach
                                    </ol>
                                @endif
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Student answer') }}</p>
                                        <pre class="mt-1 max-h-56 overflow-y-auto whitespace-pre-wrap break-words rounded-md border border-qs-soft bg-qs-soft/10 px-2.5 py-2 text-xs text-qs-text">{{ $row['your_answer'] ?? __('No answer recorded') }}</pre>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Correct answer') }}</p>
                                        <pre class="mt-1 max-h-56 overflow-y-auto whitespace-pre-wrap break-words rounded-md border border-emerald-200 bg-emerald-50/40 px-2.5 py-2 text-xs text-qs-text">{{ $row['correct_answer'] ?? '—' }}</pre>
                                    </div>
                                </div>
                                @if (! empty($row['feedback']))
                                    <div class="mt-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Grader feedback') }}</p>
                                        <p class="mt-1 whitespace-pre-wrap rounded-md border border-qs-soft bg-white px-2.5 py-2 text-xs text-qs-text">{{ $row['feedback'] }}</p>
                                    </div>
                                @endif
                            </div>
                        </details>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Assignment-specific extras: submitted files + student text response --}}
    @if (! empty($assignmentSubmissionFiles) && count($assignmentSubmissionFiles))
        <section class="mt-5 qs-surface rounded-2xl border border-qs-soft bg-white p-4 shadow-sm sm:p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Submitted files') }}</h3>
            <ul class="mt-2 divide-y divide-qs-soft text-sm">
                @foreach ($assignmentSubmissionFiles as $f)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span class="min-w-0 truncate font-medium text-qs-text">{{ $f->original_filename }}</span>
                        <a href="{{ route('examiner.exam-sessions.assignment-files.download', ['examSession' => $session, 'assignmentFile' => $f]) }}" class="shrink-0 text-xs font-semibold text-sky-700 underline-offset-2 hover:underline">{{ __('Download') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (($isAssignmentSession ?? false) && (($assignmentSessionContext['allows_text'] ?? true)))
        <section class="mt-5 qs-surface rounded-2xl border border-qs-soft bg-white p-4 shadow-sm sm:p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Student response') }}</h3>
            <div class="mt-2 max-h-64 overflow-y-auto rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm text-qs-text whitespace-pre-wrap">{{ filled($assignmentStudentResponse ?? null) ? $assignmentStudentResponse : '—' }}</div>
            @if (($assignmentSessionContext['disable_paste'] ?? false) && (int) ($assignmentPasteAttemptCount ?? 0) > 0)
                <p class="mt-2 text-xs text-amber-800">
                    {{ __('Blocked paste attempts logged: :n', ['n' => number_format((int) $assignmentPasteAttemptCount)]) }}
                </p>
            @endif
        </section>
    @endif

    <div class="mt-6">
        <a href="{{ $back }}" class="text-sm font-medium text-qs-muted underline-offset-2 hover:text-qs-text hover:underline">← {{ __('Back to scores') }}</a>
    </div>

    @push('scripts')
        <script>
            (function () {
                const list = document.querySelector('[data-qs-question-list]');
                if (list) {
                    document.querySelector('[data-qs-expand-all]')?.addEventListener('click', function () {
                        list.querySelectorAll('details').forEach(d => d.open = true);
                    });
                    document.querySelector('[data-qs-collapse-all]')?.addEventListener('click', function () {
                        list.querySelectorAll('details').forEach(d => d.open = false);
                    });
                }
            })();
        </script>

        @if ($isHeld && $canManageResults)
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
        @endif
    @endpush
</x-layouts.examiner>
