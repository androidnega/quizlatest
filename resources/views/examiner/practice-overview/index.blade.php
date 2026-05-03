<x-layouts.coordinator>
    <x-slot name="title">{{ __('Practice overview') }}</x-slot>
    <x-slot name="subtitle">{{ __('Aggregated practice activity for your courses — no individual answer review.') }}</x-slot>

    <form method="GET" action="{{ route('examiner.practice-overview.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-qs-muted">{{ __('Course filter') }}</label>
            <select name="course_id" class="qs-input mt-1 min-h-[44px] py-2" onchange="this.form.submit()">
                <option value="0">{{ __('All assigned courses') }}</option>
                @foreach ($courses as $c)
                    <option value="{{ $c->id }}" @selected((int) ($selectedCourseId ?? 0) === (int) $c->id)>{{ $c->code }} — {{ $c->title }}</option>
                @endforeach
            </select>
        </div>
    </form>

    @if ($stats)
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5">
                <p class="text-xs font-medium text-qs-muted">{{ __('Students using practice') }}</p>
                <p class="mt-2 text-2xl font-semibold text-qs-text">{{ $stats['students'] }}</p>
            </div>
            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5">
                <p class="text-xs font-medium text-qs-muted">{{ __('AI quizzes generated') }}</p>
                <p class="mt-2 text-2xl font-semibold text-qs-text">{{ $stats['quizzes_generated'] }}</p>
            </div>
            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5">
                <p class="text-xs font-medium text-qs-muted">{{ __('Avg practice score %') }}</p>
                <p class="mt-2 text-2xl font-semibold text-qs-text">{{ $stats['avg_percentage'] ?? '—' }}</p>
            </div>
        </div>
    @endif

    <div class="mt-8 rounded-xl border border-qs-soft bg-qs-card p-5">
        <h3 class="text-sm font-semibold text-qs-text">{{ __('Most used materials (quiz generations)') }}</h3>
        @if ($topMaterials->isEmpty())
            <p class="mt-2 text-sm text-qs-muted">{{ __('No data yet.') }}</p>
        @else
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($topMaterials as $row)
                    <li class="flex justify-between gap-2 border-t border-qs-soft pt-2 first:border-0 first:pt-0">
                        <span class="text-qs-text">{{ $row['title'] }}</span>
                        <span class="text-qs-muted">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.coordinator>
