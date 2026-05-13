<x-layouts.examiner>
    <x-slot name="title">{{ __('Class results') }}</x-slot>
    <x-slot name="subtitle">{{ $classroom->name }} · {{ $exam->title }}</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        @if (session('status'))
            <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-900">{{ session('status') }}</span>
        @endif
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('examiner.teaching-classes.show', $classroom) }}" class="font-medium text-qs-text underline-offset-2 hover:underline">← {{ __('Back to class') }}</a>
        <span class="text-qs-muted">·</span>
        <a href="{{ route('examiner.exams.classes.summary', $exam) }}" class="font-medium text-qs-muted underline-offset-2 hover:text-qs-text hover:underline">{{ __('Summary by class') }}</a>
        <span class="text-qs-muted">·</span>
        <a href="{{ route('examiner.quizzes.workspace', ['exam' => $exam, 'tab' => 'sessions']) }}" class="font-medium text-qs-muted underline-offset-2 hover:text-qs-text hover:underline">{{ __('All exam sessions') }}</a>
    </div>

    <section class="rounded-xl border border-qs-soft bg-white p-3 shadow-sm sm:p-4">
        <h2 class="px-1 text-sm font-semibold text-qs-text">{{ __('Students in this class') }}</h2>
        <p class="px-1 text-xs text-qs-muted">{{ __('One row per student. Open session for proctoring detail, or clear an attempt to allow a fresh start.') }}</p>

        <div class="mt-2 overflow-x-auto rounded-lg border border-qs-soft">
            <table class="w-full min-w-[52rem] border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-qs-soft bg-qs-bg/80 text-[10px] font-semibold uppercase tracking-wide text-qs-muted">
                        <th class="px-2 py-1.5">{{ __('Student') }}</th>
                        <th class="px-2 py-1.5">{{ __('Index') }}</th>
                        <th class="px-2 py-1.5">{{ __('Session') }}</th>
                        <th class="px-2 py-1.5">{{ __('Result') }}</th>
                        <th class="px-2 py-1.5">{{ __('Score') }}</th>
                        <th class="px-2 py-1.5">{{ __('Risk') }}</th>
                        <th class="px-2 py-1.5">{{ __('Flags') }}</th>
                        <th class="px-2 py-1.5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-qs-soft/90">
                    @foreach ($students as $student)
                        @php
                            $session = $latestSessionByStudentId->get($student->id);
                            $result = $resultsByStudentId->get($student->id);
                            $flagged = $session && (
                                in_array($session->risk_state, ['suspicious', 'critical', 'locked'], true)
                                || ($result && $result->status === 'held')
                            );
                        @endphp
                        <tr @class(['bg-amber-50/80' => $flagged])>
                            <td class="max-w-[10rem] truncate px-2 py-1 font-medium text-qs-text" title="{{ $student->name }}">{{ $student->name }}</td>
                            <td class="whitespace-nowrap px-2 py-1 font-mono text-[11px] text-qs-muted">{{ $student->index_number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-1 text-qs-text">
                                @if ($session)
                                    {{ str_replace('_', ' ', $session->status) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-2 py-1 text-qs-text">{{ $result ? str_replace('_', ' ', $result->status) : '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-1 tabular-nums text-qs-text">{{ $result ? $result->score : '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-1 text-qs-text">{{ $session ? $session->risk_state : '—' }}</td>
                            <td class="whitespace-nowrap px-2 py-1">
                                @if ($flagged)
                                    <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-900">{{ __('Review') }}</span>
                                @else
                                    <span class="text-qs-muted">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-2 py-1 text-right">
                                @if ($session)
                                    <a href="{{ route('examiner.exam-sessions.show', $session) }}" class="font-semibold text-qs-primary underline-offset-2 hover:underline">{{ __('Session') }}</a>
                                @else
                                    <span class="text-qs-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 px-1">{{ $students->links() }}</div>
    </section>
</x-layouts.examiner>
