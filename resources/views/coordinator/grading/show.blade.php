<x-layouts.coordinator>
    <x-slot name="title">Grade essay</x-slot>
    <x-slot name="subtitle">{{ $answer->examSession?->exam?->title }}</x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">{{ __('Student') }}</p>
            <p class="mt-1 font-medium text-qs-text">{{ $answer->examSession?->student?->name }}</p>
        </div>

        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">Question (max {{ $answer->question->marks }} marks)</p>
            <p class="mt-2 whitespace-pre-wrap text-qs-text">{{ $answer->question->question_text }}</p>
        </div>

        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">{{ __('Submitted answer') }}</p>
            <p class="mt-2 whitespace-pre-wrap text-qs-text">{{ $answer->answer_payload['text'] ?? '' }}</p>
        </div>

        <form method="post" action="{{ route('coordinator.grading.grade', $answer) }}" class="space-y-4 rounded-xl border border-qs-soft bg-qs-bg p-5">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Points') }}</label>
                <input type="number" name="points_awarded" step="0.01" min="0" max="{{ $answer->question->marks }}" required
                    class="qs-input mt-0 w-full max-w-xs py-2.5 sm:w-40" value="{{ old('points_awarded', 0) }}" />
                @error('points_awarded')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Feedback (optional)') }}</label>
                <textarea name="grader_feedback" rows="4" class="qs-input mt-0 py-2.5">{{ old('grader_feedback') }}</textarea>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Save grade') }}</button>
                <a href="{{ route('coordinator.grading.pending') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
