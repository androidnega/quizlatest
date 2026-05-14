<x-layouts.coordinator>
    <x-slot name="title">{{ __('Reporting') }}</x-slot>
    <x-slot name="subtitle">{{ __('Assessment completion and course signals for your departments.') }}</x-slot>

    <div class="space-y-6">
        @if ($departmentIds === [])
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                {{ __('You have no active department assignments, so reporting is empty.') }}
            </div>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    __('Published assessments') => $snapshot['published_quizzes'] ?? 0,
                    __('Submitted sessions') => $snapshot['submitted_sessions'] ?? 0,
                    __('Pending grading') => $snapshot['pending_grading'] ?? 0,
                    __('Assignments due (7d)') => $snapshot['assignments_due_soon'] ?? 0,
                    __('Assignments overdue') => $snapshot['assignments_overdue'] ?? 0,
                    __('Missing submissions (estimate)') => $snapshot['missing_submissions'] ?? 0,
                    __('Active examiner seats') => $snapshot['examiner_active_assignments'] ?? 0,
                ] as $label => $val)
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-[11px] font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $val }}</p>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <a href="{{ route('coordinator.reporting.export.class-completion') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">{{ __('Export class completion CSV') }}</a>
                <a href="{{ route('coordinator.reporting.export.course-performance') }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800 hover:bg-slate-50">{{ __('Export course performance CSV') }}</a>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Classes') }}</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-[10px] font-bold uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pe-3">{{ __('Class') }}</th>
                                <th class="py-2 pe-3">{{ __('Program') }}</th>
                                <th class="py-2 pe-3 text-end">{{ __('Students') }}</th>
                                <th class="py-2 pe-3 text-end">{{ __('Published assessments') }}</th>
                                <th class="py-2 text-end">{{ __('Submitted sessions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($classRows as $r)
                                <tr>
                                    <td class="py-2 pe-3 font-medium text-slate-800">{{ $r['class_label'] ?? '' }}</td>
                                    <td class="py-2 pe-3 text-slate-600">{{ $r['program'] ?? '' }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $r['students'] ?? 0 }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $r['published_assessments'] ?? 0 }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $r['submitted_session_count'] ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No classes in scope.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Courses') }}</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-[10px] font-bold uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pe-3">{{ __('Code') }}</th>
                                <th class="py-2 pe-3">{{ __('Title') }}</th>
                                <th class="py-2 pe-3 text-end">{{ __('Published') }}</th>
                                <th class="py-2 pe-3 text-end">{{ __('Pending grading') }}</th>
                                <th class="py-2 pe-3 text-end">{{ __('Avg score') }}</th>
                                <th class="py-2 pe-3 text-end">{{ __('Published results') }}</th>
                                <th class="py-2 text-end">{{ __('Graded, unreleased') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($courseRows as $r)
                                <tr>
                                    <td class="py-2 pe-3 font-mono font-semibold text-slate-800">{{ $r['code'] ?? '' }}</td>
                                    <td class="py-2 pe-3 text-slate-700">{{ $r['title'] ?? '' }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $r['published_assessments'] ?? 0 }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $r['pending_grading'] ?? 0 }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $r['avg_score'] ?? '—' }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $r['results_published'] ?? 0 }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $r['graded_unpublished'] ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-6 text-center text-slate-500">{{ __('No courses in scope.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('Examiner activity') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('Pending grading counts are limited to assessments created by each examiner inside your scoped courses.') }}</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="text-[10px] font-bold uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pe-3">{{ __('Examiner') }}</th>
                                <th class="py-2 pe-3 text-end">{{ __('Courses assigned') }}</th>
                                <th class="py-2 text-end">{{ __('Pending grading') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($examinerRows as $r)
                                <tr>
                                    <td class="py-2 pe-3 font-medium text-slate-800">{{ $r['name'] ?? '' }}</td>
                                    <td class="py-2 pe-3 text-end tabular-nums">{{ $r['courses_assigned'] ?? 0 }}</td>
                                    <td class="py-2 text-end tabular-nums">{{ $r['pending_grading'] ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-slate-500">{{ __('No examiners linked to scoped courses.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-layouts.coordinator>
