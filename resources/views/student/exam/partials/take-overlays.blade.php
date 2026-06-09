<div
    id="proctoring-review-overlay"
    class="hidden fixed inset-0 z-[71] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="proctoring-review-overlay-title"
    aria-describedby="proctoring-review-overlay-desc"
>
    <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white px-6 py-7 shadow-2xl">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-700" aria-hidden="true">
            <i class="fa-solid fa-display text-2xl"></i>
        </div>
        <h2 id="proctoring-review-overlay-title" class="mt-4 text-lg font-bold tracking-tight text-slate-900">
            {{ __('Screen setup needs attention') }}
        </h2>
        <p id="proctoring-review-overlay-desc" class="mt-2 text-sm leading-relaxed text-slate-600">
            {{ __('Your school’s integrity rules require a single primary display during this session. Disconnect or mirror extra monitors, close extended desktop, then confirm when you are ready to continue.') }}
        </p>
        <button
            type="button"
            id="btn-proctoring-overlay-continue"
            class="mt-6 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <i class="fa-solid fa-check" aria-hidden="true"></i>
            {{ __('I have fixed this — continue') }}
        </button>
    </div>
</div>

@if (! ($assignmentTake ?? false))
    <div
        id="exam-tab-switch-modal"
        class="hidden fixed inset-0 z-[68] flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-[2px]"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="exam-tab-switch-title"
        aria-describedby="exam-tab-switch-desc"
    >
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white px-6 py-7 text-center shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-red-600" aria-hidden="true">
                <i class="fa-solid fa-triangle-exclamation text-2xl"></i>
            </div>
            <h2 id="exam-tab-switch-title" class="mt-4 text-lg font-bold tracking-tight text-slate-900">
                {{ __('Tab switch detected') }}
            </h2>
            <p id="exam-tab-switch-desc" class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ __('Leaving the exam tab or window is not allowed during the assessment. Repeated violations may result in automatic submission.') }}
            </p>
            <div id="exam-tab-switch-dots" class="mt-5 flex justify-center gap-2" aria-hidden="true"></div>
            <p id="exam-tab-switch-level" class="mt-2 text-sm font-semibold text-red-600"></p>
            <button
                type="button"
                id="btn-tab-switch-dismiss"
                class="mt-6 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-700"
            >
                <i class="fa-solid fa-expand" aria-hidden="true"></i>
                {{ __('Return to exam') }}
            </button>
        </div>
    </div>
@endif

<div id="exam-timer-pause-overlay" class="hidden fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/75 px-4" role="dialog" aria-modal="true" aria-labelledby="exam-pause-title">
    <div class="max-w-md rounded-2xl border border-amber-200/80 bg-amber-50 px-5 py-6 text-center shadow-xl">
        @if ($assignmentTake ?? false)
            <p id="exam-pause-title" class="text-lg font-semibold text-amber-950">{{ __('Session paused') }}</p>
            <p id="exam-pause-body" class="mt-2 text-sm leading-relaxed text-amber-900/90">
                {{ __('Your connection was interrupted. Press Resume when you are ready to continue your assignment.') }}
            </p>
        @else
            <p id="exam-pause-title" class="text-lg font-semibold text-amber-950">{{ __('Exam paused') }}</p>
            <p id="exam-pause-body" class="mt-2 text-sm leading-relaxed text-amber-900/90">
                {{ __('Your timer is frozen. When you are ready, resume to continue from where you left off.') }}
            </p>
        @endif
        <button type="button" id="btn-exam-resume" class="mt-5 inline-flex min-h-[44px] w-full items-center justify-center rounded-xl bg-amber-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-900">
            {{ __('Resume') }}
        </button>
    </div>
</div>

<div id="essay-clipboard-toast" role="status" aria-live="polite"
    class="pointer-events-none fixed bottom-6 left-1/2 z-50 hidden max-w-sm -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-center text-sm text-slate-800 shadow-lg">
</div>
