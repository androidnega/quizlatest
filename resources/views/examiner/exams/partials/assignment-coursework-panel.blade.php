@php
    /** @var \App\Models\Quiz $exam */
    $isAssignment = $exam->isAssignment();
    /** @var array<string, int>|null $submissionStats */
    $submissionStats = $submissionStats ?? null;
    $variant = $variant ?? 'summary';
    $tz = config('app.timezone');
    $pendingMarking = (int) ($submissionStats['pending_manual'] ?? 0);
    $gradingUrl = route('examiner.grading.pending', ['exam' => $exam->id]);
    $settingsTabUrl = route('examiner.quizzes.workspace', ['exam' => $exam, 'tab' => 'settings']);
@endphp
@if ($isAssignment)
    @if ($variant === 'summary')
        <section class="rounded-xl border border-sky-200/90 bg-gradient-to-br from-sky-50/80 to-white p-4 shadow-sm sm:p-5" aria-labelledby="assignment-summary-heading">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="assignment-summary-heading" class="text-sm font-semibold text-slate-900">{{ $exam->title }}</h2>
                    <p class="mt-1 text-xs text-slate-600">
                        @if ($exam->due_at)
                            {{ __('Due') }} {{ $exam->due_at->timezone($tz)->format('M j, Y · H:i') }}
                        @else
                            {{ __('No due date set') }}
                        @endif
                        @if ($exam->grades_released_at)
                            <span class="text-slate-400"> · </span><span class="text-emerald-700">{{ __('Grades released') }}</span>
                        @endif
                    </p>
                </div>
                <a
                    href="{{ $settingsTabUrl }}"
                    @click.prevent="syncWorkspaceTab('settings')"
                    class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                >
                    {{ __('Settings') }}
                </a>
            </div>

            @if (is_array($submissionStats))
                <dl class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-lg border border-slate-200/80 bg-white/90 px-3 py-2">
                        <dt class="text-[11px] font-medium text-slate-500">{{ __('Submissions') }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900">{{ number_format((int) ($submissionStats['submitted_sessions'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200/80 bg-white/90 px-3 py-2">
                        <dt class="text-[11px] font-medium text-slate-500" title="{{ __('Late submissions') }}">{{ __('Late submissions') }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900">{{ number_format((int) ($submissionStats['late_submissions'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg border border-amber-200/80 bg-amber-50/50 px-3 py-2">
                        <dt class="text-[11px] font-medium text-amber-800">{{ __('To mark') }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold tabular-nums text-amber-900">{{ number_format($pendingMarking) }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200/80 bg-white/90 px-3 py-2">
                        <dt class="text-[11px] font-medium text-slate-500">{{ __('Graded') }}</dt>
                        <dd class="mt-0.5 text-lg font-semibold tabular-nums text-slate-900">{{ number_format((int) ($submissionStats['graded'] ?? 0)) }}</dd>
                    </div>
                </dl>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                @if ($exam->status === 'published')
                    <form method="post" action="{{ route('examiner.exams.unpublish', $exam) }}" class="inline" onsubmit="return confirm(@js(__('Stop new submissions for this assignment?')));">
                        @csrf
                        <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-100">{{ __('Unpublish') }}</button>
                    </form>
                @elseif ($canEditSchedule)
                    <form method="post" action="{{ route('examiner.exams.publish', $exam) }}" class="inline" onsubmit="return confirm(@js(__('Publish this assignment for students?')));">
                        @csrf
                        <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">{{ __('Publish') }}</button>
                    </form>
                @endif
                <a
                    href="{{ $gradingUrl }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                >
                    <i class="fa-solid fa-pen-to-square text-violet-700" aria-hidden="true"></i>
                    {{ __('Grade manually') }}
                    @if ($pendingMarking > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold tabular-nums text-amber-900">{{ $pendingMarking }}</span>
                    @endif
                </a>
                @if (($aiEnabled ?? false) && $pendingMarking > 0)
                    <form
                        method="post"
                        action="{{ route('examiner.exams.assignment-grade-ai', $exam) }}"
                        class="inline"
                        onsubmit="return confirm(@js(__('Run AI assist on all pending submissions? Review marks before releasing grades.')));"
                    >
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-violet-700 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-violet-800">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                            {{ __('AI assist') }}
                        </button>
                    </form>
                @endif
                <a
                    href="{{ route('examiner.quizzes.workspace', ['exam' => $exam, 'tab' => 'sessions']) }}"
                    @click.prevent="syncWorkspaceTab('sessions')"
                    class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                >
                    {{ __('Submissions') }}
                </a>
                @if ($exam->status === 'published' && ! $exam->grades_released_at)
                    <form method="post" action="{{ route('examiner.exams.release-assignment-grades', $exam) }}" class="inline" onsubmit="return confirm(@js(__('Release grades and feedback to students?')));">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                            {{ __('Release grades') }}
                        </button>
                    </form>
                @endif
            </div>

            @if (filled($exam->description))
                <details class="mt-4 rounded-lg border border-slate-200/80 bg-white/80 px-3 py-2">
                    <summary class="cursor-pointer text-xs font-semibold text-slate-700">{{ __('Student instructions') }}</summary>
                    <div class="mt-2 max-h-32 overflow-y-auto whitespace-pre-wrap text-sm text-slate-800">{{ $exam->description }}</div>
                </details>
            @endif
        </section>
    @elseif ($variant === 'settings')
        <section class="space-y-6" aria-labelledby="assignment-settings-heading">
            <div>
                <h2 id="assignment-settings-heading" class="text-sm font-semibold text-slate-900">{{ __('Assignment settings') }}</h2>
                <p class="mt-1 text-xs text-slate-600">
                    {{ __('Title, instructions, due date, and how students submit. The essay question itself is shown on Overview.') }}
                </p>
            </div>

            @if ($canEditSchedule)
                <form method="post" action="{{ route('examiner.exams.schedule.update', $exam) }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    @csrf
                    @method('PATCH')
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Details') }}</h3>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="assignment-title">{{ __('Title') }}</label>
                        <input id="assignment-title" type="text" name="title" value="{{ old('title', $exam->title) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="assignment-instructions">{{ __('Instructions for students') }}</label>
                        <p class="mt-0.5 text-[11px] text-slate-500">{{ __('How to submit, formatting, word count, files allowed — not the essay question itself.') }}</p>
                        <textarea id="assignment-instructions" name="description" rows="5" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" required>{{ old('description', $exam->description) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="assignment-due">{{ __('Due date') }}</label>
                        <input id="assignment-due" type="datetime-local" name="due_at" value="{{ old('due_at', $exam->due_at?->timezone($tz)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">{{ __('Save details') }}</button>
                </form>

                <form method="post" action="{{ route('examiner.exams.assignment-submission.update', $exam) }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    @csrf
                    @method('PATCH')
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Submission format') }}</h3>
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
                    <input type="hidden" name="assignment_allow_code" value="0" />
                    <label class="flex items-start gap-2 text-sm text-slate-800">
                        <input type="checkbox" name="assignment_allow_code" value="1" class="mt-0.5 size-4 rounded border-slate-300 text-sky-600" @checked(old('assignment_allow_code', $exam->assignment_allow_code ?? false)) @disabled(! (bool) old('assignment_allows_text', $exam->assignment_allows_text)) />
                        <span>
                            {{ __('Enable code editor with syntax highlighting') }}
                            <span class="block text-[11px] text-slate-500">{{ __('Students see a colorful code editor (with a language picker). Leave off for prose-only assignments.') }}</span>
                        </span>
                    </label>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="assignment-ext">{{ __('Allowed extensions') }}</label>
                        <input id="assignment-ext" type="text" name="assignment_allowed_extensions" value="{{ old('assignment_allowed_extensions', is_array($exam->assignment_allowed_extensions) ? implode(', ', $exam->assignment_allowed_extensions) : '') }}" placeholder="pdf, docx, txt" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600" for="assignment-max-kb">{{ __('Max file size (KB)') }}</label>
                        <input id="assignment-max-kb" type="number" name="assignment_max_file_kb" min="256" max="51200" value="{{ old('assignment_max_file_kb', $exam->assignment_max_file_kb ?? 5120) }}" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">{{ __('Save submission options') }}</button>
                    <p class="text-[11px] leading-relaxed text-slate-500">{{ __('Paste blocking logs attempts for review; it does not deduct marks.') }}</p>
                </form>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                    <p>{{ __('This assignment is published. Unpublish to edit title, instructions, due date, or submission options.') }}</p>
                    <dl class="mt-4 grid gap-3 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="font-medium text-slate-500">{{ __('Instructions') }}</dt>
                            <dd class="mt-1 whitespace-pre-wrap">{{ $exam->description ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">{{ __('Submission') }}</dt>
                            <dd class="mt-1">
                                @if ($exam->assignment_allows_text) {{ __('Typed in-app') }}@endif
                                @if ($exam->assignment_allows_files)
                                    @if ($exam->assignment_allows_text), @endif
                                    {{ __('File upload') }}
                                    ({{ is_array($exam->assignment_allowed_extensions) ? implode(', ', $exam->assignment_allowed_extensions) : '—' }})
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif

            @if ($exam->status === 'published' && ! $exam->grades_released_at)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <p class="text-xs font-semibold text-emerald-950">{{ __('Release grades') }}</p>
                    <p class="mt-1 text-[11px] text-emerald-900/90">{{ __('Students only see marks and feedback after you release.') }}</p>
                    <form method="post" action="{{ route('examiner.exams.release-assignment-grades', $exam) }}" class="mt-3" onsubmit="return confirm(@js(__('Release grades and feedback to students for this assignment?')));">
                        @csrf
                        <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                            {{ __('Release grades to students') }}
                        </button>
                    </form>
                </div>
            @endif
        </section>
    @endif
@endif
