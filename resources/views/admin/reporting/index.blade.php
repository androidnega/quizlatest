<x-layouts.admin>
    <x-slot name="title">{{ __('System reporting') }}</x-slot>
    <x-slot name="subtitle">{{ __('High-level assessment and submission totals. Detailed grading stays with examiners unless policy expands access.') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('admin.system-reporting.export.system-summary') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">{{ __('Export system summary CSV') }}</a>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                __('Assessments') => $snapshot['assessments_total'] ?? 0,
                __('Submitted sessions') => $snapshot['submissions_total'] ?? 0,
                __('Students') => $snapshot['students_total'] ?? 0,
                __('Examiners') => $snapshot['examiners_total'] ?? 0,
                __('Coordinators') => $snapshot['coordinators_total'] ?? 0,
                __('Universities') => $snapshot['universities'] ?? 0,
                __('Departments') => $snapshot['departments'] ?? 0,
                __('Active classes') => $snapshot['classes_active'] ?? 0,
                __('Flagged sessions') => $snapshot['flagged_sessions'] ?? 0,
                __('Graded results') => $snapshot['results_graded'] ?? 0,
                __('Published results') => $snapshot['results_published'] ?? 0,
                __('Pending manual grading') => $snapshot['pending_manual'] ?? 0,
            ] as $label => $val)
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-[11px] font-medium text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $val }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Assessments by type') }}</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse ($snapshot['by_assessment_type'] ?? [] as $type => $count)
                        <li class="flex justify-between py-1.5"><span class="text-slate-700">{{ $type }}</span><span class="font-semibold tabular-nums">{{ $count }}</span></li>
                    @empty
                        <li class="py-4 text-slate-500">{{ __('No data.') }}</li>
                    @endforelse
                </ul>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Submissions by type') }}</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse ($snapshot['submissions_by_type'] ?? [] as $type => $count)
                        <li class="flex justify-between py-1.5"><span class="text-slate-700">{{ $type }}</span><span class="font-semibold tabular-nums">{{ $count }}</span></li>
                    @empty
                        <li class="py-4 text-slate-500">{{ __('No data.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.admin>
