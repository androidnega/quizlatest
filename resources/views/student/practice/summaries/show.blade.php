<x-layouts.student>
    <x-slot name="title">{{ __('Summary') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6 py-2">
            <p class="text-xs text-qs-muted">{{ $summary->course?->code }} · {{ $summary->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
            <article class="mt-6 whitespace-pre-wrap text-sm leading-relaxed text-qs-text">{{ $summary->body }}</article>
            <a href="{{ route('student.practice.summaries.index') }}" class="qs-btn-secondary mt-8 inline-flex text-sm">{{ __('Back') }}</a>
    </div>
</x-layouts.student>
