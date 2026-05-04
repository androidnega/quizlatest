<x-layouts.examiner>
    <x-slot name="title">Exam sessions</x-slot>
    <x-slot name="subtitle">{{ $exam->title }} — {{ $exam->course?->code }}</x-slot>

    <div class="mb-5">
        <a href="{{ route('examiner.exams.index') }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">← {{ __('Back to exams') }}</a>
    </div>

    <div class="qs-card mb-6 rounded-xl p-5 shadow-sm">
        <h2 class="text-base font-semibold text-qs-text">{{ __('Exam analytics') }}</h2>
        <p class="mt-1 text-xs text-qs-muted">{{ __('Overview for all attempts on this exam (not affected by table filters).') }}</p>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Students (distinct)') }}</dt>
                <dd class="mt-1 text-lg font-semibold text-qs-text">{{ $analytics['total_students'] }}</dd>
            </div>
            <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Submitted') }}</dt>
                <dd class="mt-1 text-lg font-semibold text-qs-text">{{ $analytics['submitted_count'] }}</dd>
            </div>
            <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Held') }}</dt>
                <dd class="mt-1 text-lg font-semibold text-qs-text">{{ $analytics['held_count'] }}</dd>
            </div>
            <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Pending manual') }}</dt>
                <dd class="mt-1 text-lg font-semibold text-qs-text">{{ $analytics['pending_manual_count'] }}</dd>
            </div>
            <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Avg score') }}</dt>
                <dd class="mt-1 text-lg font-semibold text-qs-text">{{ $analytics['average_score'] !== null ? $analytics['average_score'] : '—' }}</dd>
            </div>
            <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('High-risk sessions') }}</dt>
                <dd class="mt-1 text-lg font-semibold text-qs-text">{{ $analytics['high_risk_session_count'] }}</dd>
                <p class="mt-0.5 text-[10px] text-qs-muted">{{ __('Suspicious, critical, or locked') }}</p>
            </div>
        </dl>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-qs-text">{{ __('Risk distribution') }}</h3>
                <p class="text-xs text-qs-muted">{{ __('Critical includes locked sessions.') }}</p>
                <ul class="mt-2 space-y-1 text-sm text-qs-text">
                    @foreach ($analytics['risk_distribution'] as $label => $count)
                        <li class="flex justify-between rounded border border-qs-soft/80 bg-qs-bg px-3 py-1.5">
                            <span>{{ $label }}</span>
                            <span class="font-semibold">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-qs-text">{{ __('Top violation signals') }}</h3>
                <p class="text-xs text-qs-muted">{{ __('Totals from proctoring events for this exam.') }}</p>
                <ul class="mt-2 space-y-1 text-sm text-qs-text">
                    @foreach ($analytics['violation_totals'] as $type => $count)
                        <li class="flex justify-between rounded border border-qs-soft/80 bg-qs-bg px-3 py-1.5">
                            <span>{{ str_replace('_', ' ', $type) }}</span>
                            <span class="font-semibold">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Flagged students') }}</h3>
            <p class="mt-1 text-xs text-qs-muted">{{ __('High risk session or held result (max 100, by violation count).') }}</p>
            <div class="qs-table-wrap mt-3">
                <table class="qs-table min-w-full">
                    <thead>
                        <tr>
                            <th class="text-left">{{ __('Student') }}</th>
                            <th class="text-left">{{ __('Risk') }}</th>
                            <th class="text-left">{{ __('Violations') }}</th>
                            <th class="text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($analytics['flagged_sessions'] as $fs)
                            <tr>
                                <td class="text-sm font-medium text-qs-text">{{ $fs->student?->name ?? '—' }}</td>
                                <td class="text-sm text-qs-text">{{ $fs->risk_state }}</td>
                                <td class="text-sm text-qs-text">{{ $fs->violation_count }}</td>
                                <td class="text-right">
                                    <a href="{{ route('examiner.exam-sessions.show', $fs) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-3 py-2 text-xs font-semibold">{{ __('View session') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-sm text-qs-muted">{{ __('No flagged sessions.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="qs-card rounded-xl p-5 shadow-sm">
        <form method="GET" action="{{ route('examiner.exams.sessions.index', $exam) }}" class="mb-5 grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-end">
            <div class="sm:col-span-1 lg:min-w-[11rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Status') }}</label>
                <select name="status" class="qs-input mt-0 min-h-[44px] w-full py-2.5 lg:min-w-[11rem]">
                    <option value="">{{ __('All') }}</option>
                    <option value="in_progress" @selected(request('status') === 'in_progress')>{{ __('In progress') }}</option>
                    <option value="submitted" @selected(request('status') === 'submitted')>{{ __('Submitted') }}</option>
                    <option value="held" @selected(request('status') === 'held')>{{ __('Held') }}</option>
                    <option value="pending_manual" @selected(request('status') === 'pending_manual')>{{ __('Pending manual') }}</option>
                    <option value="graded" @selected(request('status') === 'graded')>{{ __('Graded') }}</option>
                </select>
            </div>
            <div class="sm:col-span-1 lg:min-w-[11rem]">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Risk state') }}</label>
                <select name="risk_state" class="qs-input mt-0 min-h-[44px] w-full py-2.5 lg:min-w-[11rem]">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($riskStates as $rs)
                        <option value="{{ $rs }}" @selected(request('risk_state') === $rs)>{{ $rs }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row lg:col-span-initial lg:items-end">
                <button type="submit" class="qs-btn-primary min-h-[44px] w-full px-4 text-sm sm:w-auto">{{ __('Filter') }}</button>
                @if (request()->hasAny(['status', 'risk_state']))
                    <a href="{{ route('examiner.exams.sessions.index', $exam) }}" class="qs-btn-secondary inline-flex min-h-[44px] w-full items-center justify-center px-4 text-sm sm:w-auto">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>

        <div class="qs-table-wrap">
            <table class="qs-table min-w-full">
                <thead>
                    <tr>
                        <th class="text-left">{{ __('Student') }}</th>
                        <th class="text-left">{{ __('Status') }}</th>
                        <th class="text-left">{{ __('Score') }}</th>
                        <th class="text-left">{{ __('Risk') }}</th>
                        <th class="text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $row)
                        <tr>
                            <td class="text-sm font-medium text-qs-text">{{ $row->student?->name ?? '—' }}</td>
                            <td class="text-sm text-qs-text">{{ str_replace('_', ' ', $row->workflow_display_status) }}</td>
                            <td class="text-sm text-qs-text">
                                @if ($row->result)
                                    {{ $row->result->score }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-sm text-qs-text">{{ $row->risk_state }}</td>
                            <td class="text-right">
                                <a href="{{ route('examiner.exam-sessions.show', $row) }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-3 py-2 text-xs font-semibold">{{ __('View session') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-qs-muted">{{ __('No sessions match your filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $sessions->links() }}</div>
    </div>
</x-layouts.examiner>
