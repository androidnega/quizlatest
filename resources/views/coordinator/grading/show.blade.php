<x-layouts.coordinator>
    <x-slot name="title">Grade essay</x-slot>
    <x-slot name="subtitle">{{ $answer->examSession?->exam?->title }}</x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="bg-white rounded-xl border border-qs-soft p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">Student</p>
            <p class="mt-1 font-medium text-qs-text">{{ $answer->examSession?->student?->name }}</p>
        </div>

        <div class="bg-white rounded-xl border border-qs-soft p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">Question (max {{ $answer->question->marks }} marks)</p>
            <p class="mt-2 whitespace-pre-wrap text-qs-text">{{ $answer->question->question_text }}</p>
        </div>

        <div class="bg-white rounded-xl border border-qs-soft p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">Submitted answer</p>
            <p class="mt-2 whitespace-pre-wrap text-qs-text">{{ $answer->answer_payload['text'] ?? '' }}</p>
        </div>

        <form method="post" action="{{ route('coordinator.grading.grade', $answer) }}" class="bg-white rounded-xl border border-qs-soft p-5 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-qs-muted mb-1">Points</label>
                <input type="number" name="points_awarded" step="0.01" min="0" max="{{ $answer->question->marks }}" required
                    class="w-40 rounded-lg border border-qs-soft px-3 py-2 text-sm" value="{{ old('points_awarded', 0) }}" />
                @error('points_awarded')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-qs-muted mb-1">Feedback (optional)</label>
                <textarea name="grader_feedback" rows="4" class="w-full rounded-lg border border-qs-soft px-3 py-2 text-sm">{{ old('grader_feedback') }}</textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-qs-accent px-4 py-2 text-sm font-semibold text-qs-text hover:opacity-95">Save grade</button>
                <a href="{{ route('coordinator.grading.pending') }}" class="rounded-lg border border-qs-soft px-4 py-2 text-sm font-semibold text-qs-muted hover:bg-qs-card">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.coordinator>
