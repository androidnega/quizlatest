<x-layouts.coordinator>
    <x-slot name="title">Exam sessions</x-slot>
    <x-slot name="subtitle">{{ $exam->title }} — {{ $exam->course?->code }}</x-slot>

    <div class="mb-5">
        <a href="{{ route('examiner.exams.index') }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">← Back to exams</a>
    </div>

    <div class="qs-card rounded-xl p-5 shadow-sm">
        <form method="GET" action="{{ route('coordinator.exams.sessions.index', $exam) }}" class="mb-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-qs-soft">Status</label>
                <select name="status" class="qs-input min-w-[11rem] py-2">
                    <option value="">All</option>
                    <option value="in_progress" @selected(request('status') === 'in_progress')>In progress</option>
                    <option value="submitted" @selected(request('status') === 'submitted')>Submitted</option>
                    <option value="held" @selected(request('status') === 'held')>Held</option>
                    <option value="pending_manual" @selected(request('status') === 'pending_manual')>Pending manual</option>
                    <option value="graded" @selected(request('status') === 'graded')>Graded</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-qs-soft">Risk state</label>
                <select name="risk_state" class="qs-input min-w-[11rem] py-2">
                    <option value="">All</option>
                    @foreach ($riskStates as $rs)
                        <option value="{{ $rs }}" @selected(request('risk_state') === $rs)>{{ $rs }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="qs-btn-primary py-2 text-sm">Filter</button>
            @if (request()->hasAny(['status', 'risk_state']))
                <a href="{{ route('coordinator.exams.sessions.index', $exam) }}" class="qs-btn-secondary py-2 text-sm">Clear</a>
            @endif
        </form>

        <div class="qs-table-wrap overflow-x-auto rounded-lg border border-qs-soft">
            <table class="qs-table min-w-full">
                <thead>
                    <tr>
                        <th class="text-left">Student</th>
                        <th class="text-left">Status</th>
                        <th class="text-left">Score</th>
                        <th class="text-left">Risk</th>
                        <th class="text-right">Action</th>
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
                                <a href="{{ route('coordinator.exam-sessions.show', $row) }}" class="qs-btn-secondary inline-block px-3 py-1.5 text-xs">View session</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-qs-soft">No sessions match your filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $sessions->links() }}</div>
    </div>
</x-layouts.coordinator>
