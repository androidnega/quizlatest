<x-layouts.coordinator>
    <x-slot name="title">Coordinator Dashboard</x-slot>
    <x-slot name="subtitle">Department-scoped academic overview</x-slot>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:shadow-md">
            <p class="text-sm font-medium text-qs-muted">{{ __('Total students') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $studentCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:shadow-md">
            <p class="text-sm font-medium text-qs-muted">{{ __('Students without class') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $studentsWithoutClass }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:shadow-md">
            <p class="text-sm font-medium text-qs-muted">{{ __('Active classes') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $classCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:shadow-md">
            <p class="text-sm font-medium text-qs-muted">{{ __('Programs (active / total)') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $activeProgramCount }} <span class="text-lg text-qs-muted">/ {{ $programTotal }}</span></p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:shadow-md">
            <p class="text-sm font-medium text-qs-muted">{{ __('Courses in scope') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $courseCount }}</p>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 shadow-sm transition hover:shadow-md">
            <p class="text-sm font-medium text-qs-muted">{{ __('Your examiner course assignments') }}</p>
            <p class="mt-4 text-3xl font-semibold text-qs-text">{{ $assignedCourseCount }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-qs-text">{{ __('Quick actions') }}</h3>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('coordinator.students.index') }}" class="qs-btn-primary text-sm">{{ __('Students') }}</a>
                <a href="{{ route('coordinator.students.upload') }}" class="qs-btn-secondary text-sm">{{ __('CSV upload') }}</a>
                <a href="{{ route('coordinator.classes.index') }}" class="qs-btn-secondary text-sm">{{ __('Classes') }}</a>
                <a href="{{ route('coordinator.courses.index') }}" class="qs-btn-secondary text-sm">{{ __('Courses') }}</a>
                @if (\App\Http\Middleware\EnsureUserIsExaminer::mayAccessExaminerPortal(auth()->user()))
                    <a href="{{ route('examiner.dashboard') }}" class="qs-btn-secondary text-sm">{{ __('Examiner portal') }}</a>
                @endif
                <a href="{{ route('coordinator.academic-reset.index') }}" class="qs-btn-secondary text-sm">{{ __('Academic reset') }}</a>
            </div>
        </div>
        <div class="rounded-xl border border-qs-soft bg-qs-card p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-qs-text">{{ __('Recently added students') }}</h3>
            <p class="mt-1 text-xs text-qs-muted">{{ __('Latest records in your departments (import or manual).') }}</p>
            @if ($recentStudents->isEmpty())
                <p class="mt-4 text-sm text-qs-muted">{{ __('No recent student records.') }}</p>
            @else
                <ul class="mt-4 divide-y divide-qs-soft text-sm">
                    @foreach ($recentStudents as $s)
                        <li class="flex justify-between gap-3 py-2">
                            <span class="text-qs-text">{{ $s->name }}</span>
                            <span class="shrink-0 text-qs-muted">{{ $s->created_at?->timezone(config('app.timezone'))->format('M j, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layouts.coordinator>
