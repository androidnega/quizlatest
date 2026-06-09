<x-layouts.student>
    <x-slot name="title">{{ __('Assessments') }}</x-slot>
    <x-slot name="subtitle">{{ __('What to do next — open, due, and upcoming work.') }}</x-slot>

    @php
        $sessionExam = $activeSession?->exam;
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
    @endphp

    <div class="space-y-0 pb-6">
        @if ($errors->has('exam'))
            <div class="qs-std-list-stack">
                <div class="qs-std-card border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-900">
                    {{ $errors->first('exam') }}
                </div>
            </div>
        @endif

        @if ($examSessionPaused && $sessionExam)
            <div class="qs-std-list-stack">
                <div class="qs-std-card border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                    <p class="font-semibold">{{ __('Timer paused') }}</p>
                    <p class="mt-1 text-xs leading-relaxed">{{ __('Open the assessment and tap Resume to continue.') }}</p>
                    <a href="{{ route('student.exam.take', $activeSession) }}" class="qs-std-btn qs-std-btn--primary mt-4 inline-flex">
                        {{ __('Resume') }}
                    </a>
                </div>
            </div>
        @endif

        @include('student.partials.assessment-worklist')
    </div>
</x-layouts.student>
