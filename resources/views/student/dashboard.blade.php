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
    @endphp

    <div class="w-full min-w-0 space-y-5 pb-6 text-slate-950">
        @if ($errors->has('exam'))
            <div class="flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
                <span>{{ $errors->first('exam') }}</span>
            </div>
        @endif

        @if ($examSessionPaused && $sessionExam)
            <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <i class="fa-solid fa-pause mt-0.5 shrink-0 text-amber-700" aria-hidden="true"></i>
                <div class="min-w-0">
                    <p class="font-semibold">{{ __('Your assessment timer is paused') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-900/90">
                        {{ __('Time is frozen until you return and tap Resume inside the assessment.') }}
                    </p>
                    <a href="{{ route('student.exam.take', $activeSession) }}" class="mt-3 inline-flex min-h-[44px] items-center justify-center rounded-lg bg-amber-800 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-900">
                        {{ __('Open assessment to resume') }}
                    </a>
                </div>
            </div>
        @endif

        @if (! $classYearOk)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ __('Class year notice') }}</p>
                <p class="mt-1 text-xs leading-relaxed">{{ __('Your class may not match the active academic year. Some items could look empty until your coordinator updates your enrollment.') }}</p>
            </div>
        @endif

        @if ($user->class_id === null)
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800">
                <p class="leading-relaxed text-slate-700">
                    <i class="fa-solid fa-circle-info me-1 text-slate-500" aria-hidden="true"></i>
                    {{ __('student_ui.class_group_not_assigned') }}
                </p>
            </div>
        @endif

        @if (! empty($dashboardCourseNewMaterials))
            <div class="rounded-xl border border-sky-200 bg-sky-50/90 px-4 py-3 text-sm text-sky-950">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-900/80">{{ __('Since your last visit') }}</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($dashboardCourseNewMaterials as $row)
                        @php $n = (int) $row['count']; @endphp
                        <li class="leading-snug">
                            @if ($n === 1)
                                {{ __('1 new file in :course', ['course' => $row['name']]) }}
                            @else
                                {{ __(':count new files in :course', ['count' => number_format($n), 'course' => $row['name']]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($practiceEnabled)
                    <a href="{{ route('student.practice.materials.index') }}" class="mt-2 inline-flex text-xs font-semibold text-sky-800 underline-offset-2 hover:underline">
                        {{ __('Open course materials') }}
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
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700" aria-hidden="true">
                    <i class="fa-solid fa-lightbulb text-sm"></i>
                </span>
                <p class="min-w-0 flex-1 leading-relaxed">{{ $dashboardTip }}</p>
                <button
                    type="button"
                    class="-m-1 inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400/40"
                    @click="dismissed = true; try { localStorage.setItem(key, '1'); } catch (e) {}"
                    aria-label="{{ __('Dismiss tip') }}"
                >
                    <i class="fa-solid fa-xmark text-base" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm text-slate-500">{{ __('Welcome back') }}</p>
                <h1 class="mt-0.5 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ __('Hi, :name', ['name' => $firstName]) }}</h1>
                <p class="mt-1 max-w-xl text-sm text-slate-600">{{ __('Here is what needs your attention across assessments and assignments.') }}</p>
            </div>
            <a
                href="{{ route('profile.edit') }}"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 md:hidden"
                aria-label="{{ __('Profile') }}"
            >
                <i class="fa-solid fa-user text-lg" aria-hidden="true"></i>
            </a>
        </header>

        <section class="rounded-xl border border-slate-200 bg-white px-4 py-4 sm:px-5" aria-labelledby="student-summary-heading">
            <h2 id="student-summary-heading" class="text-sm font-semibold text-slate-900">{{ __('Your details') }}</h2>
            <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    __('Name') => $sum['name'] ?? null,
                    __('Index number') => $sum['index_number'] ?? null,
                    __('Class') => $sum['class'] ?? null,
                    __('Program') => $sum['program'] ?? null,
                    __('Level') => $sum['level'] ?? null,
                    __('Department') => $sum['department'] ?? null,
                    __('Academic year') => $sum['academic_year'] ?? null,
                    __('Semester') => $sum['semester'] ?? null,
                ] as $label => $value)
                    @if (filled($value))
                        <div class="min-w-0">
                            <dt class="text-xs font-medium text-slate-500">{{ $label }}</dt>
                            <dd class="truncate font-medium text-slate-900">{{ $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
            @php
                $anyDetail = collect($sum)->filter(fn ($v) => filled($v))->isNotEmpty();
            @endphp
            @if (! $anyDetail)
                <p class="mt-2 text-sm text-slate-500">{{ __('Your coordinator can help if class or program details look incomplete.') }}</p>
            @endif
        </section>

        <nav class="flex flex-wrap gap-2" aria-label="{{ __('Quick links') }}">
            <a href="{{ route('dashboard') }}#student-work" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                {{ __('Assessments') }}
            </a>
            <a href="{{ route('student.assignments.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                {{ __('Assignments') }}
            </a>
            <a href="{{ route('student.results.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                {{ __('Results') }}
            </a>
            @if ($practiceEnabled)
                <a href="{{ route('student.practice.revision') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                    {{ __('Revision') }}
                </a>
                <a href="{{ route('student.practice.materials.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                    {{ __('Materials') }}
                </a>
            @endif
            <a href="{{ route('profile.edit') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                {{ __('Profile') }}
            </a>
        </nav>

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
