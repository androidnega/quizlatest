<x-layouts.examiner>
    <x-slot name="title">{{ ($isAssignmentSession ?? false) ? __('Submission review') : __('Session review') }}</x-slot>
    <x-slot name="subtitle">{{ $session->exam?->title }}</x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('examiner.quizzes.workspace', ['exam' => $session->exam, 'tab' => 'sessions']) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">← {{ __('Sessions for this assessment') }}</a>
        @if ($session->exam)
            <span class="text-qs-muted">·</span>
            <a href="{{ route('examiner.exams.analytics.show', $session->exam) }}" class="text-sm font-medium text-sky-700 underline-offset-2 hover:underline">{{ __('Assessment analytics') }}</a>
        @endif
        @if (! empty($classResultsUrl))
            <span class="text-qs-muted">·</span>
            <a href="{{ $classResultsUrl }}" class="text-sm font-medium text-qs-muted underline-offset-2 hover:text-qs-text hover:underline">{{ __('Back to class results') }}</a>
        @endif
    </div>

    @if (! empty($assignmentSessionContext) && $session->exam)
        @php $ac = $assignmentSessionContext; $tz = config('app.timezone'); @endphp
        <div class="qs-card rounded-xl border border-sky-200/80 bg-sky-50/50 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Assignment submission') }}</h3>
            <p class="mt-1 text-xs text-slate-700">{{ __('This attempt is coursework. Use the grading queue for marks and feedback; release grades from the assignment workspace when ready.') }}</p>
            @if (filled($ac['instructions']))
                <div class="mt-3 max-h-36 overflow-y-auto rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 whitespace-pre-wrap">{{ $ac['instructions'] }}</div>
            @endif
            <dl class="mt-4 grid gap-2 text-sm text-slate-800 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-slate-500">{{ __('Due') }}</dt>
                    <dd class="font-semibold">{{ $ac['due_at']?->timezone($tz)->format('M j, Y · H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">{{ __('Grade release') }}</dt>
                    <dd class="font-semibold">{{ $ac['grades_released'] ? __('Released to students') : __('Not released yet') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">{{ __('Submitted at') }}</dt>
                    <dd class="font-semibold">{{ $session->end_time?->timezone($tz)->format('M j, Y · H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">{{ __('Attachment rule') }}</dt>
                    <dd class="font-semibold">
                        @if (! ($ac['allows_files'] ?? false))
                            {{ __('No file upload') }}
                        @elseif ($ac['attachment_required'] ?? false)
                            {{ __('File required') }}
                        @else
                            {{ __('Optional file') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">{{ __('Submission status') }}</dt>
                    <dd class="font-semibold">
                        {{ $session->status === 'submitted' ? ($ac['submitted_late'] ? __('Submitted late') : __('Submitted on time')) : __('In progress') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">{{ __('Marking') }}</dt>
                    <dd class="font-semibold">{{ str_replace('_', ' ', $workflowStatus) }}</dd>
                </div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('examiner.quizzes.workspace', ['exam' => $session->exam, 'tab' => 'overview']) }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">{{ __('Assignment workspace') }}</a>
                <a href="{{ route('examiner.grading.pending') }}" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">{{ __('Grading queue') }}</a>
            </div>
        </div>
    @endif

    <div class="space-y-6">
        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">Student</h3>
            <p class="mt-2 text-sm text-qs-text">{{ $session->student?->name ?? '—' }}</p>
            @if ($session->student?->index_number)
                <p class="mt-1 text-sm text-qs-muted">Index: {{ $session->student->index_number }}</p>
            @endif
        </div>

        @if ($verificationEvidenceUrl && ! ($isAssignmentSession ?? false))
            <div class="qs-card rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">{{ __('Exam verification photo') }}</h3>
                <p class="mt-1 text-xs text-qs-muted">{{ __('Captured once at exam start for session evidence. Camera monitoring may continue during the exam.') }}</p>
                <div class="mt-3">
                    <a href="{{ $verificationEvidenceUrl }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ $verificationEvidenceUrl }}" alt="" loading="lazy" decoding="async" class="max-h-48 max-w-full rounded-lg border border-qs-soft object-contain" />
                    </a>
                </div>
            </div>
        @endif

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">{{ __('Assessment') }}</h3>
            <p class="mt-2 text-sm font-medium text-qs-text">{{ $session->exam?->title }}</p>
            <p class="mt-1 text-sm text-qs-muted">{{ $session->exam?->course?->code }} — {{ $session->exam?->course?->title }}</p>
        </div>

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">Score summary</h3>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-qs-muted">Workflow</dt>
                    <dd class="font-medium text-qs-text">{{ str_replace('_', ' ', $workflowStatus) }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">Score</dt>
                    <dd class="font-medium text-qs-text">{{ $session->result ? $session->result->score : '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">{{ __('Risk & violations') }}</h3>
            <p class="mt-2 text-xs leading-relaxed text-qs-muted">
                {{ __('Proctoring signals do not automatically deduct marks. They support warnings, flags, auto-submit where enabled, holds, and examiner review.') }}
            </p>
            @if ($isAssignmentSession ?? false)
                <p class="mt-2 text-xs text-qs-muted">{{ __('Coursework typically has little or no live proctoring; counts below are mostly informational.') }}</p>
            @endif
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-qs-muted">{{ __('Risk state') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $session->risk_state }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Violation score') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $session->violation_score }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Violation count') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $session->violation_count }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Tab switches (server)') }}</dt>
                    <dd class="font-medium text-qs-text">{{ (int) ($session->tab_switch_count ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Auto-submit reason') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $session->auto_submit_reason_code ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Face obstruction strikes') }}</dt>
                    <dd class="font-medium text-qs-text">{{ (int) ($session->face_covered_strike_count ?? 0) }}</dd>
                </div>
            </dl>
        </div>

        @if ($isHeld && $canManageResults)
            <div class="qs-card rounded-xl border border-qs-accent/40 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-qs-text">Held result review</h3>
                <p class="mt-2 text-sm text-qs-muted">Choose an action. Changes apply immediately.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" id="held-release" class="qs-btn-primary px-4 py-2 text-sm">Release result</button>
                    <button type="button" id="held-confirm" class="rounded-lg border border-qs-danger bg-qs-bg px-4 py-2 text-sm font-semibold text-qs-danger hover:bg-qs-danger-soft">Confirm violation</button>
                    <button type="button" id="held-override" class="qs-btn-secondary px-4 py-2 text-sm">Override</button>
                </div>
                <div class="mt-4">
                    <label for="override-note" class="mb-1 block text-xs font-semibold text-qs-muted">Override note (optional)</label>
                    <textarea id="override-note" rows="2" class="qs-input w-full max-w-lg py-2 text-sm" placeholder="Short note for audit trail"></textarea>
                </div>
            </div>
        @elseif ($isHeld)
            <div class="qs-card rounded-xl border border-qs-soft p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-qs-text">Held result</h3>
                <p class="mt-2 text-sm text-qs-muted">Only an examiner assigned to this course can release, confirm, or override this result.</p>
            </div>
        @endif

        @if (! empty($invalidateForRetakeUrl))
            <div class="qs-card rounded-xl border border-rose-200/80 bg-rose-50/90 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-rose-950">{{ __('Allow another attempt') }}</h3>
                <p class="mt-2 text-xs text-rose-900/85">{{ __('Remove this student’s attempt, result, and proctoring events for this exam so they can start again from the exam entry screen. Use when a session must be voided or retakes are authorized.') }}</p>
                <form method="POST" action="{{ $invalidateForRetakeUrl }}" class="mt-4" onsubmit="return confirm(@json(__('Clear this attempt permanently? The student will be able to start the exam again.')));">
                    @csrf
                    <button type="submit" class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-950 hover:bg-rose-100">{{ __('Clear attempt & allow retake') }}</button>
                </form>
            </div>
        @endif

        @if (! empty($assignmentSubmissionFiles) && count($assignmentSubmissionFiles))
            <div class="qs-card rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">{{ __('Submitted files') }}</h3>
                <ul class="mt-3 divide-y divide-qs-soft text-sm">
                    @foreach ($assignmentSubmissionFiles as $f)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="min-w-0 truncate font-medium text-qs-text">{{ $f->original_filename }}</span>
                            <a href="{{ route('examiner.exam-sessions.assignment-files.download', ['examSession' => $session, 'assignmentFile' => $f]) }}" class="shrink-0 text-xs font-semibold text-sky-700 underline-offset-2 hover:underline">{{ __('Download') }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (($isAssignmentSession ?? false) && (($assignmentSessionContext['allows_text'] ?? true)))
            <div class="qs-card rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">{{ __('Student response') }}</h3>
                <div class="mt-3 max-h-64 overflow-y-auto rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm text-qs-text whitespace-pre-wrap">{{ filled($assignmentStudentResponse ?? null) ? $assignmentStudentResponse : '—' }}</div>
                @if (($assignmentSessionContext['disable_paste'] ?? false) && (int) ($assignmentPasteAttemptCount ?? 0) > 0)
                    <p class="mt-2 text-xs text-amber-800">
                        {{ __('Blocked paste attempts logged: :n', ['n' => number_format((int) $assignmentPasteAttemptCount)]) }}
                    </p>
                @endif
            </div>
        @endif

        <div class="qs-card rounded-xl p-5 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">Proctoring timeline</h3>
            <p class="mt-1 text-xs text-qs-muted">Latest 200 events, oldest first.</p>
            <div class="qs-table-wrap mt-4 overflow-x-auto rounded-lg border border-qs-soft">
                <table class="qs-table min-w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Time</th>
                            <th class="text-left">Event</th>
                            <th class="text-left">Action</th>
                            <th class="text-left">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($timeline as $ev)
                            <tr @class([
                                'bg-qs-danger-soft/90 ring-1 ring-qs-danger/15' => $ev['is_auto_submit'],
                                'bg-qs-accent/15 ring-1 ring-qs-accent/25' => $ev['is_warning'] && ! $ev['is_auto_submit'],
                            ])>
                                <td class="whitespace-nowrap text-sm text-qs-text">{{ $ev['at']?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
                                <td class="text-sm text-qs-text">{{ $ev['event_type'] }}</td>
                                <td class="text-sm font-medium text-qs-text">{{ $ev['action'] }}</td>
                                <td class="text-xs text-qs-muted">{{ $ev['metadata_summary'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-qs-muted">No proctoring events recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (count($thumbnails) > 0)
            <div class="qs-card rounded-xl p-5 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-qs-muted">Image evidence</h3>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($thumbnails as $img)
                        <figure class="text-center">
                            <a href="{{ $img['url'] }}" target="_blank" rel="noopener noreferrer" class="block">
                                <img src="{{ $img['url'] }}" alt="" loading="lazy" decoding="async" class="h-24 w-24 rounded-lg border border-qs-soft object-cover" />
                            </a>
                            <figcaption class="mt-1 max-w-[6rem] truncate text-xs text-qs-muted">{{ $img['event_type'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if ($isHeld && $canManageResults)
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
</x-layouts.examiner>
