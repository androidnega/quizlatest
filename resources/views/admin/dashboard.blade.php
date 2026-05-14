<x-layouts.admin>
    <x-slot name="title">{{ __('Admin overview') }}</x-slot>
    <x-slot name="subtitle">{{ __('Live metrics, infrastructure checks, and shortcuts across your QuizSnap deployment.') }}</x-slot>

    @php
        $viteOk = $viteBuildPresent && $viteBuildDirPresent;
        $sc = $sessionStatusCounts;
        $sessActive = (int) ($sc['active'] ?? 0);
        $sessPaused = (int) ($sc['paused'] ?? 0);
        $sessSubmitted = (int) ($sc['submitted'] ?? 0);
        $sessFlagged = (int) ($sc['flagged'] ?? 0);
        $util = min(100, max(0, (float) $examUtilizationPercent));
    @endphp

    <div class="qs-refdash">
        <div class="qs-refdash-shell">
            <div class="mb-4 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.system-reporting.index') }}" class="text-sm font-semibold text-slate-700 underline-offset-2 hover:underline">{{ __('System reporting') }}</a>
                <a href="{{ request()->fullUrl() }}" class="qs-refdash-refresh" title="{{ __('Reload this page') }}">
                    <i class="fa-solid fa-rotate-right text-[#6b7280]" aria-hidden="true"></i>
                    {{ __('Refresh') }}
                </a>
            </div>

            <section aria-labelledby="admin-kpi-heading" class="mb-5">
                <h2 id="admin-kpi-heading" class="sr-only">{{ __('Key metrics') }}</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="qs-refdash-kpi qs-refdash-kpi--universities">
                        <div class="min-w-0 flex-1">
                            <p class="qs-refdash-kpi__label">{{ __('Universities') }}</p>
                            <p class="qs-refdash-kpi__value">{{ number_format($universityCount) }}</p>
                        </div>
                        <div class="qs-refdash-kpi__icon" aria-hidden="true">
                            <i class="fa-solid fa-building-columns"></i>
                        </div>
                    </article>
                    <article class="qs-refdash-kpi qs-refdash-kpi--coordinators">
                        <div class="min-w-0 flex-1">
                            <p class="qs-refdash-kpi__label">{{ __('Coordinators') }}</p>
                            <p class="qs-refdash-kpi__value">{{ number_format($coordinatorCount) }}</p>
                        </div>
                        <div class="qs-refdash-kpi__icon" aria-hidden="true">
                            <i class="fa-solid fa-user-group"></i>
                        </div>
                    </article>
                    <article class="qs-refdash-kpi qs-refdash-kpi--students">
                        <div class="min-w-0 flex-1">
                            <p class="qs-refdash-kpi__label">{{ __('Students') }}</p>
                            <p class="qs-refdash-kpi__value">{{ number_format($studentCount) }}</p>
                        </div>
                        <div class="qs-refdash-kpi__icon" aria-hidden="true">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                    </article>
                    <article class="qs-refdash-kpi qs-refdash-kpi--exams">
                        <div class="min-w-0 flex-1">
                            <p class="qs-refdash-kpi__label">{{ __('Published exams') }}</p>
                            <p class="qs-refdash-kpi__value">{{ number_format($publishedExamCount) }}</p>
                        </div>
                        <div class="qs-refdash-kpi__icon" aria-hidden="true">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                    </article>
                </div>
            </section>

            @if (auth()->user()?->isSuperAdmin())
                @isset($userManagementOverview)
                    <section class="qs-refdash-card mb-5" aria-labelledby="admin-user-mgmt-heading">
                        <div class="qs-refdash-card__head">
                            <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
                            <h2 id="admin-user-mgmt-heading" class="text-sm font-bold">{{ __('Manage users') }}</h2>
                        </div>
                        <div class="qs-refdash-card__body">
                            <p class="mb-4 flex gap-2 text-xs leading-relaxed text-[#6b7280]">
                                <i class="fa-solid fa-address-book mt-0.5 shrink-0 text-teal-600" aria-hidden="true"></i>
                                <span>{{ __('Open the staff directory to search, edit, activate or deactivate, and reset passwords for admins, coordinators, and examiners. Student accounts stay with coordinators.') }}</span>
                            </p>
                            <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div class="flex flex-col items-center rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-3 py-2.5 text-center">
                                    <span class="mb-1 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-500/10 text-slate-700" aria-hidden="true">
                                        <i class="fa-solid fa-users text-sm"></i>
                                    </span>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-[#6b7280]">{{ __('Staff total') }}</p>
                                    <p class="mt-0.5 text-lg font-bold tabular-nums text-black">{{ number_format($userManagementOverview['staff_total']) }}</p>
                                </div>
                                <div class="flex flex-col items-center rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-3 py-2.5 text-center">
                                    <span class="mb-1 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700" aria-hidden="true">
                                        <i class="fa-solid fa-user-shield text-sm"></i>
                                    </span>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-[#6b7280]">{{ __('Admins') }}</p>
                                    <p class="mt-0.5 text-lg font-bold tabular-nums text-black">{{ number_format($userManagementOverview['admin']) }}</p>
                                </div>
                                <div class="flex flex-col items-center rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-3 py-2.5 text-center">
                                    <span class="mb-1 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-teal-500/10 text-teal-700" aria-hidden="true">
                                        <i class="fa-solid fa-user-tie text-sm"></i>
                                    </span>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-[#6b7280]">{{ __('Coordinators') }}</p>
                                    <p class="mt-0.5 text-lg font-bold tabular-nums text-black">{{ number_format($userManagementOverview['coordinator']) }}</p>
                                </div>
                                <div class="flex flex-col items-center rounded-lg border border-[#e5e7eb] bg-[#f9fafb] px-3 py-2.5 text-center">
                                    <span class="mb-1 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-700" aria-hidden="true">
                                        <i class="fa-solid fa-clipboard-check text-sm"></i>
                                    </span>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-[#6b7280]">{{ __('Examiners') }}</p>
                                    <p class="mt-0.5 text-lg font-bold tabular-nums text-black">{{ number_format($userManagementOverview['examiner']) }}</p>
                                </div>
                            </div>
                            <p class="mb-4 flex items-start gap-2 text-[11px] text-[#6b7280]">
                                <i class="fa-solid fa-graduation-cap mt-0.5 shrink-0 text-blue-600" aria-hidden="true"></i>
                                <span>{{ __('Students in the system: :count (not listed in this directory).', ['count' => number_format($userManagementOverview['student'])]) }}</span>
                            </p>
                            <div class="flex flex-wrap items-center gap-3">
                                <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-[#10b981] bg-[#10b981] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">
                                    <i class="fa-solid fa-folder-open mr-2 text-sm" aria-hidden="true"></i>
                                    {{ __('Open staff directory') }}
                                    <i class="fa-solid fa-arrow-right ml-2 text-xs" aria-hidden="true"></i>
                                </a>
                                <span class="flex items-center gap-1.5 text-[11px] text-[#6b7280]">
                                    <i class="fa-solid fa-magnifying-glass shrink-0 text-[10px]" aria-hidden="true"></i>
                                    {{ __('Filter by role; search by name or email.') }}
                                </span>
                            </div>
                        </div>
                    </section>
                @endisset
            @endif

            <div class="grid gap-4 lg:grid-cols-3">
                <article class="qs-refdash-card">
                    <div class="qs-refdash-card__head">
                        <i class="fa-solid fa-stopwatch" aria-hidden="true"></i>
                        <h3>{{ __('Exam time on record') }}</h3>
                    </div>
                    <div class="qs-refdash-card__body">
                        <p class="qs-refdash-muted-foot -mt-1 mb-2">{{ __('Sum of finished session durations from stored start/end times.') }}</p>
                        <div class="qs-refdash-row">
                            <span class="qs-refdash-row__label">{{ __('This week') }}</span>
                            <span class="qs-refdash-row__value">{{ number_format($sessionsThisWeekHours) }} {{ __('h') }}</span>
                        </div>
                        <div class="qs-refdash-row">
                            <span class="qs-refdash-row__label">{{ __('All time') }}</span>
                            <span class="qs-refdash-row__value">{{ number_format($totalSessionHours) }} {{ __('h') }}</span>
                        </div>
                        <div class="qs-refdash-row">
                            <span class="qs-refdash-row__label">{{ __('Held results (review queue)') }}</span>
                            <span><span class="qs-refdash-badge">{{ number_format($pendingHeldReviews) }}</span></span>
                        </div>
                        <a href="#admin-recent-activity" class="qs-refdash-link">
                            {{ __('View platform activity') }}
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>

                <article class="qs-refdash-card">
                    <div class="qs-refdash-card__head">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <h3>{{ __('Quiz catalogue') }}</h3>
                    </div>
                    <div class="qs-refdash-card__body">
                        <p class="qs-refdash-muted-foot -mt-1 mb-2">{{ __('Published share of all quiz records in the database.') }}</p>
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="qs-refdash-row__label">{{ __('Published share') }}</span>
                                <span class="qs-refdash-row__value">{{ $util }} %</span>
                            </div>
                            <div class="qs-refdash-bar" role="progressbar" aria-valuenow="{{ (int) round($util) }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="qs-refdash-bar__fill" style="width: {{ $util }}%"></div>
                            </div>
                        </div>
                        <div class="qs-refdash-row">
                            <span class="qs-refdash-row__label">{{ __('Published') }}</span>
                            <span class="qs-refdash-row__value">{{ number_format($publishedExamCount) }}</span>
                        </div>
                        <div class="qs-refdash-row">
                            <span class="qs-refdash-row__label">{{ __('Draft / other') }}</span>
                            <span class="qs-refdash-row__value">{{ number_format($draftExamCount) }}</span>
                        </div>
                        <a href="{{ route('admin.academic-years.index') }}" class="qs-refdash-link">
                            {{ __('Academic years & terms') }}
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>

                <article class="qs-refdash-card">
                    <div class="qs-refdash-card__head">
                        <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                        <h3>{{ __('Exam sessions & grading') }}</h3>
                    </div>
                    <div class="qs-refdash-card__body">
                        <p class="qs-refdash-muted-foot -mt-1 mb-2">{{ __('Live rows from exam_sessions, plus results awaiting manual grading.') }}</p>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[#6b7280]">{{ __('Active') }}</span>
                                <span class="font-bold tabular-nums text-black">{{ number_format($sessActive) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[#6b7280]">{{ __('Paused') }}</span>
                                <span class="font-bold tabular-nums text-black">{{ number_format($sessPaused) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[#6b7280]">{{ __('Submitted') }}</span>
                                <span class="font-bold tabular-nums text-black">{{ number_format($sessSubmitted) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[#6b7280]">{{ __('Flagged') }}</span>
                                <span class="font-bold tabular-nums text-black">{{ number_format($sessFlagged) }}</span>
                            </div>
                        </div>
                        <div class="mt-2 border-t border-[#e5e7eb] pt-2">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="text-[#6b7280]">{{ __('Pending manual grades') }}</span>
                                <span class="font-bold tabular-nums text-black">{{ number_format($pendingManualResultsCount) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                                <span class="text-[#6b7280]">{{ __('Finalized results (graded / released)') }}</span>
                                <span class="font-bold tabular-nums text-black">{{ number_format($gradedOrPublishedResults) }}</span>
                            </div>
                        </div>
                        <a href="{{ route('admin.coordinators.index') }}" class="qs-refdash-link">
                            {{ __('Coordinator directory') }}
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </article>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="qs-health-overview lg:col-span-2">
                <div class="qs-health-overview__head">
                    <i class="fa-solid fa-bullseye text-base" aria-hidden="true"></i>
                    <h3 class="qs-health-overview__title">{{ __('Platform status overview') }}</h3>
                    <span class="qs-health-overview__count-pill">{{ $platformChecksPassed }}/{{ $platformChecksTotal }} {{ __('checks') }}</span>
                </div>
                <p class="qs-health-overview__intro">{{ __('No secrets. This host only.') }}</p>

                <div class="qs-health-overview__grid">
                    <div>
                        <p class="qs-health-overview__col-head">{{ __('Runtime & delivery') }}</p>
                        <div class="qs-health-overview__rows">
                            @php
                                $sessionsTone = $activeSessions['value'] === null ? 'danger' : 'info';
                                $sessionsPill = $activeSessions['value'] !== null ? number_format($activeSessions['value']) : __('N/A');
                                $sessionsDetail = $activeSessions['value'] !== null
                                    ? ($activeSessions['source'] === 'redis' ? __('Counter source: Redis.') : ($activeSessions['source'] === 'database_estimate' ? __('Counter source: database estimate.') : __('Counter source: unavailable.')))
                                    : __('Live session counter could not be read from Redis or the database.');
                            @endphp
                            <div class="qs-health-row qs-health-row--{{ $sessionsTone }} qs-health-row--live" data-health-key="active_sessions">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Active exam sessions') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>{{ $sessionsDetail }}</p>
                                </div>
                                <span class="qs-health-row__pill" data-health-pill>{{ $sessionsPill }}</span>
                            </div>
                            <div class="qs-health-row qs-health-row--{{ $redisUi['tone'] }} qs-health-row--live" data-health-key="redis">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Redis') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>{{ $redisUi['detail'] }}</p>
                                </div>
                                <span class="qs-health-row__pill" data-health-pill>{{ $redisUi['label'] }}</span>
                            </div>
                            <div class="qs-health-row qs-health-row--{{ $liveSocketUi['tone'] }} qs-health-row--live" data-health-key="live_updates">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Live updates') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>{{ $liveSocketUi['detail'] }}</p>
                                </div>
                                <span class="qs-health-row__pill" data-health-pill>{{ $liveSocketUi['label'] }}</span>
                            </div>
                            <div class="qs-health-row qs-health-row--{{ $viteOk ? 'ok' : 'danger' }} qs-health-row--live" data-health-key="vite">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Vite build') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>{{ $viteOk ? __('Production assets manifest is present.') : __('Run npm run build before deploying; manifest or build folder is missing.') }}</p>
                                </div>
                                <span class="qs-health-row__pill" data-health-pill>{{ $viteOk ? __('Ready') : __('Incomplete') }}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="qs-health-overview__col-head">{{ __('Storage & messaging') }}</p>
                        <div class="qs-health-overview__rows">
                            <div class="qs-health-row qs-health-row--{{ $dbConnected ? 'ok' : 'danger' }} qs-health-row--live" data-health-key="database">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Database') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>{{ $dbConnected ? __('Application can run queries on the default connection.') : __('The default database connection failed during this check.') }}</p>
                                </div>
                                <span class="qs-health-row__pill" data-health-pill>{{ $dbConnected ? __('OK') : __('Fail') }}</span>
                            </div>
                            <div class="qs-health-row qs-health-row--{{ $privateWritable ? 'ok' : 'danger' }} qs-health-row--live" data-health-key="private_storage">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Private storage') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>
                                        @if ($privateStorageBytes !== null)
                                            {{ __('Local disk footprint: :size.', ['size' => \Illuminate\Support\Number::fileSize($privateStorageBytes, 1)]) }}
                                        @else
                                            {{ __('Writable private disk used for uploads and evidence.') }}
                                        @endif
                                    </p>
                                </div>
                                <span class="qs-health-row__pill" data-health-pill>{{ $privateWritable ? __('OK') : __('Fail') }}</span>
                            </div>
                            <div class="qs-health-row qs-health-row--{{ $publicStorageBytes !== null ? 'info' : 'muted' }} qs-health-row--live" data-health-key="public_storage">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Public (legacy)') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>{{ __('Legacy public disk usage (optional).') }}</p>
                                </div>
                                <span class="qs-health-row__pill" data-health-pill>
                                    @if ($publicStorageBytes !== null)
                                        {{ \Illuminate\Support\Number::fileSize($publicStorageBytes, 1) }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div class="qs-health-row qs-health-row--muted qs-health-row--live" data-health-key="queue_otp_sms">
                                <span class="qs-health-row__dot" aria-hidden="true"></span>
                                <div class="qs-health-row__main">
                                    <p class="qs-health-row__label">{{ __('Queue · OTP · SMS') }}</p>
                                    <p class="qs-health-row__detail" data-health-detail>
                                        {{ __('OTP') }} {{ $otpEnabled ? __('on') : __('off') }}
                                        · {{ __('SMS') }} {{ $smsEnabled ? __('on') : __('off') }}
                                    </p>
                                </div>
                                <span class="qs-health-row__pill font-mono text-[11px] normal-case" data-health-pill>{{ $queueDriver }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="qs-health-overview__progress">
                    <div class="qs-health-overview__progress-top">
                        <span>{{ __('Core infrastructure score') }}</span>
                        <span class="font-semibold tabular-nums text-black">{{ $platformChecksPercent }}%</span>
                    </div>
                    <div class="qs-health-overview__progress-bar" role="progressbar" aria-valuenow="{{ $platformChecksPercent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="qs-health-overview__progress-fill" style="width: {{ $platformChecksPercent }}%"></div>
                    </div>
                </div>

                <div class="qs-health-overview__foot">
                    <a href="{{ route('admin.settings.index') }}">
                        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                        {{ __('System settings') }}
                    </a>
                    <a href="{{ request()->fullUrl() }}">
                        <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                        {{ __('Reload checks') }}
                    </a>
                </div>
            </div>

            <div class="qs-refdash-card">
                <div class="qs-refdash-card__head qs-refdash-card__head--dense">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    <h3>{{ __('Quick links') }}</h3>
                </div>
                <div class="qs-refdash-card__body qs-refdash-card__body--dense">
                    <div class="qs-refdash-quick">
                        <a href="{{ route('admin.universities.index') }}" class="qs-refdash-quick__a">{{ __('Universities') }} <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.coordinators.index') }}" class="qs-refdash-quick__a">{{ __('Coordinators') }} <span aria-hidden="true">→</span></a>
                        @if (auth()->user()?->isSuperAdmin())
                            <a href="{{ route('admin.users.index') }}" class="qs-refdash-quick__a"><i class="fa-solid fa-users-gear mr-1.5 text-[11px] opacity-80" aria-hidden="true"></i>{{ __('Manage users') }} <span aria-hidden="true">→</span></a>
                        @endif
                        <a href="{{ route('admin.academic-years.index') }}" class="qs-refdash-quick__a">{{ __('Academic years') }} <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.settings.index') }}" class="qs-refdash-quick__a">{{ __('Settings') }} <span aria-hidden="true">→</span></a>
                        <a href="{{ route('admin.academic-reset-snapshots.index') }}" class="qs-refdash-quick__a">{{ __('Reset snapshots') }} <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </div>
        </div>

        <section id="admin-recent-activity" class="qs-refdash-card mt-4" aria-labelledby="admin-activity-heading">
            <div class="qs-refdash-card__head qs-refdash-card__head--dense">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <h2 id="admin-activity-heading" class="text-xs font-bold">{{ __('Recent platform events') }}</h2>
            </div>
            <div class="qs-refdash-card__body qs-refdash-card__body--dense">
                @forelse ($recentActivity as $log)
                    @php
                        $label = \Illuminate\Support\Str::of((string) $log->event_type)->replace('_', ' ')->title()->toString();
                    @endphp
                    <div class="mb-1.5 rounded-md border border-[#e5e7eb] bg-[#f9fafb] px-2.5 py-2 last:mb-0">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-xs font-medium text-black">{{ $label }}</p>
                            <span class="qs-refdash-badge text-[10px]">{{ $log->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-[#6b7280]">
                            @if ($log->user)
                                {{ $log->user->name }}
                            @else
                                {{ __('System') }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="py-4 text-center text-xs text-[#6b7280]">{{ __('No recent events yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.admin>

@push('scripts')
<script>
    (() => {
        const endpoint = @json(route('admin.health-snapshot'));
        const rows = Array.from(document.querySelectorAll('[data-health-key]'));
        if (!rows.length || !endpoint) return;

        const toneClasses = ['qs-health-row--ok', 'qs-health-row--info', 'qs-health-row--warn', 'qs-health-row--muted', 'qs-health-row--danger'];
        const apply = (payload) => {
            rows.forEach((row) => {
                const key = row.getAttribute('data-health-key');
                const item = payload[key];
                if (!item) return;
                row.classList.remove(...toneClasses);
                row.classList.add(`qs-health-row--${item.tone ?? 'muted'}`);
                const detail = row.querySelector('[data-health-detail]');
                const pill = row.querySelector('[data-health-pill]');
                if (detail && typeof item.detail === 'string') detail.textContent = item.detail;
                if (pill && typeof item.label === 'string') pill.textContent = item.label;
                if (pill && typeof item.pill === 'string') pill.textContent = item.pill;
            });
        };

        const tick = async () => {
            try {
                const res = await fetch(endpoint, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                if (!res.ok) return;
                const json = await res.json();
                apply(json);
            } catch (e) {}
        };

        tick();
        setInterval(tick, 15000);
    })();
</script>
@endpush
