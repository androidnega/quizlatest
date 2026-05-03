<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">{{ __('Summary') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <p class="text-xs text-qs-muted">{{ $summary->course?->code }} · {{ $summary->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
            <article class="mt-6 whitespace-pre-wrap text-sm leading-relaxed text-qs-text">{{ $summary->body }}</article>
            <a href="{{ route('student.practice.summaries.index') }}" class="qs-btn-secondary mt-8 inline-flex text-sm">{{ __('Back') }}</a>
        </div>
    </div>
</x-app-layout>
