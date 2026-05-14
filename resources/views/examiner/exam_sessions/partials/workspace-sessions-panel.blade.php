@php
    $tm = max(1, (int) $exam->total_marks);
    $avgPct = isset($analytics['average_score']) && $analytics['average_score'] !== null
        ? round(((float) $analytics['average_score'] / $tm) * 100, 1)
        : null;
    $lowPct = $scoreLow !== null ? round(((float) $scoreLow / $tm) * 100, 2) : null;
    $highPct = $scoreHigh !== null ? round(((float) $scoreHigh / $tm) * 100, 2) : null;
    $exportQuery = array_filter(request()->only(['status', 'risk_state', 'integrity', 'q']));
    $exportHref = route('examiner.exams.sessions.export-csv', $exam);
    if ($exportQuery !== []) {
        $exportHref .= '?'.http_build_query($exportQuery);
    }
    $clearAttemptConfirmJs = json_encode(__('Clear this attempt? The student can start again.'));
    $invalidateRangeConfirmJs = json_encode(__('This removes attempts for every student who completed in this period. Continue?'));
    $workspaceUrl = route('examiner.quizzes.workspace', $exam);
    $analyticsUrl = route('examiner.exams.analytics.show', $exam);
@endphp

<div class="mb-3 flex flex-wrap gap-2 text-xs font-semibold">
    <a href="{{ $analyticsUrl }}" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-800 hover:bg-slate-50">{{ __('Open full analytics') }}</a>
</div>

<div class="w-full min-w-0 space-y-5 pb-1 text-qs-text">
    {{-- Retake window --}}
    <section class="qs-surface rounded-2xl px-4 py-3 shadow-sm sm:px-5">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Allow students to retake') }}</h2>
        <p class="mt-1 text-xs leading-snug text-qs-muted">
            {{ __('Clears completed attempts in the window so students can use the same link again. Use only when authorized.') }}
        </p>
        <form method="post" action="{{ route('examiner.exams.sessions.invalidate-range', $exam) }}" class="mt-2 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end" onsubmit='return confirm({!! $invalidateRangeConfirmJs !!});'>
            @csrf
            <div class="grid w-full max-w-xl gap-2 sm:grid-cols-2">
                <div>
                    <label for="retake-from-ws" class="block text-[11px] font-medium text-qs-muted">{{ __('From') }}</label>
                    <input
                        id="retake-from-ws"
                        type="datetime-local"
                        name="from"
                        value="{{ old('from') }}"
                        required
                        class="mt-0.5 w-full rounded-lg border border-qs-soft bg-qs-card px-2.5 py-1.5 text-sm text-qs-text focus:border-qs-primary focus:outline-none focus:ring-1 focus:ring-qs-primary/25"
                    />
                </div>
                <div>
                    <label for="retake-to-ws" class="block text-[11px] font-medium text-qs-muted">{{ __('To') }}</label>
                    <input
                        id="retake-to-ws"
                        type="datetime-local"
                        name="to"
                        value="{{ old('to') }}"
                        required
                        class="mt-0.5 w-full rounded-lg border border-qs-soft bg-qs-card px-2.5 py-1.5 text-sm text-qs-text focus:border-qs-primary focus:outline-none focus:ring-1 focus:ring-qs-primary/25"
                    />
                </div>
            </div>
            <button type="submit" class="qs-btn-secondary px-3 py-1.5 text-xs">
                {{ __('Clear sessions in range') }}
            </button>
        </form>
        @error('from')
            <p class="mt-2 text-xs text-qs-danger">{{ $message }}</p>
        @enderror
        @error('to')
            <p class="mt-2 text-xs text-qs-danger">{{ $message }}</p>
        @enderror
    </section>

    {{-- Summary metrics --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="qs-surface rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Students') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-qs-text">{{ $analytics['total_students'] }}</p>
        </div>
        <div class="qs-surface rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Average') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-qs-text">{{ $avgPct !== null ? $avgPct.'%' : '—' }}</p>
        </div>
        <div class="qs-surface rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Range') }}</p>
            <p class="mt-1 text-lg font-semibold tabular-nums leading-snug text-qs-text">
                @if ($lowPct !== null && $highPct !== null)
                    {{ $lowPct }}%–{{ $highPct }}%
                @else
                    —
                @endif
            </p>
        </div>
        <div class="qs-surface rounded-2xl p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Violations') }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-qs-text">{{ $violationEventTotal }}</p>
            <p class="mt-0.5 text-xs text-qs-muted">{{ trans_choice(':count student affected|:count students affected', $studentsWithViolations, ['count' => $studentsWithViolations]) }}</p>
        </div>
    </div>

    @if ($flaggedStudentsCount > 0)
        <div class="rounded-2xl border border-qs-soft bg-qs-soft/35 px-4 py-3 text-sm text-qs-text">
            {{ __(':count session(s) need review (risk or held). Open a row or filter by risk.', ['count' => $flaggedStudentsCount]) }}
        </div>
    @endif

    {{-- Results table --}}
    <section class="qs-surface overflow-hidden rounded-2xl shadow-sm">
        <div class="flex flex-col gap-3 border-b border-qs-soft px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <h2 class="text-sm font-semibold text-qs-text">{{ __('Student results') }}</h2>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ $exportHref }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-qs-soft bg-qs-card px-3 py-2 text-xs font-semibold text-qs-text shadow-sm hover:bg-qs-soft/40"
                >
                    <i class="fa-solid fa-download text-[11px] text-qs-muted" aria-hidden="true"></i>
                    {{ __('Export CSV') }}
                </a>
            </div>
        </div>

        <form method="get" action="{{ $workspaceUrl }}" class="border-b border-qs-soft/80 bg-qs-bg/50 px-4 py-3 sm:px-5">
            <input type="hidden" name="tab" value="sessions" />
            <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                <div class="min-w-[12rem] flex-1">
                    <label class="mb-1 block text-[11px] font-medium text-qs-muted" for="sessions-q-ws">{{ __('Search') }}</label>
                    <input
                        id="sessions-q-ws"
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="{{ __('Index or name…') }}"
                        class="w-full rounded-xl border border-qs-soft bg-qs-card px-3 py-2 text-sm text-qs-text placeholder:text-qs-muted/70 focus:border-qs-primary focus:outline-none focus:ring-2 focus:ring-qs-primary/25"
                    />
                </div>
                <div class="w-full min-w-[10rem] sm:w-auto">
                    <label class="mb-1 block text-[11px] font-medium text-qs-muted" for="sessions-status-ws">{{ __('Status') }}</label>
                    <select id="sessions-status-ws" name="status" class="w-full rounded-xl border border-qs-soft bg-qs-card px-3 py-2 text-sm text-qs-text focus:border-qs-primary focus:outline-none focus:ring-2 focus:ring-qs-primary/25">
                        <option value="">{{ __('All') }}</option>
                        <option value="in_progress" @selected(request('status') === 'in_progress')>{{ __('In progress') }}</option>
                        <option value="submitted" @selected(request('status') === 'submitted')>{{ __('Submitted') }}</option>
                        <option value="held" @selected(request('status') === 'held')>{{ __('Held') }}</option>
                        <option value="pending_manual" @selected(request('status') === 'pending_manual')>{{ __('Pending manual') }}</option>
                        <option value="graded" @selected(request('status') === 'graded')>{{ __('Graded') }}</option>
                    </select>
                </div>
                <div class="w-full min-w-[10rem] sm:w-auto">
                    <label class="mb-1 block text-[11px] font-medium text-qs-muted" for="sessions-risk-ws">{{ __('Risk') }}</label>
                    <select id="sessions-risk-ws" name="risk_state" class="w-full rounded-xl border border-qs-soft bg-qs-card px-3 py-2 text-sm text-qs-text focus:border-qs-primary focus:outline-none focus:ring-2 focus:ring-qs-primary/25">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($riskStates as $rs)
                            <option value="{{ $rs }}" @selected(request('risk_state') === $rs)>{{ $rs }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full min-w-[10rem] sm:w-auto">
                    <label class="mb-1 block text-[11px] font-medium text-qs-muted" for="sessions-integrity-ws">{{ __('Integrity') }}</label>
                    <select id="sessions-integrity-ws" name="integrity" class="w-full rounded-xl border border-qs-soft bg-qs-card px-3 py-2 text-sm text-qs-text focus:border-qs-primary focus:outline-none focus:ring-2 focus:ring-qs-primary/25">
                        <option value="">{{ __('All') }}</option>
                        <option value="flagged" @selected(($integrityFilter ?? null) === 'flagged')>{{ __('Flagged (risk or held)') }}</option>
                        <option value="auto_submitted" @selected(($integrityFilter ?? null) === 'auto_submitted')>{{ __('Auto-submitted') }}</option>
                        <option value="phone_detected" @selected(($integrityFilter ?? null) === 'phone_detected')>{{ __('Phone detected') }}</option>
                        <option value="tab_switch_limit" @selected(($integrityFilter ?? null) === 'tab_switch_limit')>{{ __('Tab switch limit') }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="qs-btn-primary">{{ __('Apply') }}</button>
                    @if (request()->hasAny(['status', 'risk_state', 'integrity', 'q']))
                        <a href="{{ $workspaceUrl.'?tab=sessions' }}" class="qs-btn-secondary">{{ __('Reset') }}</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-qs-soft bg-qs-soft/30">
                        <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wide text-qs-muted sm:px-5">{{ __('Student') }}</th>
                        <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Mark') }}</th>
                        <th class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wide text-qs-muted">{{ __('Risk') }}</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-qs-muted sm:px-5">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-qs-soft/80">
                    @forelse ($sessions as $row)
                        @php
                            $result = $row->result;
                            $pct = $result !== null ? round(((float) $result->score / $tm) * 100, 1) : null;
                        @endphp
                        <tr class="hover:bg-qs-soft/25">
                            <td class="max-w-[18rem] px-4 py-3 align-top text-qs-text sm:px-5">
                                <span class="font-mono text-xs text-qs-muted">{{ $row->student?->index_number ?: '—' }}</span>
                                <span class="mt-0.5 block text-sm font-medium">{{ $row->student?->name ?? '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top tabular-nums text-qs-text">
                                @if ($result !== null)
                                    <span>{{ $pct }}%</span>
                                    <span class="text-qs-muted/80"> · </span>
                                    <span class="text-qs-muted">{{ $result->score }}/{{ $exam->total_marks }}</span>
                                @else
                                    <span class="text-qs-muted/60">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top text-qs-muted">{{ $row->risk_state }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top sm:px-5">
                                <a href="{{ route('examiner.exam-sessions.show', $row) }}" class="qs-link text-sm font-semibold">{{ __('View') }}</a>
                                <span class="text-qs-soft">·</span>
                                <form method="post" action="{{ route('examiner.exam-sessions.invalidate-for-retake', $row) }}" class="inline" onsubmit='return confirm({!! $clearAttemptConfirmJs !!});'>
                                    @csrf
                                    <button type="submit" class="qs-link text-sm font-medium text-qs-muted hover:text-qs-danger">{{ __('Clear') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-sm text-qs-muted">{{ __('No sessions match your filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-qs-soft/80 bg-qs-bg/30 px-4 py-3 sm:px-5">{{ $sessions->links() }}</div>
    </section>
</div>
