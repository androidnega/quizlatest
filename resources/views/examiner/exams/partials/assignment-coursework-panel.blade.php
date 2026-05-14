@php
    /** @var \App\Models\Quiz $exam */
    $isAssignment = $exam->isAssignment();
    /** @var array<string, int>|null $submissionStats */
    $submissionStats = $submissionStats ?? null;
    $tz = config('app.timezone');
@endphp
@if ($isAssignment)
    <section class="rounded-xl border border-sky-200 bg-sky-50/60 p-4 shadow-sm sm:p-5" aria-labelledby="assignment-coursework-heading">
        <h2 id="assignment-coursework-heading" class="text-sm font-semibold text-slate-900">{{ __('Coursework (assignment)') }}</h2>
        <p class="mt-1 text-xs leading-relaxed text-slate-700">
            {{ __('Students can type in the app and optionally upload files based on the submission format below. Copy/paste blocking is configurable when typed responses are enabled.') }}
        </p>

        <div class="mt-4 rounded-lg border border-slate-200/80 bg-white/90 px-3 py-3 text-sm text-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Instructions for students') }}</p>
            <div class="mt-2 max-h-40 overflow-y-auto whitespace-pre-wrap text-slate-900">{{ filled($exam->description) ? $exam->description : '—' }}</div>
        </div>

        <dl class="mt-4 grid gap-2 text-xs text-slate-800 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                <dt class="font-medium text-slate-500">{{ __('Due') }}</dt>
                <dd class="mt-0.5 font-semibold text-slate-900">
                    {{ $exam->due_at?->timezone($tz)->format('M j, Y · H:i') ?? '—' }}
                </dd>
            </div>
            @if ($exam->start_time || $exam->end_time)
                <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                    <dt class="font-medium text-slate-500">{{ __('Availability') }}</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">
                        {{ $exam->start_time?->timezone($tz)->format('M j, H:i') ?? '—' }}
                        —
                        {{ $exam->end_time?->timezone($tz)->format('M j, H:i') ?? '—' }}
                    </dd>
                </div>
            @endif
            <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                <dt class="font-medium text-slate-500">{{ __('Grades visible to students') }}</dt>
                <dd class="mt-0.5 font-semibold text-slate-900">
                    @if ($exam->grades_released_at)
                        {{ __('Yes') }} · {{ $exam->grades_released_at->timezone($tz)->format('M j, Y · H:i') }}
                    @else
                        {{ __('Not yet — release when ready') }}
                    @endif
                </dd>
            </div>
        </dl>

        @if (is_array($submissionStats))
            <dl class="mt-3 grid gap-2 text-xs text-slate-800 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                    <dt class="font-medium text-slate-500">{{ __('Submissions') }}</dt>
                    <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900">{{ number_format((int) ($submissionStats['submitted_sessions'] ?? 0)) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                    <dt class="font-medium text-slate-500">{{ __('Late submissions') }}</dt>
                    <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900">{{ number_format((int) ($submissionStats['late_submissions'] ?? 0)) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                    <dt class="font-medium text-slate-500">{{ __('Awaiting marking') }}</dt>
                    <dd class="mt-0.5 text-lg font-semibold tabular-nums text-amber-800">{{ number_format((int) ($submissionStats['pending_manual'] ?? 0)) }}</dd>
                </div>
                <div class="rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                    <dt class="font-medium text-slate-500">{{ __('Graded') }} / {{ __('Held') }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                        {{ number_format((int) ($submissionStats['graded'] ?? 0)) }}
                        <span class="text-slate-400">·</span>
                        {{ number_format((int) ($submissionStats['held'] ?? 0)) }}
                    </dd>
                </div>
            </dl>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('examiner.grading.pending') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
                {{ __('Open essay grading queue') }}
            </a>
            <a href="{{ route('examiner.quizzes.workspace', ['exam' => $exam, 'tab' => 'sessions']) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
                {{ __('View student sessions') }}
            </a>
        </div>

        @if ($canEditSchedule)
            <form method="post" action="{{ route('examiner.exams.schedule.update', $exam) }}" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-white p-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-title">{{ __('Title') }}</label>
                    <input id="assignment-title" type="text" name="title" value="{{ old('title', $exam->title) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-instructions">{{ __('Instructions') }}</label>
                    <textarea id="assignment-instructions" name="description" rows="5" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>{{ old('description', $exam->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-due">{{ __('Due date') }}</label>
                    <input id="assignment-due" type="datetime-local" name="due_at" value="{{ old('due_at', $exam->due_at?->timezone($tz)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">{{ __('Save coursework details') }}</button>
                </div>
            </form>
        @endif

        @if ($exam->status === 'published' && ! $exam->grades_released_at)
            <form method="post" action="{{ route('examiner.exams.release-assignment-grades', $exam) }}" class="mt-4" onsubmit="return confirm(@js(__('Release grades and feedback to students for this assignment?')));">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                    {{ __('Release grades to students') }}
                </button>
            </form>
        @endif

        @if ($canEditSchedule)
            <form method="post" action="{{ route('examiner.exams.assignment-submission.update', $exam) }}" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-white p-3">
                @csrf
                @method('PATCH')
                <p class="text-xs font-semibold text-slate-800">{{ __('Submission format') }}</p>
                <input type="hidden" name="assignment_allows_text" value="0" />
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input type="checkbox" name="assignment_allows_text" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('assignment_allows_text', $exam->assignment_allows_text)) />
                    <span>{{ __('Allow typed responses in-app') }}</span>
                </label>
                <input type="hidden" name="assignment_allows_files" value="0" />
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input type="checkbox" name="assignment_allows_files" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('assignment_allows_files', $exam->assignment_allows_files)) />
                    <span>{{ __('Allow file uploads') }}</span>
                </label>
                <input type="hidden" name="assignment_attachment_required" value="0" />
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input type="checkbox" name="assignment_attachment_required" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('assignment_attachment_required', $exam->assignment_attachment_required)) @disabled(! (bool) old('assignment_allows_files', $exam->assignment_allows_files)) />
                    <span>{{ __('Require uploaded file before submit') }}</span>
                </label>
                <input type="hidden" name="assignment_disable_paste" value="0" />
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input type="checkbox" name="assignment_disable_paste" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('assignment_disable_paste', $exam->assignment_disable_paste ?? true)) @disabled(! (bool) old('assignment_allows_text', $exam->assignment_allows_text)) />
                    <span>{{ __('Disable copy/paste in typed response') }}</span>
                </label>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-ext">{{ __('Allowed extensions (comma-separated, e.g. pdf, docx)') }}</label>
                    <input id="assignment-ext" type="text" name="assignment_allowed_extensions" value="{{ old('assignment_allowed_extensions', is_array($exam->assignment_allowed_extensions) ? implode(', ', $exam->assignment_allowed_extensions) : '') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600" for="assignment-max-kb">{{ __('Max file size (KB)') }}</label>
                    <input id="assignment-max-kb" type="number" name="assignment_max_file_kb" min="256" max="51200" value="{{ old('assignment_max_file_kb', $exam->assignment_max_file_kb ?? 5120) }}" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                </div>
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">{{ __('Save submission options') }}</button>
            </form>
        @endif

        <p class="mt-3 text-[11px] leading-relaxed text-slate-600">
            {{ __('Use the grading queue for marks and feedback, then release grades when you are ready. Paste blocking, if enabled, logs attempts for review and does not deduct marks.') }}
        </p>
    </section>
@endif
