<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">
            {{ __('My results') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="qs-surface overflow-hidden rounded-lg shadow-sm">
                <ul class="divide-y divide-qs-soft">
                    @forelse ($sessions as $s)
                        @php
                            $r = $s->result;
                            $label = match ($r?->status) {
                                'held' => __('Under review'),
                                'pending_manual' => __('Pending grading'),
                                'graded' => __('Graded'),
                                default => __('Processing'),
                            };
                        @endphp
                        <li>
                            <a href="{{ route('student.results.show', $s) }}" class="block px-5 py-4 transition hover:bg-qs-card">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span class="font-medium text-qs-text">{{ $s->exam?->title ?? __('Exam') }}</span>
                                    <span class="text-xs font-medium uppercase tracking-wide text-qs-soft">{{ $label }}</span>
                                </div>
                                @if ($r && $r->status === 'graded')
                                    <p class="mt-1 text-sm text-qs-soft">
                                        {{ __('Score') }}: {{ $r->score }} / {{ $s->exam?->total_marks ?? '—' }}
                                    </p>
                                @endif
                                <p class="mt-1 text-xs text-qs-soft">{{ $s->end_time?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-sm text-qs-soft">{{ __('No submitted exams yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
