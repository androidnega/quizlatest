<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl qs-heading leading-tight">
                {{ $session->exam?->title ?? __('Result') }}
            </h2>
            <a href="{{ route('student.results.index') }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">{{ __('← All results') }}</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">
            @if ($resultStatus === 'held')
                <div class="qs-surface rounded-lg p-6 shadow-sm">
                    <p class="text-sm text-qs-text">{{ __('Your result is under review. Contact your examiner.') }}</p>
                </div>
            @elseif ($resultStatus === 'pending_manual')
                <div class="qs-surface rounded-lg p-6 shadow-sm">
                    <p class="text-sm text-qs-text">{{ __('Your result is pending manual grading.') }}</p>
                </div>
            @elseif ($resultStatus === 'graded' && $result)
                <div class="qs-surface rounded-lg p-6 shadow-sm">
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-qs-muted">{{ __('Score') }}</dt>
                            <dd class="font-semibold text-qs-text">{{ $result->score }} / {{ $session->exam?->total_marks ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-qs-muted">{{ __('Percentage') }}</dt>
                            <dd class="font-semibold text-qs-text">{{ $percentage !== null ? $percentage.'%' : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-qs-muted">{{ __('Status') }}</dt>
                            <dd class="font-semibold text-qs-text">{{ ucfirst(str_replace('_', ' ', $result->status)) }}</dd>
                        </div>
                    </dl>
                    @if ($examinerFeedback)
                        <div class="mt-5 border-t border-qs-soft pt-4">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Examiner feedback') }}</h3>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-qs-text">{{ $examinerFeedback }}</p>
                        </div>
                    @endif
                    <div class="mt-6">
                        <a href="{{ route('student.results.pdf', $session) }}" class="inline-flex items-center rounded-lg border border-qs-soft bg-qs-bg px-4 py-2 text-sm font-medium text-qs-text hover:bg-qs-card">
                            {{ __('Download PDF') }}
                        </a>
                    </div>
                </div>

                @if (count($breakdown) > 0)
                    <div class="qs-surface overflow-hidden rounded-lg shadow-sm">
                        <div class="border-b border-qs-soft px-5 py-3">
                            <h3 class="text-sm font-semibold text-qs-text">{{ __('Question breakdown') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-qs-card text-xs uppercase text-qs-muted">
                                    <tr>
                                        <th class="px-4 py-2">#</th>
                                        <th class="px-4 py-2">{{ __('Type') }}</th>
                                        <th class="px-4 py-2">{{ __('Points') }}</th>
                                        <th class="px-4 py-2">{{ __('Max') }}</th>
                                        @if ($showCorrectSummaries)
                                            <th class="px-4 py-2">{{ __('Correct') }}</th>
                                        @endif
                                        <th class="px-4 py-2">{{ __('Feedback') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-qs-soft">
                                    @foreach ($breakdown as $row)
                                        <tr>
                                            <td class="px-4 py-3 text-qs-text">{{ $row['number'] }}</td>
                                            <td class="px-4 py-3 text-qs-text">{{ str_replace('_', ' ', $row['type']) }}</td>
                                            <td class="px-4 py-3 text-qs-text">{{ $row['points'] }}</td>
                                            <td class="px-4 py-3 text-qs-text">{{ $row['max'] }}</td>
                                            @if ($showCorrectSummaries)
                                                <td class="px-4 py-3 text-qs-text">{{ $row['correct_summary'] ?? '—' }}</td>
                                            @endif
                                            <td class="px-4 py-3 text-qs-text">{{ $row['feedback'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @else
                <div class="qs-surface rounded-lg p-6 shadow-sm">
                    <p class="text-sm text-qs-text">{{ __('Your result is not available yet.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
