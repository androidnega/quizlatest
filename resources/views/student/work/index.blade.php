<x-layouts.student>
    <x-slot name="title">{{ __('Your work') }}</x-slot>
    <x-slot name="subtitle">{{ __('Open, due, submitted, and released — grouped by what to do next.') }}</x-slot>

    @php
        $sessionExam = $activeSession?->exam;
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
    @endphp

    <div class="w-full min-w-0 space-y-4 pb-8 text-slate-950">
        @if ($errors->has('exam'))
            <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm text-rose-900">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600" aria-hidden="true">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </span>
                <span class="min-w-0 pt-0.5">{{ $errors->first('exam') }}</span>
            </div>
        @endif

        @if ($examSessionPaused && $sessionExam)
            <div class="rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-amber-950">
                <div class="flex gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700" aria-hidden="true">
                        <i class="fa-solid fa-pause"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ __('Timer paused') }}</p>
                        <p class="mt-1 text-xs text-amber-900/90">{{ __('Open the assessment and tap Resume to continue.') }}</p>
                        <a href="{{ route('student.exam.take', $activeSession) }}" class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-amber-800 px-4 text-xs font-semibold text-white hover:bg-amber-900 sm:w-auto">
                            {{ __('Resume') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif

        @include('student.partials.assessment-worklist')
    </div>
</x-layouts.student>
