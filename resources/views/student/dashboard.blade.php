<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl qs-heading leading-tight">
            {{ __('Student dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if ($errors->has('exam'))
                <div class="rounded-xl border border-qs-danger/35 bg-qs-danger-soft px-4 py-3 text-sm text-qs-danger">
                    {{ $errors->first('exam') }}
                </div>
            @endif

            @if ($practiceEnabled ?? false)
                <div class="rounded-xl border border-qs-accent/30 bg-qs-accent/10 px-5 py-5 shadow-sm">
                    <p class="text-sm font-semibold text-qs-text">{{ __('Practice (unofficial)') }}</p>
                    <p class="mt-1 text-xs text-qs-muted">{{ __('Materials, AI summaries, and self-tests — separate from official exams.') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('student.practice.index') }}" class="qs-btn-primary text-sm">{{ __('Practice hub') }}</a>
                        <span class="self-center text-xs text-qs-muted">{{ __('Saved quizzes') }}: {{ $practiceQuizCount }}</span>
                    </div>
                    @if (($recentPracticeScores ?? collect())->isNotEmpty())
                        <ul class="mt-4 space-y-1 text-xs text-qs-muted">
                            @foreach ($recentPracticeScores as $att)
                                <li>{{ $att->practiceQuiz?->course?->code }} — {{ $att->percentage !== null ? number_format((float) $att->percentage, 1).'%' : '—' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Program') }}</p>
                    <p class="mt-2 text-lg font-semibold text-qs-text">{{ $user->program?->name ?? '—' }}</p>
                    <p class="mt-1 text-sm text-qs-muted">{{ __('Level') }}: {{ $user->level?->name ?? '—' }}</p>
                </div>
                <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Face profile') }}</p>
                    @if ($faceProfileReady)
                        <p class="mt-2 text-sm font-medium text-qs-text">{{ __('Enrolled — ready for verification') }}</p>
                    @else
                        <p class="mt-2 text-sm font-medium text-qs-danger">{{ __('Not enrolled') }}</p>
                        <p class="mt-1 text-xs text-qs-muted">{{ __('Complete portrait enrollment on your profile or registration before high-stakes exams.') }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Graded results') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-qs-text">{{ $gradedResultsCount }}</p>
                    <a href="{{ route('student.results.index') }}" class="mt-2 inline-block text-sm font-medium text-qs-text underline-offset-2 hover:underline">
                        {{ __('View all results') }}
                    </a>
                </div>
            </div>

            @if ($heldResults->isNotEmpty())
                <div class="rounded-xl border border-qs-accent/40 bg-qs-accent/10 px-5 py-4 text-sm text-qs-text">
                    <p class="font-semibold">{{ __('Results under review') }}</p>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-qs-muted">
                        @foreach ($heldResults as $row)
                            <li>{{ $row->quiz?->title ?? __('Exam') }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-qs-muted">{{ __('Your institution is reviewing these outcomes. You will be notified when a decision is recorded.') }}</p>
                </div>
            @endif

            @if ($pendingManualResults->isNotEmpty())
                <div class="rounded-xl border border-qs-soft bg-qs-card px-5 py-4 text-sm text-qs-text">
                    <p class="font-semibold">{{ __('Awaiting grading') }}</p>
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-qs-muted">
                        @foreach ($pendingManualResults as $row)
                            <li>{{ $row->quiz?->title ?? __('Exam') }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($activeSession)
                <div class="rounded-xl border border-qs-accent bg-qs-bg p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-qs-muted">{{ __('Exam in progress') }}</p>
                            <p class="mt-2 text-lg font-semibold text-qs-text">{{ $activeSession->exam?->title }}</p>
                            <p class="mt-1 text-sm text-qs-muted">{{ $activeSession->exam?->course?->code }} — {{ $activeSession->exam?->course?->title }}</p>
                        </div>
                        <a href="{{ route('student.exam.take', $activeSession) }}" class="qs-btn-primary shrink-0">
                            {{ __('Continue exam') }}
                        </a>
                    </div>
                </div>
            @endif

            <div>
                <h3 class="text-lg font-semibold text-qs-text">{{ __('Available exams') }}</h3>
                @if (! $hasClass)
                    <p class="mt-2 text-sm text-qs-muted">{{ __('student_ui.class_group_not_assigned') }}</p>
                @elseif ($availableExams->isEmpty() && ! $activeSession)
                    <p class="mt-2 text-sm text-qs-muted">{{ __('No exams are open for you right now.') }}</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($availableExams as $exam)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-qs-soft bg-qs-bg px-4 py-4 shadow-sm">
                                <div>
                                    <p class="font-semibold text-qs-text">{{ $exam->title }}</p>
                                    <p class="text-sm text-qs-muted">{{ $exam->course?->code }} · {{ $exam->duration_minutes }} {{ __('min') }}</p>
                                </div>
                                <a href="{{ route('student.exam.prepare', $exam) }}" class="qs-btn-primary text-sm">
                                    {{ __('Start exam') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <h3 class="text-lg font-semibold text-qs-text">{{ __('Upcoming exams') }}</h3>
                @if ($upcomingExams->isEmpty())
                    <p class="mt-2 text-sm text-qs-muted">{{ __('No upcoming windows scheduled.') }}</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($upcomingExams as $exam)
                            <li class="rounded-xl border border-qs-soft bg-qs-card px-4 py-4">
                                <p class="font-semibold text-qs-text">{{ $exam->title }}</p>
                                <p class="text-sm text-qs-muted">{{ $exam->course?->code }}</p>
                                @if ($exam->start_time)
                                    <p class="mt-2 text-xs text-qs-muted">{{ __('Opens') }}: {{ $exam->start_time->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
