<x-layouts.student>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>
    <x-slot name="subtitle">{{ __('Shortcuts to what matters most for your classes — help and profile stay in the side menu.') }}</x-slot>

    @php
        $parts = \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->explode(' ')->filter();
        $firstName = $parts->first() ?: $user->name;
        $sessionExam = $activeSession?->exam;
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
        $dashboardCourseNewMaterials = $dashboard_course_new_materials ?? [];
        $dashboardTip = (string) ($dashboard_tip ?? '');
        $dashboardPolicyNotice = $dashboard_policy_notice ?? null;
        $dashboardNotices = $dashboard_notices ?? [];
        $materialsNav = ! empty($studentCourseMaterialsNavEnabled);
        $navCard = 'group flex min-h-[76px] flex-col justify-center rounded-xl border-2 p-3.5 text-left transition hover:brightness-[0.97] sm:min-h-0 sm:p-4';
        $studentNavCards = [
            [
                'href' => route('student.work.index'),
                'icon' => 'fa-clipboard-list',
                'title' => __('Your work'),
                'sub' => __('Assessments & deadlines'),
                'card' => 'border-emerald-700 bg-emerald-100',
                'iconWrap' => 'bg-emerald-200 text-emerald-950',
            ],
            [
                'href' => route('student.assignments.index'),
                'icon' => 'fa-file-pen',
                'title' => __('Assignments'),
                'sub' => __('Coursework to hand in'),
                'card' => 'border-violet-700 bg-violet-100',
                'iconWrap' => 'bg-violet-200 text-violet-950',
            ],
            [
                'href' => route('student.results.index'),
                'icon' => 'fa-square-poll-vertical',
                'title' => __('Results'),
                'sub' => __('Scores when released'),
                'card' => 'border-rose-700 bg-rose-100',
                'iconWrap' => 'bg-rose-200 text-rose-950',
            ],
        ];
        if ($practiceEnabled) {
            $studentNavCards[] = [
                'href' => route('student.practice.revision'),
                'icon' => 'fa-book-open-reader',
                'title' => __('Revision'),
                'sub' => __('Practice & check understanding'),
                'card' => 'border-teal-700 bg-teal-100',
                'iconWrap' => 'bg-teal-200 text-teal-950',
            ];
            $studentNavCards[] = [
                'href' => route('student.practice.materials.index'),
                'icon' => 'fa-folder-open',
                'title' => __('Materials'),
                'sub' => __('Files from your courses'),
                'card' => 'border-sky-700 bg-sky-100',
                'iconWrap' => 'bg-sky-200 text-sky-950',
            ];
        } elseif ($materialsNav) {
            $studentNavCards[] = [
                'href' => route('student.practice.materials.index'),
                'icon' => 'fa-folder-open',
                'title' => __('Materials'),
                'sub' => __('Files from your courses'),
                'card' => 'border-sky-700 bg-sky-100',
                'iconWrap' => 'bg-sky-200 text-sky-950',
            ];
        }
    @endphp

    <div class="w-full min-w-0 space-y-4 pb-8 text-slate-950">
        {{-- Greeting --}}
        <div class="rounded-2xl border-2 border-indigo-200 bg-indigo-100 px-4 py-4 sm:px-6 sm:py-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-900">{{ __('Dashboard') }}</p>
                    <h1 class="mt-0.5 text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">{{ __('Hi, :name', ['name' => $firstName]) }}</h1>
                    <p class="mt-1.5 text-sm text-slate-700">{{ __('Use the bell (top right) for notifications — then jump to your classes below.') }}</p>
                </div>
                <a
                    href="{{ route('profile.edit') }}"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-indigo-300 bg-indigo-200 text-indigo-950 transition hover:bg-indigo-300 md:hidden"
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

        <section class="rounded-2xl border-2 border-slate-200 bg-slate-100 p-4 sm:p-5" aria-labelledby="dash-nav-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 id="dash-nav-heading" class="text-sm font-semibold text-slate-900">{{ __('Essentials') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-600">{{ __('Assessments, hand-ins, results — and study tools when your school turns them on.') }}</p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($studentNavCards as $card)
                    <a href="{{ $card['href'] }}" class="{{ $navCard }} {{ $card['card'] }}">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $card['iconWrap'] }}" aria-hidden="true">
                            <i class="fa-solid {{ $card['icon'] }} text-sm"></i>
                        </span>
                        <span class="mt-2.5 text-sm font-semibold text-slate-950">{{ $card['title'] }}</span>
                        <span class="mt-0.5 text-xs text-slate-800/90">{{ $card['sub'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <a
            href="{{ route('student.work.index') }}"
            class="group flex items-center gap-4 rounded-2xl border-2 border-emerald-700 bg-emerald-100 p-4 transition hover:brightness-[0.97] sm:p-5"
        >
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-200 text-emerald-950" aria-hidden="true">
                <i class="fa-solid fa-clipboard-list text-lg"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-950">{{ __('Full assessment list') }}</p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-800">{{ __('Same “Your work” page as the card — every open, due, or finished item in one place.') }}</p>
            </div>
            <span class="shrink-0 text-sm font-semibold text-emerald-950">{{ __('Open') }} →</span>
        </a>

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
            <section
                class="rounded-2xl border-2 border-amber-600 bg-amber-100 p-4 sm:p-5"
                aria-labelledby="dash-notices-heading"
            >
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 id="dash-notices-heading" class="text-sm font-semibold text-slate-950">{{ __('Updates for you') }}</h2>
                    <a href="{{ route('student.notifications.index') }}" class="text-xs font-semibold text-amber-950 underline-offset-2 hover:underline">{{ __('View all') }}</a>
                </div>
                <ul class="mt-3 divide-y divide-amber-500/25 rounded-xl border-2 border-amber-500 bg-amber-50">
                    @foreach (array_slice($dashboardNotices, 0, 4) as $n)
                        <li>
                            <a
                                href="{{ $n['href'] ?? route('student.notifications.index') }}"
                                class="flex min-h-[52px] flex-col gap-0.5 px-3 py-3 text-left transition hover:bg-amber-100 sm:flex-row sm:items-center sm:justify-between sm:px-4"
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

    </div>

    @if (is_array($dashboardPolicyNotice) && ($dashboardPolicyNotice['message'] ?? '') !== '')
        <div
            class="pointer-events-none fixed bottom-4 left-0 right-0 z-50 flex justify-center px-4 sm:justify-end sm:px-6"
            role="status"
        >
            <div
                class="pointer-events-auto w-full max-w-md rounded-xl border-2 border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white"
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
