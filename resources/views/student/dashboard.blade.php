<x-layouts.student>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>
    <x-slot name="subtitle">{{ __('Jump in with the cards below — details live on each page.') }}</x-slot>

    @php
        $parts = \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->explode(' ')->filter();
        $firstName = $parts->first() ?: $user->name;
        $sessionExam = $activeSession?->exam;
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
        $dashboardCourseNewMaterials = $dashboard_course_new_materials ?? [];
        $dashboardTip = (string) ($dashboard_tip ?? '');
        $dashboardPolicyNotice = $dashboard_policy_notice ?? null;
        $dashboardNotices = $dashboard_notices ?? [];
        $navCard = 'group flex min-h-[76px] flex-col justify-center rounded-2xl border p-3.5 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 sm:min-h-0 sm:p-4';
        $studentNavCards = [
            [
                'href' => route('student.notifications.index'),
                'icon' => 'fa-bell',
                'title' => __('Notifications'),
                'sub' => __('Due dates & status'),
                'card' => 'border-amber-200/90 bg-gradient-to-br from-amber-50 via-white to-amber-50/30 hover:border-amber-300',
                'iconWrap' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/80',
            ],
            [
                'href' => route('student.help'),
                'icon' => 'fa-circle-question',
                'title' => __('Help'),
                'sub' => __('How things work'),
                'card' => 'border-sky-200/90 bg-gradient-to-br from-sky-50 via-white to-sky-50/30 hover:border-sky-300',
                'iconWrap' => 'bg-sky-100 text-sky-800 ring-1 ring-sky-200/80',
            ],
            [
                'href' => route('dashboard') . '#student-work',
                'icon' => 'fa-clipboard-list',
                'title' => __('Your work'),
                'sub' => __('Open & due items'),
                'card' => 'border-emerald-200/90 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/30 hover:border-emerald-300',
                'iconWrap' => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80',
            ],
            [
                'href' => route('student.assignments.index'),
                'icon' => 'fa-file-pen',
                'title' => __('Assignments'),
                'sub' => __('All coursework'),
                'card' => 'border-violet-200/90 bg-gradient-to-br from-violet-50 via-white to-violet-50/30 hover:border-violet-300',
                'iconWrap' => 'bg-violet-100 text-violet-800 ring-1 ring-violet-200/80',
            ],
            [
                'href' => route('student.results.index'),
                'icon' => 'fa-square-poll-vertical',
                'title' => __('Results'),
                'sub' => __('Scores & feedback'),
                'card' => 'border-rose-200/90 bg-gradient-to-br from-rose-50 via-white to-rose-50/30 hover:border-rose-300',
                'iconWrap' => 'bg-rose-100 text-rose-800 ring-1 ring-rose-200/80',
            ],
        ];
        if ($practiceEnabled) {
            $studentNavCards[] = [
                'href' => route('student.practice.revision'),
                'icon' => 'fa-book-open-reader',
                'title' => __('Revision'),
                'sub' => __('Practice & summaries'),
                'card' => 'border-teal-200/90 bg-gradient-to-br from-teal-50 via-white to-teal-50/30 hover:border-teal-300',
                'iconWrap' => 'bg-teal-100 text-teal-800 ring-1 ring-teal-200/80',
            ];
            $studentNavCards[] = [
                'href' => route('student.practice.materials.index'),
                'icon' => 'fa-folder-open',
                'title' => __('Materials'),
                'sub' => __('Files & outlines'),
                'card' => 'border-cyan-200/90 bg-gradient-to-br from-cyan-50 via-white to-cyan-50/30 hover:border-cyan-300',
                'iconWrap' => 'bg-cyan-100 text-cyan-800 ring-1 ring-cyan-200/80',
            ];
        }
        $studentNavCards[] = [
            'href' => route('profile.edit'),
            'icon' => 'fa-user',
            'title' => __('Profile'),
            'sub' => __('Account & placement'),
            'card' => 'border-indigo-200/90 bg-gradient-to-br from-indigo-50 via-white to-indigo-50/30 hover:border-indigo-300',
            'iconWrap' => 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-200/80',
        ];
    @endphp

    <div class="w-full min-w-0 space-y-4 pb-8 text-slate-950">
        {{-- Greeting --}}
        <div class="overflow-hidden rounded-2xl border border-indigo-200/60 bg-gradient-to-br from-indigo-50 via-white to-sky-50 px-4 py-4 shadow-sm sm:px-6 sm:py-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-600/90">{{ __('Dashboard') }}</p>
                    <h1 class="mt-0.5 text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">{{ __('Hi, :name', ['name' => $firstName]) }}</h1>
                    <p class="mt-1.5 text-sm text-slate-600">{{ __('Pick a card to open a page — class and account details are on Profile.') }}</p>
                </div>
                <a
                    href="{{ route('profile.edit') }}"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-indigo-200/80 bg-white/80 text-indigo-700 shadow-sm transition hover:bg-white md:hidden"
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

        <section class="rounded-2xl border border-slate-200/90 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5" aria-labelledby="dash-nav-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 id="dash-nav-heading" class="text-sm font-semibold text-slate-900">{{ __('Quick links') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Each area has its own screen — nothing duplicated here.') }}</p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($studentNavCards as $card)
                    <a href="{{ $card['href'] }}" class="{{ $navCard }} {{ $card['card'] }}">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $card['iconWrap'] }}" aria-hidden="true">
                            <i class="fa-solid {{ $card['icon'] }} text-sm"></i>
                        </span>
                        <span class="mt-2.5 text-sm font-semibold text-slate-900 group-hover:text-slate-950">{{ $card['title'] }}</span>
                        <span class="mt-0.5 text-xs text-slate-600">{{ $card['sub'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

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
                class="rounded-2xl border border-fuchsia-200/70 bg-gradient-to-br from-fuchsia-50/50 via-white to-white p-4 shadow-sm sm:p-5"
                aria-labelledby="dash-notices-heading"
            >
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 id="dash-notices-heading" class="text-sm font-semibold text-slate-900">{{ __('Updates for you') }}</h2>
                    <a href="{{ route('student.notifications.index') }}" class="text-xs font-semibold text-fuchsia-800 underline-offset-2 hover:underline">{{ __('View all') }}</a>
                </div>
                <ul class="mt-3 divide-y divide-fuchsia-100/80 rounded-xl border border-fuchsia-100/90 bg-white/80">
                    @foreach (array_slice($dashboardNotices, 0, 4) as $n)
                        <li>
                            <a
                                href="{{ $n['href'] ?? route('student.notifications.index') }}"
                                class="flex min-h-[52px] flex-col gap-0.5 px-3 py-3 text-left transition hover:bg-fuchsia-50/40 sm:flex-row sm:items-center sm:justify-between sm:px-4"
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
