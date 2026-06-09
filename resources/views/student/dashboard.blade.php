<x-layouts.student>
    @php
        $parts = \Illuminate\Support\Str::of((string) ($user->name ?? ''))->trim()->explode(' ')->filter();
        $lastName = $parts->count() > 1 ? $parts->last() : ($parts->first() ?: $user->name);
        $sessionExam = $activeSession?->exam;
        $examSessionPaused = $activeSession !== null && $activeSession->status === 'paused';
        $dashboardCourseNewMaterials = $dashboard_course_new_materials ?? [];
        $dashboardTip = (string) ($dashboard_tip ?? '');
        $dashboardPolicyNotice = $dashboard_policy_notice ?? null;
        $dashboardNotices = $dashboard_notices ?? [];
        $dashboardNewAssessments = $dashboard_new_assessments ?? [];
        $materialsHubEnabled = ! empty($studentMaterialsBrowseEnabled);
        $materialsHref = route('student.practice.materials.index');
        $statOpen = (int) ($dashboard_stat_open_assessments ?? count($dashboardNewAssessments));
        $statDue = (int) ($dashboard_stat_assignments_due ?? 0);
        $statPending = (int) ($dashboard_stat_pending_results ?? 0);
        $statNotices = (int) ($dashboard_stat_notice_count ?? ($studentNoticeCount ?? count($dashboardNotices)));
        $nextAction = $dashboard_next_action ?? null;
        $user->loadMissing(['classroom.academicYearStruct', 'level']);
        $groupLabel = $user->classroom
            ? trim($user->classroom->name.' '.(string) ($user->classroom->section ?? ''))
            : null;
        $levelLabel = $user->level?->name ?? $user->level?->code;
        $mobileProfileSubline = collect([$groupLabel, $levelLabel])->filter()->implode(' · ');
        $semesterLabel = $user->classroom?->academicYearStruct?->name
            ?? ($user->classroom?->academic_year ? (string) $user->classroom->academic_year : null);
        $initials = \Illuminate\Support\Str::of((string) $user->name)->trim()->explode(' ')->filter()->take(2)->map(fn ($w) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($w, 0, 1)))->implode('');
        $newList = array_slice($dashboardNewAssessments, 0, 4);
        $cardSection = 'rounded-xl border border-slate-200/90 bg-white p-4 sm:p-5';
        $feedPanel = 'qs-std-dash-panel flex h-full flex-col '.$cardSection;

        // Super-admin gated experimental theme — when on, the mobile
        // dashboard renders the "wallet" variant instead of the standard
        // mobile hero + stats + feed. Desktop/tablet (>=lg) is unchanged.
        $useMobileWallet = app(\App\Services\SystemSettingsService::class)
            ->getBool('student_dashboard_mobile_wallet', false);
    @endphp

    @if ($useMobileWallet)
        @include('student.partials.dashboard-mobile-wallet', [
            'user' => $user,
            'lastName' => $lastName,
            'initials' => $initials,
            'mobileProfileSubline' => $mobileProfileSubline,
            'semesterLabel' => $semesterLabel,
            'statOpen' => $statOpen,
            'statDue' => $statDue,
            'statPending' => $statPending,
            'statNotices' => $statNotices,
            'newList' => $newList,
            'activeSession' => $activeSession,
            'sessionExam' => $sessionExam,
            'headerNoticeCount' => $studentNoticeCount ?? 0,
        ])
    @endif

    <div @class([
        'qs-std-page-wrap pt-2 lg:pt-6',
        'hidden lg:block' => $useMobileWallet,
    ])>
        @if ($errors->has('exam'))
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0" aria-hidden="true"></i>
                <span class="min-w-0">{{ $errors->first('exam') }}</span>
            </div>
        @endif

        @if ($examSessionPaused && $sessionExam)
            <div class="mb-4 {{ $cardSection }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('In progress') }}</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ __('Timer paused') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ __('Open the assessment and tap Resume to continue.') }}</p>
                        <p class="mt-1 truncate text-sm font-medium text-slate-800">{{ $sessionExam->title }}</p>
                    </div>
                    <a href="{{ route('student.exam.take', $activeSession) }}" class="qs-btn-primary inline-flex min-h-[40px] shrink-0 items-center justify-center rounded-lg px-4 text-sm font-semibold">
                        {{ __('Resume') }}
                    </a>
                </div>
            </div>
        @endif

        @if (! $classYearOk)
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ __('Class year') }}</p>
                <p class="mt-1 text-xs leading-relaxed">{{ __('Your class may not match the active year. Ask your coordinator if lists look empty.') }}</p>
            </div>
        @endif

        @if ($user->class_id === null)
            <div class="mb-4 rounded-xl border border-slate-200/90 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <p>
                    <i class="fa-solid fa-circle-info me-1.5 text-slate-500" aria-hidden="true"></i>
                    {{ __('student_ui.class_group_not_assigned') }}
                </p>
            </div>
        @endif

        <div class="qs-std-profile-bleed qs-std-hero-bleed">
            @include('student.partials.dashboard-mobile-profile-card', [
                'user' => $user,
                'lastName' => $lastName,
                'initials' => $initials,
                'mobileProfileSubline' => $mobileProfileSubline,
                'semesterLabel' => $semesterLabel,
            ])
        </div>

        @include('student.partials.dashboard-header', [
            'lastName' => $lastName,
            'groupLabel' => $groupLabel,
            'levelLabel' => $levelLabel,
            'semesterLabel' => $semesterLabel,
        ])

        @if ($nextAction)
            @include('student.partials.dashboard-mobile-next-action', ['nextAction' => $nextAction])
        @endif

        @php
            // Pick the soonest upcoming/open item that has a real ISO target
            // so the desktop hero countdown card can drive its timer from the
            // same digest data that powers the mobile wallet + feed rows.
            $heroCountdownItem = null;
            foreach ($newList as $candidate) {
                if (! empty($candidate['countdown_ends_at'])) {
                    $heroCountdownItem = $candidate;
                    break;
                }
            }

            // When NO countdown is pending but there IS a startable quiz,
            // surface THAT item in the hero card with a Start button — the
            // desktop ticket stays actionable just like the mobile hero.
            $heroStartableItem = null;
            if ($heroCountdownItem === null) {
                foreach ($newList as $candidate) {
                    $cta = trim((string) ($candidate['cta_label'] ?? ''));
                    if ($cta === '') {
                        continue;
                    }
                    if (stripos($cta, 'instruction') !== false) {
                        continue;
                    }
                    $heroStartableItem = $candidate;
                    break;
                }
            }
        @endphp

        <section class="qs-stat-grid qs-stat-grid--dash mb-6 lg:mb-8" aria-labelledby="dash-stats-heading">
            <h2 id="dash-stats-heading" class="sr-only">{{ __('Overview') }}</h2>
            @include('student.partials.dashboard-stat-card', [
                'label' => __('Open assessments'),
                'value' => number_format($statOpen),
                'icon' => 'fa-clipboard-list',
                'tone' => 'assessments',
                'href' => route('student.work.index'),
                'linkLabel' => __('View worklist'),
            ])
            @include('student.partials.dashboard-stat-card', [
                'label' => __('Assignments due'),
                'value' => number_format($statDue),
                'icon' => 'fa-file-pen',
                'tone' => 'assignments',
                'href' => route('student.assignments.index'),
                'linkLabel' => __('View assignments'),
            ])
            @include('student.partials.dashboard-stat-card', [
                'label' => __('Pending results'),
                'value' => number_format($statPending),
                'icon' => 'fa-chart-simple',
                'tone' => 'results',
                'href' => route('student.results.index'),
                'linkLabel' => __('View results'),
            ])
            @include('student.partials.dashboard-stat-card', [
                'label' => __('New notices'),
                'value' => number_format($statNotices),
                'icon' => 'fa-bell',
                'tone' => 'notices',
                'href' => route('student.notifications.index'),
                'linkLabel' => __('View all'),
            ])
        </section>

        {{-- The hero countdown sits BELOW the stat cards on desktop so the
             metrics are the first thing the student reads. The card is
             responsive but the include itself targets desktop (hidden on
             <lg via its own CSS). --}}
        @include('student.partials.dashboard-desktop-countdown', [
            'countdownItem' => $heroCountdownItem,
            'startableItem' => $heroStartableItem,
            'activeSession' => $activeSession,
        ])

        {{-- "Open & new assessments" feed: kept for the classic mobile
             experience (when the wallet is off, this is the only worklist
             surface students get on phones). On desktop it is intentionally
             hidden — the new countdown card + stat cards above already
             surface the next item, and the dedicated /work page owns the
             full worklist. --}}
        <section class="mb-6 {{ $feedPanel }} lg:hidden" aria-labelledby="dash-new-heading">
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <h2 id="dash-new-heading" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Open & new assessments') }}</h2>
                    <a href="{{ route('student.work.index') }}" class="shrink-0 text-sm font-medium text-sky-700 underline-offset-2 hover:underline">{{ __('Worklist') }}</a>
                </div>
                <div class="qs-std-dash-panel__body">
                    @if ($newList === [])
                        <p class="flex flex-1 items-center justify-center py-6 text-center text-sm text-slate-600">{{ __('No new assessments right now.') }}</p>
                    @else
                        <ul class="qs-std-feed-grid mt-3">
                            @foreach ($newList as $qa)
                                @php
                                    $qaTypeLabel = (string) ($qa['type_label'] ?? '');
                                    $qaTypeKey = strtolower($qaTypeLabel);
                                    [$qaIcon, $qaTone] = match (true) {
                                        str_contains($qaTypeKey, 'assignment') => ['fa-file-pen', 'assignments'],
                                        str_contains($qaTypeKey, 'mid') => ['fa-flask', 'results'],
                                        str_contains($qaTypeKey, 'exam') => ['fa-shield-halved', 'assessments'],
                                        str_contains($qaTypeKey, 'quiz') => ['fa-clipboard-question', 'assessments'],
                                        default => ['fa-clipboard-list', 'assessments'],
                                    };
                                @endphp
                                @include('student.partials.dashboard-feed-row', [
                                    'href' => $qa['href'],
                                    'title' => $qa['title'],
                                    'subtitle' => ($qa['course_line'] ?? '') !== '' ? $qa['course_line'] : null,
                                    'meta' => $qa['published_at'] ?? null,
                                    'action' => $qa['cta_label'] ?? null,
                                    'countdownEndsAt' => $qa['countdown_ends_at'] ?? null,
                                    'countdownPrefix' => $qa['countdown_prefix'] ?? null,
                                    'countdownExpiredCta' => $qa['countdown_expired_cta'] ?? null,
                                    'countdownExpiredState' => $qa['countdown_expired_state'] ?? null,
                                    'icon' => $qaIcon,
                                    'tone' => $qaTone,
                                    'tag' => $qaTypeLabel !== '' ? $qaTypeLabel : null,
                                ])
                            @endforeach
                        </ul>
                    @endif
                </div>
        </section>

        @if ($dashboardCourseNewMaterials !== [])
            <section class="mb-6 {{ $cardSection }}" aria-label="{{ __('New course materials') }}">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('New course materials') }}</h2>
                <ul class="qs-std-feed-grid mt-3">
                    @foreach (array_slice($dashboardCourseNewMaterials, 0, 3) as $row)
                        @include('student.partials.dashboard-feed-row', [
                            'href' => $materialsHubEnabled ? $materialsHref : null,
                            'title' => trans_choice(':count new file in :course|:count new files in :course', (int) $row['count'], ['count' => (int) $row['count'], 'course' => $row['name']]),
                            'action' => $materialsHubEnabled ? __('Open materials') : null,
                            'icon' => 'fa-folder-open',
                            'tone' => 'materials',
                        ])
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="space-y-4 pb-6">
            @if (is_array($dashboardPolicyNotice) && ($dashboardPolicyNotice['message'] ?? '') !== '')
                <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">{{ __('Institution notice') }}</p>
                    <p class="mt-2 text-sm leading-relaxed text-amber-950">{{ $dashboardPolicyNotice['message'] }}</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @if (($dashboardPolicyNotice['faq_url'] ?? '') !== '')
                            <a href="{{ $dashboardPolicyNotice['faq_url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-amber-900 underline-offset-2 hover:underline">{{ __('Read FAQ') }}</a>
                        @endif
                        <form method="post" action="{{ route('student.dashboard.policy-notice.dismiss') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-amber-900 underline-offset-2 hover:underline">{{ __('Dismiss') }}</button>
                        </form>
                    </div>
                </section>
            @endif

            @if ($dashboardTip !== '')
                @php
                    $dashboardTipDismissKey = 'qs_student_dash_tip_v1_' . hash('sha256', $dashboardTip . '|' . app()->getLocale());
                @endphp
                <div
                    x-data="{ key: @js($dashboardTipDismissKey), dismissed: false }"
                    x-init="dismissed = (() => { try { return localStorage.getItem(key) === '1'; } catch (e) { return false; } })()"
                    x-show="!dismissed"
                    class="flex items-start gap-3 rounded-xl border border-slate-200/90 bg-slate-50 p-4"
                    role="region"
                    aria-label="{{ __('Tip') }}"
                >
                    <i class="fa-solid fa-lightbulb mt-0.5 text-amber-600" aria-hidden="true"></i>
                    <p class="min-w-0 flex-1 text-sm text-slate-700">{{ $dashboardTip }}</p>
                    <button type="button" class="shrink-0 text-slate-500 hover:text-slate-800" @click="dismissed = true; try { localStorage.setItem(key, '1'); } catch (e) {}" aria-label="{{ __('Dismiss tip') }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            @endif
        </div>
    </div>
</x-layouts.student>
