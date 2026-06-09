<x-layouts.examiner>
    <x-slot name="title">{{ $classroom->name }}</x-slot>
    <x-slot name="subtitle">{{ $course->code }} — {{ __('Your quizzes and class records') }}</x-slot>

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('examiner.courses.show', $course) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">← {{ __('Back to course') }}</a>
        <span class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-600">
            {{ __('Level') }} {{ $classroom->level?->name ?? $classroom->level?->code ?? '—' }}
        </span>
    </div>

    <section class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('My quizzes in this class context') }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ __('Only quizzes you created for this course are listed.') }}</p>

        @if ($myExams->isEmpty())
            <p class="mt-4 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-600">{{ __('No quizzes created for this course yet.') }}</p>
        @else
            <div class="qs-table-wrap mt-4 border border-slate-200/80">
                <table class="qs-table">
                    <thead>
                        <tr>
                            <th>{{ __('Quiz') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Submitted') }}</th>
                            <th>{{ __('Average score') }}</th>
                            <th>{{ __('Pending manual') }}</th>
                            <th>{{ __('Held') }}</th>
                            <th class="text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($myExams as $exam)
                            @php($row = $examRows->get($exam->id))
                            <tr>
                                <td class="text-sm font-medium text-qs-text">{{ $exam->title }}</td>
                                <td class="text-xs uppercase text-qs-muted">{{ $exam->status }}</td>
                                <td class="text-sm text-qs-text">{{ (int) ($row->submitted_count ?? 0) }}</td>
                                <td class="text-sm text-qs-text">{{ isset($row->average_score) ? number_format((float) $row->average_score, 2) : '—' }}</td>
                                <td class="text-sm text-qs-text">{{ (int) ($row->pending_manual_count ?? 0) }}</td>
                                <td class="text-sm text-qs-text">{{ (int) ($row->held_count ?? 0) }}</td>
                                <td class="text-right">
                                    <a href="{{ route('examiner.quizzes.workspace', ['exam' => $exam, 'tab' => 'sessions']) }}" class="inline-flex min-h-[36px] items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('Open sessions') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.examiner>
