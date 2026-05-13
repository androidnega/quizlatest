<x-layouts.examiner>
    <x-slot name="title">{{ __('Results by class') }}</x-slot>
    <x-slot name="subtitle">{{ $exam->title }} — {{ $exam->course?->code }}</x-slot>

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('examiner.quizzes.workspace', ['exam' => $exam, 'tab' => 'sessions']) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">← {{ __('Back to exam sessions') }}</a>
        <a href="{{ route('examiner.quizzes.workspace', $exam) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">{{ __('Edit assessment') }}</a>
    </div>

    <section class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Class result summary') }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ __('Only classes linked to this exam course are shown.') }}</p>
        @if ($classrooms->isEmpty())
            <p class="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-600">{{ __('No classes linked to this exam course yet.') }}</p>
        @else
            <div class="qs-table-wrap mt-4 border border-slate-200/80">
                <table class="qs-table">
                    <thead>
                        <tr>
                            <th>{{ __('Class name') }}</th>
                            <th>{{ __('Submitted') }}</th>
                            <th>{{ __('Average score') }}</th>
                            <th>{{ __('Pending manual') }}</th>
                            <th>{{ __('Held') }}</th>
                            <th class="text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classrooms as $classroom)
                            @php($stats = $statsRows->get($classroom->id))
                            <tr>
                                <td class="text-sm font-medium text-qs-text">{{ $classroom->name }}</td>
                                <td class="text-sm text-qs-text">{{ (int) ($stats->submitted_count ?? 0) }}</td>
                                <td class="text-sm text-qs-text">{{ isset($stats->average_score) ? number_format((float) $stats->average_score, 2) : '—' }}</td>
                                <td class="text-sm text-qs-text">{{ (int) ($stats->pending_manual_count ?? 0) }}</td>
                                <td class="text-sm text-qs-text">{{ (int) ($stats->held_count ?? 0) }}</td>
                                <td class="text-right">
                                    <a href="{{ route('examiner.exams.classes.results', [$exam, $classroom]) }}" class="inline-flex min-h-[36px] items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('View class results') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.examiner>
