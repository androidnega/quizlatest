<x-layouts.examiner>
    <x-slot name="title">{{ __('Courses') }}</x-slot>
    <x-slot name="subtitle">{{ __('Everything you teach in one place.') }}</x-slot>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('examiner.dashboard') }}" class="text-sm font-medium text-[var(--qs-primary)] underline-offset-2 hover:underline">
            ← {{ __('Back to overview') }}
        </a>
    </div>

    @include('examiner.partials.assigned-courses-workspace', [
        'assignedCourses' => $assignedCourses,
        'materialUploadsEnabled' => $materialUploadsEnabled,
        'heading' => __('Your courses'),
    ])
</x-layouts.examiner>
