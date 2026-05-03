<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">{{ __('Practice quiz') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-qs-soft bg-qs-card px-4 py-3 text-sm text-qs-text">{{ session('status') }}</div>
            @endif

            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5">
                <p class="text-sm font-medium text-qs-text">{{ $quiz->title }}</p>
                <p class="mt-1 text-xs text-qs-muted">{{ $quiz->course?->code }} · {{ $quiz->status }}</p>
                @if ($quiz->generation_error)
                    <p class="mt-3 text-sm text-qs-danger">{{ $quiz->generation_error }}</p>
                @endif
            </div>

            @if ($quiz->status === \App\Models\PracticeQuiz::STATUS_READY)
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('student.practice.quizzes.take', $quiz) }}" class="qs-btn-primary text-sm">{{ __('Take quiz') }}</a>
                </div>
            @endif

            @if ($quiz->attempts->isNotEmpty())
                <div class="rounded-xl border border-qs-soft bg-qs-card p-5">
                    <h3 class="text-sm font-semibold text-qs-text">{{ __('Recent attempts') }}</h3>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($quiz->attempts as $a)
                            <li class="flex justify-between gap-2">
                                <span class="text-qs-muted">{{ $a->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                                <a href="{{ route('student.practice.quizzes.result', [$quiz, $a]) }}" class="font-medium text-qs-text underline-offset-2 hover:underline">
                                    {{ $a->percentage !== null ? number_format((float) $a->percentage, 1).'%' : '—' }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('student.practice.quizzes.destroy', $quiz) }}" onsubmit="return confirm(@json(__('Delete this practice quiz?')));">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-qs-danger">{{ __('Delete quiz') }}</button>
            </form>
        </div>
    </div>
</x-app-layout>
