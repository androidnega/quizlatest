<x-layouts.examiner>
    <x-slot name="title">{{ __('Overview') }}</x-slot>
    <x-slot name="subtitle">{{ __('Manage class groups, assessments, and session results.') }}</x-slot>

    {{-- Academic-year picker intentionally omitted on the examiner
         dashboard: year hierarchy is a coordinator / super-admin concern.
         The controller still defaults to the active year automatically. --}}

    @php
        $integrityTotal = $proctoringFlaggedSessionsCount + $autoSubmittedSessionsCount + $heldResultsCount;

        // Bento-grid design: every card sits on a single solid deep tone
        // (white text on a coloured surface) — no gradients, no left-edge
        // stripes, no white surfaces. Internal density is trimmed so each
        // card stays short, and the icon badge sits in a translucent white
        // chip in the top-right.
        $satelliteBase = 'group relative isolate flex h-full flex-col overflow-hidden rounded-2xl p-3.5 pr-12 text-white shadow-md transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg sm:p-4 sm:pr-14';
        $satelliteIconBadge = 'absolute right-2.5 top-2.5 inline-flex h-8 w-8 items-center justify-center rounded-xl bg-white/12 text-white ring-1 ring-inset ring-white/20 sm:right-3 sm:top-3';
        $metricLabel = 'text-[10px] font-semibold uppercase tracking-[0.12em] text-white/75';
        $metricValue = 'mt-0.5 text-[1.75rem] font-bold leading-none tracking-tight tabular-nums text-white sm:text-[1.9rem]';
        $metricFootLink = 'mt-auto pt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-white/90 underline-offset-2 hover:text-white hover:underline';

        // Hero math: percent breakdown for the inline draft/published bar.
        $heroTotal = max(0, (int) $quizTotalCount);
        $heroDraft = max(0, (int) $draftAssessmentsCount);
        $heroPublished = max(0, (int) $publishedAssessmentsCount);
        $heroOther = max(0, $heroTotal - $heroDraft - $heroPublished); // archived / other states
        $heroPublishedPct = $heroTotal > 0 ? (int) round(($heroPublished / $heroTotal) * 100) : 0;
        $heroDraftPct = $heroTotal > 0 ? (int) round(($heroDraft / $heroTotal) * 100) : 0;
        $heroOtherPct = max(0, 100 - $heroPublishedPct - $heroDraftPct);
    @endphp

    <div class="space-y-6">
        <section aria-labelledby="examiner-overview-metrics">
            <h2 id="examiner-overview-metrics" class="sr-only">{{ __('Overview metrics') }}</h2>

            {{-- Bento grid:
                 xl+ : hero (2 cols) + 2 satellites on row 1, sat3 spans full
                       width on row 2 — keeps the hero short instead of
                       stretching it to match a 2-row satellite stack.
                 md  : hero col-span-2, satellites 2 per row underneath
                 sm  : everything stacks. --}}
            <div class="grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-4">
                {{-- HERO — Assessments.
                     Single solid deep-teal card. No accent blobs, no
                     watermark numeral — one flat color so the card reads
                     as a single tone with maximum calm. Internal density
                     trimmed so the card sits short. --}}
                <article class="group relative isolate flex flex-col overflow-hidden rounded-3xl bg-[#1f6a78] p-5 text-white shadow-md shadow-[#0d3f49]/30 transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:shadow-[#0d3f49]/40 sm:p-5 md:col-span-2 xl:col-span-2">
                    {{-- TOP: eyebrow + small icon chip --}}
                    <div class="relative flex items-center justify-between gap-4">
                        <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-white/85">
                            <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-white/90"></span>
                            {{ __('Assessments') }}
                        </p>
                        <span aria-hidden="true" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/12 text-white ring-1 ring-inset ring-white/20">
                            <i class="fa-solid fa-file-lines text-[12px]"></i>
                        </span>
                    </div>

                    {{-- HEADLINE: number + live chip + descriptor on a single
                         row so the card stays compact vertically. --}}
                    <div class="relative mt-3">
                        <p class="flex flex-wrap items-baseline gap-x-3 gap-y-2">
                            <span class="text-[2.75rem] font-bold leading-none tabular-nums text-white sm:text-[3rem]">{{ $quizTotalCount }}</span>
                            @if ($publishedAssessmentsCount > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/12 px-2.5 py-1 text-[11px] font-semibold text-white ring-1 ring-inset ring-white/20">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white"></span>
                                    </span>
                                    {{ $publishedAssessmentsCount }} {{ __('live') }}
                                </span>
                            @endif
                        </p>
                        <p class="mt-1.5 max-w-md text-[13px] leading-snug text-white/80">{{ __('Everything you own across your courses.') }}</p>
                    </div>

                    {{-- BOTTOM: divider + mix row + footer link. --}}
                    <div class="relative mt-4 pt-4">
                        <div class="border-t border-white/15"></div>
                        @if ($heroTotal > 0)
                            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2.5">
                                {{-- Mix bar with inline label --}}
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-white/60">{{ __('Mix') }}</span>
                                    <div class="flex h-1.5 min-w-[6rem] flex-1 overflow-hidden rounded-full bg-white/12" role="img" aria-label="{{ __('Draft and published mix') }}">
                                        @if ($heroPublishedPct > 0)
                                            <span class="block h-full bg-white" style="width: {{ $heroPublishedPct }}%"></span>
                                        @endif
                                        @if ($heroDraftPct > 0)
                                            <span class="block h-full bg-white/55" style="width: {{ $heroDraftPct }}%"></span>
                                        @endif
                                        @if ($heroOtherPct > 0)
                                            <span class="block h-full bg-white/25" style="width: {{ $heroOtherPct }}%"></span>
                                        @endif
                                    </div>
                                </div>
                                {{-- Inline stat pills, separated by tiny dots --}}
                                <ul class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                                    <li class="inline-flex items-center gap-2 text-white">
                                        <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                        <span class="font-semibold uppercase tracking-wider text-white/75">{{ __('Published') }}</span>
                                        <span class="text-sm font-bold tabular-nums">{{ $heroPublished }}</span>
                                    </li>
                                    <li class="inline-flex items-center gap-2 text-white">
                                        <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-white/55"></span>
                                        <span class="font-semibold uppercase tracking-wider text-white/75">{{ __('Draft') }}</span>
                                        <span class="text-sm font-bold tabular-nums">{{ $heroDraft }}</span>
                                    </li>
                                    @if ($heroOther > 0)
                                        <li class="inline-flex items-center gap-2 text-white">
                                            <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-white/25"></span>
                                            <span class="font-semibold uppercase tracking-wider text-white/75">{{ __('Archived') }}</span>
                                            <span class="text-sm font-bold tabular-nums">{{ $heroOther }}</span>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        @else
                            <p class="mt-3 max-w-md text-xs leading-relaxed text-white/85">
                                {{ __('You have no assessments yet. Create one to start collecting submissions.') }}
                            </p>
                        @endif

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <a href="{{ route('examiner.exams.index', $dashboardProctoringQueryBase) }}"
                               class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-[#1f6a78] shadow-sm transition duration-150 ease-out hover:-translate-y-0.5 hover:bg-white/95 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 focus:ring-offset-[#1f6a78]">
                                {{ __('View all') }}
                                <i class="fa-solid fa-arrow-right text-[11px] transition group-hover:translate-x-0.5"></i>
                            </a>
                            <span class="hidden text-[11px] uppercase tracking-[0.16em] text-white/55 sm:inline">{{ __('Across all courses') }}</span>
                        </div>
                    </div>
                </article>

                {{-- SATELLITE 1 — Open now.
                     Solid deep-emerald tone. Label + value sit adjacent in
                     DOM; icon badge floats top-right so accessibility
                     readers (and the inflated-grading regression tests) see
                     the label paired directly with its number. --}}
                <article class="{{ $satelliteBase }} bg-[#0c6b3b] shadow-emerald-950/25 hover:shadow-emerald-950/35">
                    <p class="{{ $metricLabel }}">{{ __('Open now') }}</p>
                    <p class="{{ $metricValue }}">{{ $activeAssessmentsCount }}</p>
                    <span aria-hidden="true" class="{{ $satelliteIconBadge }}">
                        <i class="fa-solid fa-door-open text-[12px]"></i>
                    </span>
                    @if ($activeAssessmentsCount > 0)
                        <p class="mt-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/12 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white ring-1 ring-inset ring-white/20">
                                <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white"></span>
                                </span>
                                {{ __('Live now') }}
                            </span>
                        </p>
                    @else
                        <p class="mt-2 text-[11px] leading-snug text-white/75">{{ __('Nothing inside its window right now.') }}</p>
                    @endif
                    <p class="mt-2 text-[11px] leading-snug text-white/75">{{ __('Students can start or submit during the schedule window.') }}</p>
                </article>

                {{-- SATELLITE 2 — Submissions. Solid deep-indigo tone. --}}
                <article class="{{ $satelliteBase }} bg-[#3730a3] shadow-indigo-950/25 hover:shadow-indigo-950/35">
                    <p class="{{ $metricLabel }}">{{ __('Submissions') }}</p>
                    <p class="{{ $metricValue }}">{{ $submittedSessionsCount }}</p>
                    <span aria-hidden="true" class="{{ $satelliteIconBadge }}">
                        <i class="fa-solid fa-paper-plane text-[12px]"></i>
                    </span>
                    <p class="mt-2 text-[11px] leading-snug text-white/75">{{ __('Total student attempts that finished.') }}</p>
                    <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['tab' => 'active'])) }}"
                       class="{{ $metricFootLink }}">
                        {{ __('Browse active') }}
                        <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                    </a>
                </article>

                {{-- SATELLITE 3 — Needs grading. Solid deep-amber tone, with
                     a held-for-review chip on the front. Spans the full row
                     on xl now that the hero only takes a single row. --}}
                <article class="{{ $satelliteBase }} bg-[#92400e] shadow-amber-950/25 hover:shadow-amber-950/35 md:col-span-2 xl:col-span-4">
                    <p class="{{ $metricLabel }}">{{ __('Needs grading') }}</p>
                    <div class="mt-0.5 flex flex-wrap items-baseline gap-3">
                        <p class="text-[1.75rem] font-bold leading-none tracking-tight tabular-nums text-white sm:text-[1.9rem]">{{ $pendingManualGradingCount }}</p>
                        @if ($heldResultsCount > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/12 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white ring-1 ring-inset ring-white/20">
                                <i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                                {{ __(':count held for review', ['count' => $heldResultsCount]) }}
                            </span>
                        @endif
                    </div>
                    <span aria-hidden="true" class="{{ $satelliteIconBadge }}">
                        <i class="fa-solid fa-list-check text-[12px]"></i>
                    </span>
                    <p class="mt-2 text-[11px] leading-snug text-white/75">
                        @if ($pendingManualGradingCount > 0)
                            {{ __('Distinct submissions waiting on essay grading. Tap to open the queue and apply AI suggestions or grade manually.') }}
                        @else
                            {{ __('You are caught up — no submissions are waiting on manual grading.') }}
                        @endif
                    </p>
                    <a href="{{ route('examiner.grading.pending') }}" class="{{ $metricFootLink }}">
                        {{ __('Open grading queue') }}
                        <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                    </a>
                </article>
            </div>
        </section>

        {{-- Integrity & review: dark mini-cards on a row when there is data.
             Each card has its own neon accent stripe at the top — mirrors
             the dark hero so the integrity row feels like the same family. --}}
        @if ($integrityTotal > 0)
            <section aria-labelledby="examiner-integrity-heading">
                <div class="mb-2.5 flex items-center justify-between gap-3">
                    <h2 id="examiner-integrity-heading" class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">
                        {{ __('Integrity & review') }}
                    </h2>
                    <span class="text-[11px] text-slate-500">{{ __('Open the assessment list to filter further.') }}</span>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $integrityCardBase = 'group relative flex flex-col overflow-hidden rounded-2xl border border-slate-800/70 bg-slate-950 p-4 text-slate-100 shadow-lg shadow-slate-900/10 transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-xl';
                    @endphp
                    @if ($proctoringFlaggedSessionsCount > 0)
                        <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['proctoring_focus' => 'flagged'])) }}" class="{{ $integrityCardBase }}">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-rose-400 via-rose-500 to-pink-500"></span>
                            <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-rose-500/20 blur-3xl"></span>
                            <div class="relative flex items-start justify-between gap-3">
                                <span aria-hidden="true" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/5 text-rose-300 ring-1 ring-inset ring-white/10">
                                    <i class="fa-solid fa-flag"></i>
                                </span>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.1em] text-rose-300/80">{{ __('Flagged') }}</span>
                            </div>
                            <p class="mt-3 text-3xl font-bold tabular-nums text-white">{{ $proctoringFlaggedSessionsCount }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Sessions') }}</p>
                            <span class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-rose-300">
                                {{ __('Review flags') }}
                                <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                            </span>
                        </a>
                    @endif
                    @if ($autoSubmittedSessionsCount > 0)
                        <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['proctoring_focus' => 'auto_submitted'])) }}" class="{{ $integrityCardBase }}">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-amber-400 via-orange-400 to-yellow-400"></span>
                            <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-amber-500/20 blur-3xl"></span>
                            <div class="relative flex items-start justify-between gap-3">
                                <span aria-hidden="true" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/5 text-amber-300 ring-1 ring-inset ring-white/10">
                                    <i class="fa-solid fa-robot"></i>
                                </span>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.1em] text-amber-300/80">{{ __('Auto-submitted') }}</span>
                            </div>
                            <p class="mt-3 text-3xl font-bold tabular-nums text-white">{{ $autoSubmittedSessionsCount }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Sessions') }}</p>
                            <span class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-300">
                                {{ __('See sessions') }}
                                <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                            </span>
                        </a>
                    @endif
                    @if ($heldResultsCount > 0)
                        <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['proctoring_focus' => 'held_results'])) }}" class="{{ $integrityCardBase }}">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-violet-400 via-fuchsia-400 to-purple-500"></span>
                            <span aria-hidden="true" class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-violet-500/20 blur-3xl"></span>
                            <div class="relative flex items-start justify-between gap-3">
                                <span aria-hidden="true" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/5 text-violet-300 ring-1 ring-inset ring-white/10">
                                    <i class="fa-solid fa-pause-circle"></i>
                                </span>
                                <span class="text-[11px] font-semibold uppercase tracking-[0.1em] text-violet-300/80">{{ __('Held for review') }}</span>
                            </div>
                            <p class="mt-3 text-3xl font-bold tabular-nums text-white">{{ $heldResultsCount }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Results') }}</p>
                            <span class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-violet-300">
                                {{ __('Decide outcome') }}
                                <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                            </span>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        {{-- Quick actions — modern row with a gradient primary CTA (animated
             sparkle hint) and tinted secondary chips. Each chip uses the
             same accent palette as its matching satellite card above and
             separates label from count with a thin divider for a refined,
             Linear-style feel. --}}
        <section class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-slate-50/70 p-4 shadow-sm sm:p-5" aria-labelledby="examiner-quick-actions-heading">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="examiner-quick-actions-heading" class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">{{ __('Quick actions') }}</h2>
                <span class="hidden text-[11px] text-slate-500 sm:inline">{{ __('Jump straight to the most-used surfaces.') }}</span>
            </div>
            @php
                // Each chip pulls its full color set from this map so the
                // markup stays compact and palettes never drift between the
                // satellite cards above and the quick action chips below.
                $chipPalettes = [
                    'cyan'    => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-cyan-200/70 bg-gradient-to-br from-cyan-50/80 via-white to-white px-4 text-sm font-semibold text-cyan-900 shadow-[0_1px_0_rgba(255,255,255,0.6)_inset] transition duration-200 ease-out hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md hover:shadow-cyan-200/30 focus:outline-none focus:ring-2 focus:ring-cyan-400/30 focus:ring-offset-2',
                    'emerald' => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-emerald-200/70 bg-gradient-to-br from-emerald-50/80 via-white to-white px-4 text-sm font-semibold text-emerald-900 shadow-[0_1px_0_rgba(255,255,255,0.6)_inset] transition duration-200 ease-out hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-200/30 focus:outline-none focus:ring-2 focus:ring-emerald-400/30 focus:ring-offset-2',
                    'violet'  => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-violet-200/70 bg-gradient-to-br from-violet-50/80 via-white to-white px-4 text-sm font-semibold text-violet-900 shadow-[0_1px_0_rgba(255,255,255,0.6)_inset] transition duration-200 ease-out hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md hover:shadow-violet-200/30 focus:outline-none focus:ring-2 focus:ring-violet-400/30 focus:ring-offset-2',
                    'amber'   => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-amber-200/70 bg-gradient-to-br from-amber-50/80 via-white to-white px-4 text-sm font-semibold text-amber-900 shadow-[0_1px_0_rgba(255,255,255,0.6)_inset] transition duration-200 ease-out hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md hover:shadow-amber-200/30 focus:outline-none focus:ring-2 focus:ring-amber-400/30 focus:ring-offset-2',
                ];
                $chipIcon = [
                    'cyan'    => 'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-cyan-600 ring-1 ring-inset ring-cyan-200/80 shadow-sm transition group-hover:scale-105',
                    'emerald' => 'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-600 ring-1 ring-inset ring-emerald-200/80 shadow-sm transition group-hover:scale-105',
                    'violet'  => 'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-violet-600 ring-1 ring-inset ring-violet-200/80 shadow-sm transition group-hover:scale-105',
                    'amber'   => 'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-amber-700 ring-1 ring-inset ring-amber-200/80 shadow-sm transition group-hover:scale-105',
                ];
                $chipDivider = [
                    'cyan'    => 'h-4 w-px shrink-0 bg-cyan-200/80',
                    'emerald' => 'h-4 w-px shrink-0 bg-emerald-200/80',
                    'violet'  => 'h-4 w-px shrink-0 bg-violet-200/80',
                    'amber'   => 'h-4 w-px shrink-0 bg-amber-200/80',
                ];
                $chipCount = [
                    'cyan'    => 'shrink-0 text-sm font-bold tabular-nums text-cyan-700',
                    'emerald' => 'shrink-0 text-sm font-bold tabular-nums text-emerald-700',
                    'violet'  => 'shrink-0 text-sm font-bold tabular-nums text-violet-700',
                    'amber'   => 'shrink-0 text-sm font-bold tabular-nums text-amber-700',
                ];
            @endphp
            <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                {{-- PRIMARY — gradient cyan→teal→emerald with a soft sparkle
                     that pulses gently. Sits above the chips visually thanks
                     to the colored shadow halo. --}}
                <a href="{{ route('examiner.exams.create') }}"
                   class="group relative inline-flex min-h-[44px] items-center gap-2.5 overflow-hidden rounded-full bg-gradient-to-r from-cyan-500 via-teal-500 to-emerald-500 px-5 text-sm font-semibold text-white shadow-lg shadow-teal-500/25 transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-xl hover:shadow-teal-500/40 focus:outline-none focus:ring-2 focus:ring-teal-400/50 focus:ring-offset-2">
                    {{-- Soft moving highlight on hover --}}
                    <span aria-hidden="true" class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-700 group-hover:translate-x-full"></span>
                    <span class="relative inline-flex h-7 w-7 items-center justify-center rounded-xl bg-white/15 ring-1 ring-inset ring-white/30 backdrop-blur-sm transition group-hover:rotate-90">
                        <i class="fa-solid fa-plus text-[12px]" aria-hidden="true"></i>
                    </span>
                    <span class="relative min-w-0 truncate">{{ __('Create assessment') }}</span>
                    <span aria-hidden="true" class="relative -ml-0.5 inline-flex h-1.5 w-1.5 animate-pulse rounded-full bg-white/80"></span>
                </a>

                <a href="{{ route('examiner.exams.index') }}" class="{{ $chipPalettes['cyan'] }}">
                    <span class="{{ $chipIcon['cyan'] }}" aria-hidden="true">
                        <i class="fa-solid fa-file-lines text-[12px]"></i>
                    </span>
                    <span class="min-w-0 truncate">{{ __('All assessments') }}</span>
                    <span class="{{ $chipDivider['cyan'] }}" aria-hidden="true"></span>
                    <span class="{{ $chipCount['cyan'] }}">{{ $quizTotalCount }}</span>
                </a>

                <a href="{{ route('examiner.courses.index') }}" class="{{ $chipPalettes['emerald'] }}">
                    <span class="{{ $chipIcon['emerald'] }}" aria-hidden="true">
                        <i class="fa-solid fa-book text-[12px]"></i>
                    </span>
                    <span class="min-w-0 truncate">{{ __('Courses') }}</span>
                    <span class="{{ $chipDivider['emerald'] }}" aria-hidden="true"></span>
                    <span class="{{ $chipCount['emerald'] }}">{{ $assignedCoursesCount }}</span>
                </a>

                <a href="{{ route('examiner.teaching-classes.index') }}" class="{{ $chipPalettes['violet'] }}">
                    <span class="{{ $chipIcon['violet'] }}" aria-hidden="true">
                        <i class="fa-solid fa-user-group text-[12px]"></i>
                    </span>
                    <span class="min-w-0 truncate">{{ __('Classes') }}</span>
                    <span class="{{ $chipDivider['violet'] }}" aria-hidden="true"></span>
                    <span class="{{ $chipCount['violet'] }}">{{ $classesInScopeCount }}</span>
                </a>

                <a href="{{ route('examiner.grading.pending') }}" class="{{ $chipPalettes['amber'] }}">
                    <span class="{{ $chipIcon['amber'] }} @if ($pendingManualGradingCount > 0) relative @endif" aria-hidden="true">
                        <i class="fa-solid fa-clipboard-check text-[12px]"></i>
                        @if ($pendingManualGradingCount > 0)
                            {{-- Tiny pulsing dot to signal pending work --}}
                            <span class="absolute -right-0.5 -top-0.5 inline-flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500 ring-2 ring-white"></span>
                            </span>
                        @endif
                    </span>
                    <span class="min-w-0 truncate">{{ __('Grading') }}</span>
                    <span class="{{ $chipDivider['amber'] }}" aria-hidden="true"></span>
                    <span class="{{ $chipCount['amber'] }}">{{ $pendingManualGradingCount }}</span>
                </a>
            </div>
        </section>
    </div>
</x-layouts.examiner>
