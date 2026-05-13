<x-layouts.student>
    <x-slot name="title">{{ __('Revision & self-check') }}</x-slot>
    <x-slot name="subtitle">{{ __('Course materials, AI summaries, and practice quizzes — when your school enables them.') }}</x-slot>

    <div class="w-full min-w-0 space-y-6 pb-2 md:space-y-8">
        @if (! $practiceEnabled)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                <p class="font-semibold">{{ __('Self-study practice is off for your school') }}</p>
                <p class="mt-2 leading-relaxed text-amber-900/90">{{ __('You can still use this page to see what would be available. For official scores and feedback, open your class results.') }}</p>
                <a href="{{ route('student.results.index') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-amber-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-900">
                    {{ __('View class results') }}
                </a>
            </div>
        @else
            <p class="text-sm text-qs-muted">{{ __('Unofficial study tools — not used for grades or official records.') }}</p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Course materials --}}
            <div id="materials" class="scroll-mt-4">
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.materials.index') }}" class="block h-full rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:border-[var(--qs-primary)]/35 hover:bg-qs-card">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Materials') }}</p>
                        <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Course outline & files') }}</p>
                        <p class="mt-2 text-xs leading-snug text-qs-muted">{{ __('Slides and documents shared for your courses.') }}</p>
                        @unless ($materialUploadsEnabled)
                            <p class="mt-2 text-xs font-medium text-amber-800">{{ __('New uploads may be limited by your school.') }}</p>
                        @endunless
                    </a>
                @else
                    <div class="h-full rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Materials') }}</p>
                        <p class="mt-2 text-sm font-medium text-slate-700">{{ __('Course outline & files') }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ __('Browse course files from your outline when practice is enabled.') }}</p>
                    </div>
                @endif
            </div>

            {{-- Generate practice quiz (AI) --}}
            <div id="quiz-generate" class="scroll-mt-4">
                @if ($practiceEnabled && $aiQuizEnabled)
                    <a href="{{ route('student.practice.quizzes.create') }}" class="block h-full rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:border-[var(--qs-primary)]/35 hover:bg-qs-card">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Quiz') }}</p>
                        <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Generate practice quiz') }}</p>
                        <p class="mt-2 text-xs leading-snug text-qs-muted">{{ __('Build a quiz from a course file using AI.') }}</p>
                    </a>
                @elseif ($practiceEnabled)
                    <div class="h-full rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Quiz') }}</p>
                        <p class="mt-2 text-sm font-medium text-slate-700">{{ __('Generate practice quiz') }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ __('AI quiz generation is turned off for your school. You can still open quizzes you already created below.') }}</p>
                    </div>
                @else
                    <div class="h-full rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Quiz') }}</p>
                        <p class="mt-2 text-sm font-medium text-slate-700">{{ __('Generate practice quiz') }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ __('Create practice quizzes from your materials when your school enables study mode.') }}</p>
                    </div>
                @endif
            </div>

            {{-- AI summaries --}}
            <div id="summaries" class="scroll-mt-4">
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.summaries.index') }}" class="block h-full rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:border-[var(--qs-primary)]/35 hover:bg-qs-card">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Summary') }}</p>
                        <p class="mt-2 text-sm font-medium text-qs-text">{{ __('AI study summary') }}</p>
                        <p class="mt-2 text-xs leading-snug text-qs-muted">{{ __('Summaries from slides and course files.') }}</p>
                        @unless ($aiSummaryEnabled)
                            <p class="mt-2 text-xs font-medium text-amber-800">{{ __('Generating new summaries is disabled; you can still view past ones.') }}</p>
                        @endunless
                    </a>
                @else
                    <div class="h-full rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Summary') }}</p>
                        <p class="mt-2 text-sm font-medium text-slate-700">{{ __('AI study summary') }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ __('Topic summaries from your course materials when enabled.') }}</p>
                    </div>
                @endif
            </div>

            {{-- My practice quizzes --}}
            <div id="my-quizzes" class="scroll-mt-4">
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.quizzes.index') }}" class="block h-full rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:border-[var(--qs-primary)]/35 hover:bg-qs-card">
                        <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Mine') }}</p>
                        <p class="mt-2 text-sm font-medium text-qs-text">{{ __('My practice quizzes') }}</p>
                        <p class="mt-2 text-xs leading-snug text-qs-muted">{{ __(':count saved in your workspace', ['count' => number_format($quizCount)]) }}</p>
                    </a>
                @else
                    <div class="h-full rounded-xl border border-slate-200 bg-slate-50/80 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Mine') }}</p>
                        <p class="mt-2 text-sm font-medium text-slate-700">{{ __('My practice quizzes') }}</p>
                        <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ __('Your personal practice attempts will appear here when study tools are on.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-qs-soft bg-qs-card p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold text-qs-text">{{ __('Recent practice scores') }}</h3>
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.quizzes.index') }}" class="text-sm font-semibold text-[var(--qs-primary)] hover:underline">{{ __('Open my quizzes') }}</a>
                @endif
            </div>
            @if (! $practiceEnabled)
                <p class="mt-2 text-sm text-qs-muted">{{ __('No practice history while study mode is off.') }}</p>
            @elseif ($recentScores->isEmpty())
                <p class="mt-2 text-sm text-qs-muted">{{ __('No attempts yet.') }}</p>
            @else
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($recentScores as $att)
                        <li class="flex flex-wrap justify-between gap-2 border-t border-qs-soft pt-2 first:border-0 first:pt-0">
                            <span class="text-qs-text">{{ $att->practiceQuiz?->course?->code }} · {{ $att->practiceQuiz?->title }}</span>
                            <span class="text-qs-muted">{{ $att->percentage !== null ? number_format((float) $att->percentage, 1).'%' : '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    </div>
</x-layouts.student>
