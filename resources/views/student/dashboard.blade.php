<x-layouts.student>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>
    <x-slot name="subtitle">{{ __('Your classes, assessments, and results.') }}</x-slot>

    @php
        $parts = \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->explode(' ')->filter();
        $firstName = $parts->first() ?: $user->name;
        $sessionExam = $activeSession?->exam;
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
        $sum = $studentProfileSummary ?? [];
        $dashboardCourseNewMaterials = $dashboard_course_new_materials ?? [];
        $dashboardTip = (string) ($dashboard_tip ?? '');
        $dashboardPolicyNotice = $dashboard_policy_notice ?? null;
        $shortcutCard = 'flex min-h-[72px] flex-col justify-center rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-slate-300 hover:bg-slate-50/80 active:bg-slate-50 sm:min-h-0 sm:p-4';
        $dashboardNotices = $dashboard_notices ?? [];
    @endphp

    <div class="w-full min-w-0 space-y-4 pb-8 text-slate-950">
        {{-- Greeting: one simple card --}}
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 sm:px-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">{{ __('Hi, :name', ['name' => $firstName]) }}</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ __('Use the cards below, then check your work list for what to do next.') }}</p>
                </div>
                <a
                    href="{{ route('profile.edit') }}"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-600 transition hover:bg-slate-100 md:hidden"
                    aria-label="{{ __('Profile') }}"
                >
                    <i class="fa-solid fa-user text-lg" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        @if ($errors->has('exam'))
            <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-white px-4 py-3 text-sm text-rose-900">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600" aria-hidden="true">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </span>
                <span class="min-w-0 pt-0.5">{{ $errors->first('exam') }}</span>
            </div>
        @endif

        @if ($examSessionPaused && $sessionExam)
            <div class="rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-amber-950">
                <div class="flex gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700" aria-hidden="true">
                        <i class="fa-solid fa-pause"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ __('Timer paused') }}</p>
                        <p class="mt-1 text-xs text-amber-900/90">{{ __('Open the assessment and tap Resume to continue.') }}</p>
                        <a href="{{ route('student.exam.take', $activeSession) }}" class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-amber-800 px-4 text-xs font-semibold text-white hover:bg-amber-900 sm:w-auto">
                            {{ __('Resume') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif

        @if (! $classYearOk)
            <div class="rounded-xl border border-amber-200 bg-white px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ __('Class year') }}</p>
                <p class="mt-1 text-xs leading-relaxed text-amber-900/90">{{ __('Your class may not match the active year. Ask your coordinator if lists look empty.') }}</p>
            </div>
        @endif

        @if ($user->class_id === null)
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800">
                <p class="leading-relaxed text-slate-700">
                    <i class="fa-solid fa-circle-info me-1.5 text-slate-400" aria-hidden="true"></i>
                    {{ __('student_ui.class_group_not_assigned') }}
                </p>
            </div>
        @endif

        @if (! empty($dashboardCourseNewMaterials))
            <div class="rounded-xl border border-sky-200 bg-white px-4 py-3 text-sm text-sky-950">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-800/80">{{ __('New since last visit') }}</p>
                <ul class="mt-2 space-y-1.5 text-sm">
                    @foreach ($dashboardCourseNewMaterials as $row)
                        @php $n = (int) $row['count']; @endphp
                        <li>
                            @if ($n === 1)
                                {{ __('1 new file in :course', ['course' => $row['name']]) }}
                            @else
                                {{ __(':count new files in :course', ['count' => number_format($n), 'course' => $row['name']]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.materials.index') }}" class="mt-3 inline-flex min-h-[44px] items-center text-xs font-semibold text-sky-800 underline-offset-2 hover:underline">
                        {{ __('Materials') }} →
                    </a>
                @endif
            </div>
        @endif

        @if ($dashboardTip !== '')
            @php
                $dashboardTipDismissKey = 'qs_student_dash_tip_v1_' . hash('sha256', $dashboardTip . '|' . app()->getLocale());
            @endphp
            <div
                x-data="{
                    key: @js($dashboardTipDismissKey),
                    dismissed: false,
                }"
                x-init="dismissed = (() => { try { return localStorage.getItem(key) === '1'; } catch (e) { return false; } })()"
                x-show="!dismissed"
                x-transition.opacity.duration.150ms
                class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                role="region"
                aria-label="{{ __('Tip') }}"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700" aria-hidden="true">
                    <i class="fa-solid fa-lightbulb text-sm"></i>
                </span>
                <p class="min-w-0 flex-1 leading-relaxed">{{ $dashboardTip }}</p>
                <button
                    type="button"
                    class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                    @click="dismissed = true; try { localStorage.setItem(key, '1'); } catch (e) {}"
                    aria-label="{{ __('Dismiss tip') }}"
                >
                    <i class="fa-solid fa-xmark text-base" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        @if ($dashboardNotices !== [])
            <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5" aria-labelledby="dash-notices-heading">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 id="dash-notices-heading" class="text-sm font-semibold text-slate-900">{{ __('Updates for you') }}</h2>
                    <a href="{{ route('student.notifications.index') }}" class="text-xs font-semibold text-sky-800 underline-offset-2 hover:underline">{{ __('View all') }}</a>
                </div>
                <ul class="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-100 bg-slate-50/40">
                    @foreach (array_slice($dashboardNotices, 0, 4) as $n)
                        <li>
                            <a
                                href="{{ $n['href'] ?? route('student.notifications.index') }}"
                                class="flex min-h-[52px] flex-col gap-0.5 px-3 py-3 text-left transition hover:bg-white sm:flex-row sm:items-center sm:justify-between sm:px-4"
                            >
                                <span>
                                    <span class="text-sm font-semibold text-slate-900">{{ $n['title'] }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-600">{{ $n['body'] }}</span>
                                </span>
                                <span class="mt-1 shrink-0 text-[11px] font-medium text-slate-400 sm:mt-0">
                                    {{ \Illuminate\Support\Carbon::parse($n['at'])->timezone(config('app.timezone'))->format('M j, H:i') }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Shortcuts: same destinations, clearer as cards --}}
        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5" aria-labelledby="dash-shortcuts-heading">
            <h2 id="dash-shortcuts-heading" class="text-sm font-semibold text-slate-900">{{ __('Go to') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('Everything you had before — just grouped here.') }}</p>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <a href="{{ route('student.notifications.index') }}" class="{{ $shortcutCard }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                        <i class="fa-solid fa-bell text-sm"></i>
                    </span>
                    <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Notifications') }}</span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ __('Due dates & status') }}</span>
                </a>
                <a href="{{ route('student.help') }}" class="{{ $shortcutCard }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                        <i class="fa-solid fa-circle-question text-sm"></i>
                    </span>
                    <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Help') }}</span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ __('How things work') }}</span>
                </a>
                <a href="{{ route('dashboard') }}#student-work" class="{{ $shortcutCard }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                        <i class="fa-solid fa-clipboard-list text-sm"></i>
                    </span>
                    <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Your work') }}</span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ __('Due & open items') }}</span>
                </a>
                <a href="{{ route('student.assignments.index') }}" class="{{ $shortcutCard }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                        <i class="fa-solid fa-file-pen text-sm"></i>
                    </span>
                    <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Assignments') }}</span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ __('All coursework') }}</span>
                </a>
                <a href="{{ route('student.results.index') }}" class="{{ $shortcutCard }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                        <i class="fa-solid fa-square-poll-vertical text-sm"></i>
                    </span>
                    <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Results') }}</span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ __('Scores & feedback') }}</span>
                </a>
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.revision') }}" class="{{ $shortcutCard }}">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                            <i class="fa-solid fa-book-open-reader text-sm"></i>
                        </span>
                        <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Revision') }}</span>
                        <span class="mt-0.5 text-xs text-slate-500">{{ __('Practice & summaries') }}</span>
                    </a>
                    <a href="{{ route('student.practice.materials.index') }}" class="{{ $shortcutCard }}">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                            <i class="fa-solid fa-folder-open text-sm"></i>
                        </span>
                        <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Materials') }}</span>
                        <span class="mt-0.5 text-xs text-slate-500">{{ __('Files & outlines') }}</span>
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}" class="{{ $shortcutCard }}">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700" aria-hidden="true">
                        <i class="fa-solid fa-user text-sm"></i>
                    </span>
                    <span class="mt-2 text-sm font-semibold text-slate-900">{{ __('Profile') }}</span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ __('Your account') }}</span>
                </a>
            </div>
        </section>

        {{-- Profile: one compact card --}}
        <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 sm:px-5" aria-labelledby="student-summary-heading">
            <h2 id="student-summary-heading" class="text-sm font-semibold text-slate-900">{{ __('You') }}</h2>
            <dl class="mt-3 grid gap-x-4 gap-y-2.5 text-sm sm:grid-cols-2">
                @foreach ([
                    __('Name') => $sum['name'] ?? null,
                    __('Index') => $sum['index_number'] ?? null,
                    __('Class') => $sum['class'] ?? null,
                    __('Program') => $sum['program'] ?? null,
                    __('Level') => $sum['level'] ?? null,
                    __('Department') => $sum['department'] ?? null,
                    __('Year') => $sum['academic_year'] ?? null,
                    __('Semester') => $sum['semester'] ?? null,
                ] as $label => $value)
                    @if (filled($value))
                        <div class="min-w-0">
                            <dt class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                            <dd class="truncate text-sm font-medium text-slate-900">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
            @php
                $anyDetail = collect($sum)->filter(fn ($v) => filled($v))->isNotEmpty();
            @endphp
            @if (! $anyDetail)
                <p class="mt-2 text-xs text-slate-500">{{ __('Your coordinator can update missing class or program details.') }}</p>
            @endif
        </section>

        @include('student.partials.assessment-worklist')
    </div>

    @if (is_array($dashboardPolicyNotice) && ($dashboardPolicyNotice['message'] ?? '') !== '')
        <div
            class="pointer-events-none fixed bottom-4 left-0 right-0 z-50 flex justify-center px-4 sm:justify-end sm:px-6"
            role="status"
        >
            <div
                class="pointer-events-auto w-full max-w-md rounded-xl border border-slate-800/15 bg-slate-900 px-4 py-3 text-sm text-white shadow-lg shadow-slate-900/20"
            >
                <p class="font-medium leading-snug">{{ $dashboardPolicyNotice['message'] }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    @if (($dashboardPolicyNotice['faq_url'] ?? '') !== '')
                        <a
                            href="{{ $dashboardPolicyNotice['faq_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs font-semibold text-teal-200 underline-offset-2 hover:underline"
                        >
                            {{ __('Read FAQ') }}
                        </a>
                    @endif
                    <form method="post" action="{{ route('student.dashboard.policy-notice.dismiss') }}" class="inline">
                        @csrf
                        <button type="submit" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/20">
                            {{ __('Dismiss') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</x-layouts.student>
