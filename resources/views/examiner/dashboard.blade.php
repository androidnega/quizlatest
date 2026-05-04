<x-layouts.examiner>
    <x-slot name="title">Examiner Dashboard</x-slot>
    <x-slot name="subtitle">Scoped to courses you manage or are assigned to examine</x-slot>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
            <p class="text-sm font-medium text-qs-muted">{{ __('Courses in scope') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $manageableCourseCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
            <p class="text-sm font-medium text-qs-muted">{{ __('Draft exams') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $draftExamCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
            <p class="text-sm font-medium text-qs-muted">{{ __('Published exams') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $publishedExamCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm">
            <p class="text-sm font-medium text-qs-muted">{{ __('Held results') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $heldResultsCount }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-qs-text">{{ __('Pending manual grading') }}</h3>
            <p class="mt-2 text-4xl font-semibold text-qs-text">{{ $pendingManualGradingCount }}</p>
            <a href="{{ route('examiner.grading.pending') }}" class="qs-btn-primary mt-4 inline-flex text-sm">{{ __('Open grading queue') }}</a>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-card p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-qs-text">{{ __('Quick actions') }}</h3>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('examiner.exams.index') }}" class="qs-btn-primary text-sm">{{ __('My exams') }}</a>
                <a href="{{ route('examiner.exams.create') }}" class="qs-btn-secondary text-sm">{{ __('Create exam') }}</a>
                <a href="{{ route('coordinator.dashboard') }}" class="qs-btn-secondary text-sm">{{ __('Coordinator home') }}</a>
                @if (! empty($practiceOverviewEnabled))
                    <a href="{{ route('examiner.practice-overview.index') }}" class="qs-btn-secondary text-sm">{{ __('Practice overview') }}</a>
                @endif
                @if (! empty($materialUploadsEnabled) && $firstManageableCourse)
                    <a href="{{ route('examiner.courses.materials.index', $firstManageableCourse) }}" class="qs-btn-secondary text-sm">{{ __('Course materials') }}</a>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-qs-text">{{ __('Assigned examiner courses') }}</h3>
        @if ($assignedCourses->isEmpty())
            <p class="mt-2 text-sm text-qs-muted">{{ __('No explicit examiner assignments — you still see courses in your coordinator departments.') }}</p>
        @else
            <ul class="mt-4 divide-y divide-qs-soft text-sm">
                @foreach ($assignedCourses as $course)
                    <li class="py-2 text-qs-text">{{ $course->code }} — {{ $course->title }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-8 rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-qs-text">{{ __('Recent flagged sessions') }}</h3>
        @if ($flaggedSessions->isEmpty())
            <p class="mt-2 text-sm text-qs-muted">{{ __('No flagged sessions in your course scope.') }}</p>
        @else
            <div class="qs-table-wrap mt-4 border border-qs-soft">
                <table class="qs-table">
                    <thead>
                        <tr>
                            <th>{{ __('Student') }}</th>
                            <th>{{ __('Exam') }}</th>
                            <th>{{ __('Course') }}</th>
                            <th class="text-right">{{ __('Review') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flaggedSessions as $session)
                            <tr>
                                <td class="text-sm text-qs-text">{{ $session->student?->name ?? '—' }}</td>
                                <td class="text-sm text-qs-text">{{ $session->exam?->title ?? '—' }}</td>
                                <td class="text-sm text-qs-muted">{{ $session->exam?->course?->code ?? '—' }}</td>
                                <td class="text-right">
                                    @if ($session->exam)
                                        <a href="{{ route('examiner.exams.sessions.index', $session->exam) }}" class="text-sm font-medium text-qs-text underline-offset-2 hover:underline">
                                            {{ __('Sessions') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.examiner>
