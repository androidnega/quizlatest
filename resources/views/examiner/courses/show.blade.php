<x-layouts.examiner>
    <x-slot name="title">{{ $course->code }} — {{ $course->title }}</x-slot>
    <x-slot name="subtitle">{{ __('Course details') }}</x-slot>

    @php
        $materialsOn = app(\App\Services\PracticeModuleSettings::class)->courseMaterialUploadsEnabled();
    @endphp

    <p class="mb-6">
        <a href="{{ route('examiner.courses.index') }}" class="text-sm font-medium text-[var(--qs-primary)] underline-offset-2 hover:underline">
            ← {{ __('All courses') }}
        </a>
    </p>

    <header class="rounded-2xl border border-qs-soft bg-qs-card p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-qs-text sm:text-3xl">{{ $course->title }}</h1>
        <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-qs-muted">
            <span class="font-mono font-semibold text-qs-text">{{ $course->code }}</span>
            @if ($course->relationLoaded('department') && $course->department)
                <span aria-hidden="true">·</span>
                <span>{{ $course->department->name }}</span>
            @endif
        </p>

        <div class="mt-6 flex flex-wrap gap-3 border-t border-qs-soft pt-6">
            <a href="{{ route('examiner.exams.index', ['course_id' => $course->id]) }}" class="qs-btn-secondary inline-flex min-h-[40px] items-center gap-2 px-4 text-sm font-semibold">
                <i class="fa-solid fa-file-lines text-sm" aria-hidden="true"></i>
                {{ __('Assessments for this course') }}
            </a>
            <a href="{{ route('examiner.teaching-classes.index') }}" class="qs-btn-secondary inline-flex min-h-[40px] items-center gap-2 px-4 text-sm font-semibold">
                <i class="fa-solid fa-user-group text-sm" aria-hidden="true"></i>
                {{ __('Class groups') }}
            </a>
            @if ($materialsOn)
                <a href="{{ route('examiner.courses.outline', $course) }}" class="qs-btn-primary inline-flex min-h-[40px] items-center gap-2 px-4 text-sm font-semibold">
                    <i class="fa-solid fa-file-arrow-up text-sm" aria-hidden="true"></i>
                    {{ __('Upload outline') }}
                </a>
                <a href="{{ route('examiner.courses.materials.index', $course) }}" class="qs-btn-secondary inline-flex min-h-[40px] items-center gap-2 px-4 text-sm font-semibold">
                    <i class="fa-solid fa-folder-open text-sm" aria-hidden="true"></i>
                    {{ __('Course files') }}
                </a>
            @endif
        </div>
    </header>
</x-layouts.examiner>
