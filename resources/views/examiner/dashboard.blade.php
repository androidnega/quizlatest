<x-layouts.examiner>
    <x-slot name="title">{{ __('Overview') }}</x-slot>
    <x-slot name="subtitle">{{ __('Manage class groups, assessments, and session results.') }}</x-slot>

    {{-- Academic-year picker intentionally omitted on the examiner
         dashboard: year hierarchy is a coordinator / super-admin concern.
         The controller still defaults to the active year automatically. --}}

    @php
        $integrityTotal = $proctoringFlaggedSessionsCount + $autoSubmittedSessionsCount + $heldResultsCount;

        // Glance-card design: each card lays a circular progress donut on
        // the left and label + body content on the right. The donut is
        // an SVG with a track + accent arc; the metric value sits inside
        // the ring. Cards keep the soft pastel surface (-50 background,
        // -100 hairline border, darker matching text) from the previous
        // style, hover lifts and slightly saturates the surface, and the
        // ring colour matches each card's tone.
        $cardBase = 'group relative isolate flex h-full flex-col overflow-hidden rounded-2xl border p-3 shadow-sm transition-[transform,box-shadow,background-color,border-color] duration-300 ease-out will-change-transform hover:-translate-y-1 hover:shadow-lg sm:p-3.5';
        $cardFootLink = 'inline-flex items-center gap-1.5 text-[11px] font-semibold underline-offset-2 transition hover:underline';

        // Hero math: percent breakdown for the donut (published share of
        // the library, with a draft secondary arc layered behind).
        $heroTotal = max(0, (int) $quizTotalCount);
        $heroDraft = max(0, (int) $draftAssessmentsCount);
        $heroPublished = max(0, (int) $publishedAssessmentsCount);
        $heroOther = max(0, $heroTotal - $heroDraft - $heroPublished); // archived / other states
        $heroPublishedPct = $heroTotal > 0 ? (int) round(($heroPublished / $heroTotal) * 100) : 0;
        $heroDraftPct = $heroTotal > 0 ? (int) round(($heroDraft / $heroTotal) * 100) : 0;
        $heroOtherPct = max(0, 100 - $heroPublishedPct - $heroDraftPct);

        // Per-satellite ring percentages.
        // Open now → fraction of published that's inside its schedule window.
        $openRingPct = $heroPublished > 0
            ? min(100, (int) round(($activeAssessmentsCount / $heroPublished) * 100))
            : ($activeAssessmentsCount > 0 ? 100 : 0);
        // Submissions → decorative full ring once any submission lands.
        $submissionsRingPct = $submittedSessionsCount > 0 ? 100 : 0;
        // Needs grading → share of submissions still awaiting a grade.
        $gradingRingPct = $submittedSessionsCount > 0
            ? min(100, (int) round(($pendingManualGradingCount / $submittedSessionsCount) * 100))
            : 0;
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
                <article class="{{ $cardBase }} bg-cyan-50 border-cyan-100 hover:bg-cyan-200 hover:border-cyan-300 hover:shadow-cyan-300/60 md:col-span-2 xl:col-span-2">
                    <div class="flex items-start gap-3 sm:gap-4">
                        {{-- DONUT — published share of the total library; ring %
                             reads in the center. --}}
                        <div class="relative shrink-0">
                            <svg viewBox="0 0 36 36" class="h-[4.5rem] w-[4.5rem] -rotate-90 transition-transform duration-300 ease-out group-hover:rotate-[-84deg] sm:h-[5rem] sm:w-[5rem]" aria-hidden="true">
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" class="text-cyan-200/70"/>
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                        stroke-dasharray="{{ $heroPublishedPct }} 100"
                                        class="text-cyan-600 transition-[stroke-dasharray] duration-500"/>
                            </svg>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-cyan-900">
                                <span class="text-[0.95rem] font-bold leading-none tabular-nums">{{ $heroPublishedPct }}%</span>
                                <span class="mt-0.5 text-[8px] font-semibold uppercase tracking-wider text-cyan-700/70">{{ __('live') }}</span>
                            </div>
                        </div>

                        {{-- BODY --}}
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-cyan-700">{{ __('Assessments') }}</p>
                            <p class="mt-0.5 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <span class="text-[1.5rem] font-bold leading-none tabular-nums text-cyan-900 sm:text-[1.65rem]">{{ $quizTotalCount }}</span>
                                @if ($publishedAssessmentsCount > 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-800 ring-1 ring-inset ring-emerald-200/80">
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        </span>
                                        {{ $publishedAssessmentsCount }} {{ __('live') }}
                                    </span>
                                @endif
                            </p>
                            <p class="mt-1 max-w-md text-[12px] leading-snug text-cyan-800/80">{{ __('Everything you own across your courses.') }}</p>

                            @if ($heroTotal > 0)
                                <ul class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[10px]">
                                    <li class="inline-flex items-center gap-1.5 text-cyan-900">
                                        <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-cyan-600"></span>
                                        <span class="font-semibold uppercase tracking-wider text-cyan-700/80">{{ __('Published') }}</span>
                                        <span class="text-[12px] font-bold tabular-nums">{{ $heroPublished }}</span>
                                    </li>
                                    <li class="inline-flex items-center gap-1.5 text-cyan-900">
                                        <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                                        <span class="font-semibold uppercase tracking-wider text-cyan-700/80">{{ __('Draft') }}</span>
                                        <span class="text-[12px] font-bold tabular-nums">{{ $heroDraft }}</span>
                                    </li>
                                    @if ($heroOther > 0)
                                        <li class="inline-flex items-center gap-1.5 text-cyan-900">
                                            <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-cyan-300"></span>
                                            <span class="font-semibold uppercase tracking-wider text-cyan-700/80">{{ __('Archived') }}</span>
                                            <span class="text-[12px] font-bold tabular-nums">{{ $heroOther }}</span>
                                        </li>
                                    @endif
                                </ul>
                            @endif

                            <div class="mt-2.5 flex items-center justify-between gap-3">
                                <a href="{{ route('examiner.exams.index', $dashboardProctoringQueryBase) }}"
                                   class="inline-flex items-center gap-1.5 rounded-full bg-cyan-600 px-3 py-1 text-[11px] font-semibold text-white shadow-sm transition duration-150 ease-out hover:-translate-y-0.5 hover:bg-cyan-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-cyan-400/40 focus:ring-offset-2 focus:ring-offset-cyan-50">
                                    {{ __('View all') }}
                                    <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                                </a>
                                <span class="hidden text-[10px] uppercase tracking-[0.14em] text-cyan-700/60 sm:inline">{{ __('Across all courses') }}</span>
                            </div>
                        </div>
                    </div>
                </article>

                {{-- SATELLITE 1 — Open now.
                     Solid deep-emerald tone. Label + value sit adjacent in
                     DOM; icon badge floats top-right so accessibility
                     readers (and the inflated-grading regression tests) see
                     the label paired directly with its number. --}}
                <article class="{{ $cardBase }} bg-emerald-50 border-emerald-100 hover:bg-emerald-200 hover:border-emerald-300 hover:shadow-emerald-300/60">
                    <div class="flex items-start gap-3">
                        {{-- DONUT — active fraction of the published library. --}}
                        <div class="relative shrink-0">
                            <svg viewBox="0 0 36 36" class="h-[3.75rem] w-[3.75rem] -rotate-90 transition-transform duration-300 ease-out group-hover:rotate-[-84deg]" aria-hidden="true">
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" class="text-emerald-200/70"/>
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                        stroke-dasharray="{{ $openRingPct }} 100"
                                        class="text-emerald-600 transition-[stroke-dasharray] duration-500"/>
                            </svg>
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <span class="text-[0.85rem] font-bold leading-none tabular-nums text-emerald-900">{{ $openRingPct }}%</span>
                            </div>
                        </div>
                        {{-- BODY --}}
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-emerald-700">{{ __('Open now') }}</p>
                            <p class="mt-0.5 text-[1.25rem] font-bold leading-none tabular-nums text-emerald-900">{{ $activeAssessmentsCount }}</p>
                            @if ($activeAssessmentsCount > 0)
                                <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-800 ring-1 ring-inset ring-emerald-200/80">
                                    <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    </span>
                                    {{ __('Live now') }}
                                </span>
                            @endif
                            <p class="mt-1.5 text-[11px] leading-snug text-emerald-800/75">
                                @if ($activeAssessmentsCount > 0)
                                    {{ __('Students can start or submit during the schedule window.') }}
                                @else
                                    {{ __('Nothing inside its window right now.') }}
                                @endif
                            </p>
                        </div>
                    </div>
                </article>

                {{-- SATELLITE 2 — Submissions. Solid deep-indigo tone. --}}
                <article class="{{ $cardBase }} bg-indigo-50 border-indigo-100 hover:bg-indigo-200 hover:border-indigo-300 hover:shadow-indigo-300/60">
                    <div class="flex items-start gap-3">
                        {{-- DONUT — full ring once any submission lands. Center
                             shows a checkmark when there is activity. --}}
                        <div class="relative shrink-0">
                            <svg viewBox="0 0 36 36" class="h-[3.75rem] w-[3.75rem] -rotate-90 transition-transform duration-300 ease-out group-hover:rotate-[-84deg]" aria-hidden="true">
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" class="text-indigo-200/70"/>
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                        stroke-dasharray="{{ $submissionsRingPct }} 100"
                                        class="text-indigo-600 transition-[stroke-dasharray] duration-500"/>
                            </svg>
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-indigo-700">
                                <i class="fa-solid fa-paper-plane text-[14px]"></i>
                            </div>
                        </div>
                        {{-- BODY --}}
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-indigo-700">{{ __('Submissions') }}</p>
                            <p class="mt-0.5 text-[1.25rem] font-bold leading-none tabular-nums text-indigo-900">{{ $submittedSessionsCount }}</p>
                            <p class="mt-1 text-[11px] leading-snug text-indigo-800/75">{{ __('Total student attempts that finished.') }}</p>
                            <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['tab' => 'active'])) }}"
                               class="{{ $cardFootLink }} mt-1.5 text-indigo-700 hover:text-indigo-900">
                                {{ __('Browse active') }}
                                <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                            </a>
                        </div>
                    </div>
                </article>

                {{-- SATELLITE 3 — Needs grading. Solid deep-amber tone, with
                     a held-for-review chip on the front. Spans the full row
                     on xl now that the hero only takes a single row. --}}
                <article class="{{ $cardBase }} bg-amber-50 border-amber-100 hover:bg-amber-200 hover:border-amber-300 hover:shadow-amber-300/60 md:col-span-2 xl:col-span-4">
                    <div class="flex items-start gap-3 sm:gap-4">
                        {{-- DONUT — share of submissions still awaiting grading. --}}
                        <div class="relative shrink-0">
                            <svg viewBox="0 0 36 36" class="h-[3.75rem] w-[3.75rem] -rotate-90 transition-transform duration-300 ease-out group-hover:rotate-[-84deg] sm:h-[4.25rem] sm:w-[4.25rem]" aria-hidden="true">
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" class="text-amber-200/70"/>
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                        stroke-dasharray="{{ $gradingRingPct }} 100"
                                        class="text-amber-600 transition-[stroke-dasharray] duration-500"/>
                            </svg>
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <span class="text-[0.85rem] font-bold leading-none tabular-nums text-amber-900">{{ $gradingRingPct }}%</span>
                            </div>
                        </div>
                        {{-- BODY --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-amber-700">{{ __('Needs grading') }}</p>
                                @if ($heldResultsCount > 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-800 ring-1 ring-inset ring-amber-200/80">
                                        <i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                                        {{ __(':count held for review', ['count' => $heldResultsCount]) }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-[1.25rem] font-bold leading-none tabular-nums text-amber-900">{{ $pendingManualGradingCount }}</p>
                            <p class="mt-1 text-[11px] leading-snug text-amber-800/75">
                                @if ($pendingManualGradingCount > 0)
                                    {{ __('Distinct submissions waiting on essay grading. Tap to open the queue and apply AI suggestions or grade manually.') }}
                                @else
                                    {{ __('You are caught up — no submissions are waiting on manual grading.') }}
                                @endif
                            </p>
                            <a href="{{ route('examiner.grading.pending') }}" class="{{ $cardFootLink }} mt-1.5 text-amber-700 hover:text-amber-900">
                                {{ __('Open grading queue') }}
                                <i class="fa-solid fa-arrow-right text-[10px] transition group-hover:translate-x-0.5"></i>
                            </a>
                        </div>
                    </div>
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
                        <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['proctoring_focus' => 'flagged'])) }}" class="{{ $integrityCardBase }} hover:border-rose-500/60">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-rose-500"></span>
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
                        <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['proctoring_focus' => 'auto_submitted'])) }}" class="{{ $integrityCardBase }} hover:border-amber-500/60">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-amber-500"></span>
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
                        <a href="{{ route('examiner.exams.index', array_merge($dashboardProctoringQueryBase, ['proctoring_focus' => 'held_results'])) }}" class="{{ $integrityCardBase }} hover:border-violet-500/60">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 bg-violet-500"></span>
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

        {{-- Quick actions — solid primary CTA + tinted secondary chips.
             Each chip uses the same accent palette as its matching satellite
             card above and separates label from count with a thin divider
             for a refined, Linear-style feel. --}}
        <section class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="examiner-quick-actions-heading">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 id="examiner-quick-actions-heading" class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">{{ __('Quick actions') }}</h2>
                <span class="hidden text-[11px] text-slate-500 sm:inline">{{ __('Jump straight to the most-used surfaces.') }}</span>
            </div>
            @php
                // Each chip pulls its full color set from this map so the
                // markup stays compact and palettes never drift between the
                // satellite cards above and the quick action chips below.
                $chipPalettes = [
                    'cyan'    => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-cyan-200 bg-cyan-50 px-4 text-sm font-semibold text-cyan-900 transition duration-200 ease-out hover:-translate-y-0.5 hover:border-cyan-400 hover:bg-cyan-200 hover:shadow-md hover:shadow-cyan-300/50 focus:outline-none focus:ring-2 focus:ring-cyan-400/40 focus:ring-offset-2',
                    'emerald' => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-900 transition duration-200 ease-out hover:-translate-y-0.5 hover:border-emerald-400 hover:bg-emerald-200 hover:shadow-md hover:shadow-emerald-300/50 focus:outline-none focus:ring-2 focus:ring-emerald-400/40 focus:ring-offset-2',
                    'violet'  => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-900 transition duration-200 ease-out hover:-translate-y-0.5 hover:border-violet-400 hover:bg-violet-200 hover:shadow-md hover:shadow-violet-300/50 focus:outline-none focus:ring-2 focus:ring-violet-400/40 focus:ring-offset-2',
                    'amber'   => 'group relative inline-flex min-h-[44px] max-w-full min-w-0 items-center gap-2.5 rounded-full border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-900 transition duration-200 ease-out hover:-translate-y-0.5 hover:border-amber-400 hover:bg-amber-200 hover:shadow-md hover:shadow-amber-300/50 focus:outline-none focus:ring-2 focus:ring-amber-400/40 focus:ring-offset-2',
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
                {{-- PRIMARY — solid teal CTA. Sits above the chips visually
                     thanks to a tinted shadow halo that deepens on hover. --}}
                <a href="{{ route('examiner.exams.create') }}"
                   class="group relative inline-flex min-h-[44px] items-center gap-2.5 rounded-full bg-teal-600 px-5 text-sm font-semibold text-white shadow-md shadow-teal-600/30 transition duration-200 ease-out hover:-translate-y-0.5 hover:bg-teal-700 hover:shadow-lg hover:shadow-teal-700/50 focus:outline-none focus:ring-2 focus:ring-teal-500/50 focus:ring-offset-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-white/15 ring-1 ring-inset ring-white/30 transition group-hover:rotate-90">
                        <i class="fa-solid fa-plus text-[12px]" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0 truncate">{{ __('Create assessment') }}</span>
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
