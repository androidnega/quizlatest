<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">{{ __('Practice results') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-qs-accent/40 bg-qs-accent/10 p-5">
                <p class="text-sm text-qs-muted">{{ __('Score') }}</p>
                <p class="mt-1 text-3xl font-semibold text-qs-text">
                    {{ $attempt->score }} / {{ $attempt->total_marks }}
                    @if ($attempt->percentage !== null)
                        <span class="text-lg text-qs-muted">({{ number_format((float) $attempt->percentage, 1) }}%)</span>
                    @endif
                </p>
            </div>

            <div class="space-y-5">
                @foreach ($attempt->answers as $ans)
                    <div class="rounded-xl border border-qs-soft bg-qs-bg p-4">
                        <p class="text-sm font-medium text-qs-text">{{ $ans->question?->question_text }}</p>
                        <p class="mt-2 text-xs text-qs-muted">
                            {{ $ans->is_correct ? __('Correct') : __('Incorrect') }}
                            — {{ __('Points') }}: {{ $ans->points_awarded }}
                        </p>
                        @if ($ans->question?->explanation)
                            <p class="mt-2 text-sm text-qs-muted">{{ $ans->question->explanation }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <a href="{{ route('student.practice.quizzes.show', $quiz) }}" class="qs-btn-secondary inline-flex text-sm">{{ __('Back to quiz') }}</a>
        </div>
    </div>
</x-app-layout>
