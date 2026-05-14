<x-layouts.examiner>
    <x-slot name="title">{{ __('Exams') }}</x-slot>
    <x-slot name="subtitle">
        <span class="text-[11px] font-bold uppercase tracking-widest text-slate-500">{{ __('Create and manage assessments') }}</span>
    </x-slot>

    @php
        $filterCourseId = $filterCourseId ?? null;
        $filterCourse = $filterCourse ?? null;
        $listBaseQuery = array_filter([
            'academic_year_id' => $selectedAcademicYearId,
            'course_id' => $filterCourseId,
            'proctoring_focus' => $proctoringFocus ?? null,
        ]);
        $activeTabUrl = route('examiner.exams.index', array_merge($listBaseQuery, ['tab' => 'active']));
        $endedTabUrl = route('examiner.exams.index', array_merge($listBaseQuery, ['tab' => 'ended']));
        $clearParams = array_filter([
            'academic_year_id' => $selectedAcademicYearId,
            'proctoring_focus' => $proctoringFocus ?? null,
        ]);
        if (($examsTab ?? 'active') === 'ended') {
            $clearParams['tab'] = 'ended';
        }
        $clearCourseFilterUrl = route('examiner.exams.index', $clearParams);
        $clearProctoringFilterUrl = route('examiner.exams.index', array_filter([
            'academic_year_id' => $selectedAcademicYearId,
            'course_id' => $filterCourseId,
            'tab' => ($examsTab ?? 'active') === 'ended' ? 'ended' : null,
        ]));
        $isActiveTab = ($examsTab ?? 'active') === 'active';
        $pf = $proctoringFocus ?? null;
        $sessionsTabQuery = ['tab' => 'sessions'];
        if (is_string($pf)) {
            if (in_array($pf, ['flagged', 'auto_submitted', 'phone_detected', 'tab_switch_limit'], true)) {
                $sessionsTabQuery['integrity'] = $pf;
            } elseif ($pf === 'held_results') {
                $sessionsTabQuery['status'] = 'held';
            } elseif ($pf === 'assignments_grading') {
                $sessionsTabQuery['status'] = 'pending_manual';
            }
        }
        $levelBadgePalette = [
            'bg-sky-100 text-sky-900 ring-sky-200/80',
            'bg-emerald-100 text-emerald-900 ring-emerald-200/80',
            'bg-violet-100 text-violet-900 ring-violet-200/80',
            'bg-amber-100 text-amber-950 ring-amber-200/80',
            'bg-rose-100 text-rose-900 ring-rose-200/80',
        ];
    @endphp

    <x-slot name="headingActions">
        <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
            @if ($academicYears->isNotEmpty())
                <form method="get" action="{{ route('examiner.exams.index') }}" class="flex items-center gap-2">
                    @if (($examsTab ?? 'active') === 'ended')
                        <input type="hidden" name="tab" value="ended" />
                    @endif
                    @if (! empty($filterCourseId))
                        <input type="hidden" name="course_id" value="{{ $filterCourseId }}" />
                    @endif
                    @if (! empty($pf))
                        <input type="hidden" name="proctoring_focus" value="{{ $pf }}" />
                    @endif
                    <label for="exam-index-year" class="sr-only">{{ __('Academic year') }}</label>
                    <select
                        id="exam-index-year"
                        name="academic_year_id"
                        class="max-w-[15rem] rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm ring-1 ring-black/[0.04] transition hover:border-slate-300 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500/25"
                        onchange="this.form.submit()"
                    >
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected((int) $selectedAcademicYearId === (int) $year->id)>
                                {{ $year->name }}{{ $year->is_active ? ' · '.__('active') : '' }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a
                href="{{ route('examiner.exams.create', array_filter(['course_id' => $filterCourseId])) }}"
                class="inline-flex min-h-[40px] items-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-sky-600/20 transition hover:bg-sky-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2"
            >
                <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                {{ __('Create exam') }}
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($filterCourse)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200/80 bg-sky-50/90 px-4 py-3 text-sm sm:px-5">
                <p class="min-w-0 text-slate-800">
                    <span class="font-semibold text-slate-900">{{ __('Course filter') }}:</span>
                    <span class="ms-1 font-mono text-xs font-bold text-sky-900">{{ $filterCourse->code }}</span>
                    <span class="ms-1 text-slate-600">— {{ $filterCourse->title }}</span>
                </p>
                <a href="{{ $clearCourseFilterUrl }}" class="shrink-0 text-sm font-semibold text-sky-800 underline-offset-2 hover:underline">
                    {{ __('Clear filter') }}
                </a>
            </div>
        @endif

        @if (! empty($pf))
            @php
                $pfLabel = match ($pf) {
                    'flagged' => __('Flagged sessions'),
                    'auto_submitted' => __('Auto-submitted sessions'),
                    'phone_detected' => __('Phone detected events'),
                    'tab_switch_limit' => __('Tab switch limit reached'),
                    'held_results' => __('Held results'),
                    'assignments_grading' => __('Assignments awaiting grading'),
                    default => $pf,
                };
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200/90 bg-white px-4 py-3 text-sm sm:px-5">
                <p class="min-w-0 text-slate-800">
                    <span class="font-semibold text-slate-900">{{ __('Showing assessments matching:') }}</span>
                    <span class="ms-1 text-slate-700">{{ $pfLabel }}</span>
                </p>
                <a href="{{ $clearProctoringFilterUrl }}" class="shrink-0 text-sm font-semibold text-sky-800 underline-offset-2 hover:underline">
                    {{ __('Clear proctoring filter') }}
                </a>
            </div>
        @endif

        <div class="border-b border-slate-200">
            <nav class="-mb-px flex gap-8" aria-label="{{ __('Exam status tabs') }}">
                <a
                    href="{{ $activeTabUrl }}"
                    class="border-b-2 pb-3 text-xs font-bold uppercase tracking-wider transition {{ $isActiveTab ? 'border-sky-600 text-slate-900' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}"
                >
                    {{ __('Active') }}
                </a>
                <a
                    href="{{ $endedTabUrl }}"
                    class="border-b-2 pb-3 text-xs font-bold uppercase tracking-wider transition {{ ! $isActiveTab ? 'border-sky-600 text-slate-900' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}"
                >
                    {{ __('Ended') }}
                </a>
            </nav>
        </div>

        @if ($exams->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 bg-gradient-to-b from-slate-50/80 to-white px-6 py-16 text-center shadow-sm ring-1 ring-black/[0.02]">
                <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm ring-1 ring-slate-200/80">
                    <i class="fa-solid fa-file-circle-plus text-2xl" aria-hidden="true"></i>
                </div>
                <p class="mt-5 text-base font-semibold text-slate-900">
                    {{ $isActiveTab ? __('No active exams yet.') : __('No ended exams.') }}
                </p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
                    {{ $isActiveTab
                        ? __('Draft and published assessments appear here. Switch to Ended for archived quizzes.')
                        : __('Archived assessments appear here once you close them out.') }}
                </p>
                @if ($isActiveTab)
                    <a
                        href="{{ route('examiner.exams.create', array_filter(['course_id' => $filterCourseId])) }}"
                        class="mt-7 inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-sky-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-sky-600/25 transition hover:bg-sky-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2"
                    >
                        <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                        {{ __('Create exam') }}
                    </a>
                @endif
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-black/[0.03]">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                        <thead>
                            <tr class="bg-slate-50/90 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Title') }}</th>
                                <th class="min-w-[10rem] px-4 py-3 sm:px-5">{{ __('Class groups') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Course') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-center sm:px-5">{{ __('Q') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-center sm:px-5">{{ __('Dur') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 sm:px-5">{{ __('Status') }}</th>
                                <th class="whitespace-nowrap px-4 py-3 text-right sm:px-5">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($exams as $exam)
                                @php
                                    $metaPayload = array_filter([
                                        'TYPE' => $exam->assessment_type,
                                        'YEAR' => $exam->academicYear?->name,
                                    ], fn ($v) => $v !== null && $v !== '');
                                    $metaJson = $metaPayload !== [] ? json_encode([$metaPayload], JSON_UNESCAPED_UNICODE) : '';
                                    $rooms = $exam->course?->classrooms ?? collect();
                                @endphp
                                <tr class="bg-white transition hover:bg-slate-50/50">
                                    <td class="max-w-[14rem] px-4 py-4 align-top sm:px-5">
                                        <p class="text-xs font-bold uppercase leading-snug tracking-tight text-slate-900">
                                            {{ $exam->title }}
                                        </p>
                                        @if ($metaJson !== '')
                                            <p class="mt-1 font-mono text-[10px] leading-tight text-slate-400">{{ $metaJson }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top sm:px-5">
                                        @if ($rooms->isEmpty())
                                            <span class="text-xs text-slate-400">—</span>
                                        @else
                                            <div class="flex flex-col gap-1.5">
                                                @foreach ($rooms->take(3) as $room)
                                                    @php
                                                        $toneKey = $room->level_id !== null
                                                            ? ((int) $room->level_id % count($levelBadgePalette))
                                                            : ($loop->index % count($levelBadgePalette));
                                                    @endphp
                                                    <div>
                                                        <p class="text-[11px] font-semibold leading-tight text-slate-800">{{ $room->name }}</p>
                                                        @if ($room->level)
                                                            <span class="mt-0.5 inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase ring-1 {{ $levelBadgePalette[$toneKey] }}">
                                                                {{ __('Level') }} {{ $room->level->name ?? $room->level->code }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="max-w-[12rem] px-4 py-4 align-top text-xs font-medium text-slate-700 sm:px-5">
                                        <span class="line-clamp-2">{{ $exam->course?->title ?? '—' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center align-middle tabular-nums text-slate-800 sm:px-5">
                                        {{ (int) ($exam->questions_count ?? 0) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center align-middle tabular-nums text-slate-700 sm:px-5">
                                        @if ($exam->duration_minutes)
                                            {{ (int) $exam->duration_minutes }}m
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 align-middle sm:px-5">
                                        @php
                                            $st = (string) $exam->status;
                                            $statusLabel = match ($st) {
                                                'published' => __('Published'),
                                                'draft' => __('Draft'),
                                                'archived' => __('Archived'),
                                                default => \Illuminate\Support\Str::headline($st),
                                            };
                                        @endphp
                                        <span class="text-xs font-bold uppercase tracking-wide text-slate-900">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right align-middle text-xs font-semibold sm:px-5">
                                        <a href="{{ route('examiner.exams.analytics.show', $exam) }}" class="text-sky-600 hover:text-sky-800 hover:underline">{{ __('Analytics') }}</a>
                                        <span class="text-slate-200" aria-hidden="true"> | </span>
                                        <a href="{{ route('examiner.quizzes.workspace', $exam) }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:text-sky-800 hover:underline">{{ __('View') }}</a>
                                        <span class="text-slate-200" aria-hidden="true"> | </span>
                                        <a href="{{ route('examiner.quizzes.workspace', $exam) }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:text-sky-800 hover:underline">{{ __('Edit') }}</a>
                                        <span class="text-slate-200" aria-hidden="true"> | </span>
                                        <a href="{{ route('examiner.quizzes.workspace', array_merge(['exam' => $exam], $sessionsTabQuery)) }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 hover:text-sky-800 hover:underline">{{ __('Sessions') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($exams->hasPages())
            <nav class="flex justify-center border-t border-slate-100 pt-6" aria-label="{{ __('Pagination') }}">
                {{ $exams->links() }}
            </nav>
        @endif
    </div>
</x-layouts.examiner>
