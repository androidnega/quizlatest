<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">{{ __('Practice') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-qs-muted">{{ __('Unofficial practice only — not used for grades or official records.') }}</p>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('student.practice.materials.index') }}" class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm hover:bg-qs-card">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Materials') }}</p>
                    <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Course files') }}</p>
                </a>
                <a href="{{ route('student.practice.quizzes.create') }}" class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm hover:bg-qs-card">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Quiz') }}</p>
                    <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Generate practice quiz') }}</p>
                </a>
                <a href="{{ route('student.practice.summaries.index') }}" class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm hover:bg-qs-card">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Summary') }}</p>
                    <p class="mt-2 text-sm font-medium text-qs-text">{{ __('AI study summary') }}</p>
                </a>
                <a href="{{ route('student.practice.quizzes.index') }}" class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm hover:bg-qs-card">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Mine') }}</p>
                    <p class="mt-2 text-sm font-medium text-qs-text">{{ __('My practice quizzes') }}</p>
                </a>
            </div>

            <div class="rounded-xl border border-qs-soft bg-qs-card p-5">
                <h3 class="text-lg font-semibold text-qs-text">{{ __('Recent practice scores') }}</h3>
                @if ($recentScores->isEmpty())
                    <p class="mt-2 text-sm text-qs-muted">{{ __('No attempts yet.') }}</p>
                @else
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($recentScores as $att)
                            <li class="flex flex-wrap justify-between gap-2 border-t border-qs-soft pt-2 first:border-0 first:pt-0">
                                <span class="text-qs-text">{{ $att->practiceQuiz?->course?->code }} · {{ $att->practiceQuiz?->title }}</span>
                                <span class="text-qs-muted">{{ $att->percentage !== null ? number_format((float) $att->percentage, 1).'%' : '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
