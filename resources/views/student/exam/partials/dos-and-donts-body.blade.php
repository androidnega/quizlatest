@if ($isAssignment ?? false)
    <header class="border-b border-qs-soft pb-5 text-center">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-qs-primary/15 text-qs-primary" aria-hidden="true">
            <i class="fa-solid fa-file-lines text-xl"></i>
        </span>
        <h1 class="mt-4 text-xl font-bold tracking-tight text-qs-text">{{ __('Assignment dos and don’ts') }}</h1>
    </header>
    <ul class="mt-6 space-y-3 text-sm leading-relaxed text-qs-text">
        <li class="flex gap-3">
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700" aria-hidden="true">
                <i class="fa-solid fa-check text-xs"></i>
            </span>
            <span>{{ __('Complete this assignment yourself, using only sources and collaboration rules your course allows.') }}</span>
        </li>
        <li class="flex gap-3">
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700" aria-hidden="true">
                <i class="fa-solid fa-check text-xs"></i>
            </span>
            <span>{{ __('Typed responses must be entered in this page. Copy and paste is blocked in answer fields to support academic integrity.') }}</span>
        </li>
        <li class="flex gap-3">
            <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700" aria-hidden="true">
                <i class="fa-solid fa-check text-xs"></i>
            </span>
            <span>{{ __('This coursework does not use live camera or audio invigilation unless your school explicitly enables an exception.') }}</span>
        </li>
    </ul>
@else
    <header class="border-b border-qs-soft pb-5 text-center">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-qs-primary/15 text-qs-primary" aria-hidden="true">
            <i class="fa-solid fa-clipboard-list text-xl"></i>
        </span>
        <h1 class="mt-4 text-xl font-bold tracking-tight text-qs-text">{{ __('Exam dos and don’ts') }}</h1>
    </header>

    <div class="mt-6 space-y-5">
        <div>
            <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-emerald-800">
                <i class="fa-solid fa-circle-check text-emerald-600" aria-hidden="true"></i>
                {{ __('Do') }}
            </h2>
            <ul class="mt-3 space-y-2.5 text-sm leading-relaxed text-qs-text">
                <li class="flex gap-3 pl-0.5">
                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    <span>{{ __('Complete this exam honestly, on your own, without unauthorised help or materials, unless your institution explicitly allows otherwise.') }}</span>
                </li>
                <li class="flex gap-3 pl-0.5">
                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    <span>{{ __('Follow invigilator or institution instructions (for example fullscreen, staying visible on camera, and not switching away from the exam without permission).') }}</span>
                </li>
            </ul>
        </div>

        <div>
            <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-rose-800">
                <i class="fa-solid fa-circle-xmark text-rose-600" aria-hidden="true"></i>
                {{ __('Don’t') }}
            </h2>
            <ul class="mt-3 space-y-2.5 text-sm leading-relaxed text-qs-text">
                <li class="flex gap-3 pl-0.5">
                    <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500" aria-hidden="true"></span>
                    <span>{{ __('Do not copy, share, or capture exam content. Suspicious behaviour may be logged for review.') }}</span>
                </li>
            </ul>
        </div>

        <div class="rounded-xl border border-amber-200/90 bg-gradient-to-b from-amber-50 to-amber-50/80 px-4 py-4 text-amber-950">
            <p class="flex items-center justify-center gap-2 text-center text-sm font-bold">
                <i class="fa-solid fa-bolt text-amber-600" aria-hidden="true"></i>
                {{ __('When your work may be submitted automatically') }}
            </p>
            <ul class="mt-4 space-y-3 text-sm leading-snug">
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-200/80 text-amber-900" aria-hidden="true">
                        <i class="fa-solid fa-gauge-high text-xs"></i>
                    </span>
                    <span>{{ __('Proctoring signals can raise a violation score; policy may auto-submit after warnings.') }}</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-200/80 text-amber-900" aria-hidden="true">
                        <i class="fa-solid fa-user-shield text-xs"></i>
                    </span>
                    <span>{{ __('Staff may end or submit a session under your institution’s rules (e.g. emergency or misconduct).') }}</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-200/80 text-amber-900" aria-hidden="true">
                        <i class="fa-solid fa-clock text-xs"></i>
                    </span>
                    <span>{{ __('Timer and submission windows still apply — submit before time runs out when allowed.') }}</span>
                </li>
            </ul>
        </div>
    </div>
@endif
