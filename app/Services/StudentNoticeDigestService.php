<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Support\AssignmentDueCountdown;
use App\Models\Result;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ephemeral “notifications” for students (no persistence): built from existing
 * assessments, results, and sessions so the UI can surface timely notices.
 */
final class StudentNoticeDigestService
{
    public function __construct(
        private readonly StudentAssignmentCatalogService $assignmentCatalog,
    ) {}
    /**
     * @return list<array{id: string, title: string, body: string, href: ?string, at: string, is_unread: bool}>
     */
    public function noticesFor(User $user, int $limit = 20, ?string $seenAt = null): array
    {
        if ($user->role !== 'student') {
            return [];
        }

        $now = Carbon::now();
        $tz = (string) config('app.timezone');
        $out = [];

        $courseIds = collect();
        if ($user->class_id !== null) {
            $courseIds = DB::table('class_course')
                ->where('class_id', $user->class_id)
                ->pluck('course_id');
        }

        if ($courseIds->isNotEmpty()) {
            $published = Quiz::query()
                ->whereIn('course_id', $courseIds)
                ->where('status', 'published')
                ->where('university_id', $user->university_id)
                ->where(function ($q) use ($user) {
                    $q->whereDoesntHave('targetClassrooms')
                        ->orWhereHas('targetClassrooms', function ($q2) use ($user) {
                            $q2->where('classes.id', (int) $user->class_id);
                        });
                })
                ->orderByDesc('published_at')
                ->limit(30)
                ->get(['id', 'title', 'assessment_type', 'published_at', 'due_at', 'start_time', 'end_time']);

            // Audit Phase 10 / P1: previously each foreach iteration ran
            // its own SELECT to look up the latest submitted ExamSession
            // for the (student, exam) pair. With 30 published exams that
            // was 60 round trips per noticesFor() call (which itself is
            // memoized but still). Now we fetch once with whereIn().
            $publishedIds = $published->pluck('id')->all();
            $submittedSessionsByExamId = collect();
            if ($publishedIds !== []) {
                $submittedSessionsByExamId = ExamSession::query()
                    ->where('student_id', $user->id)
                    ->whereIn('exam_id', $publishedIds)
                    ->where('status', 'submitted')
                    ->orderByDesc('id')
                    ->get(['id', 'session_id', 'exam_id'])
                    ->unique('exam_id')
                    ->keyBy('exam_id');
            }

            foreach ($published as $exam) {
                $pubAt = $exam->published_at;
                if ($pubAt !== null && $pubAt->greaterThanOrEqualTo($now->copy()->subDays(7))) {
                    $submittedSession = $submittedSessionsByExamId->get($exam->id);

                    $out[] = [
                        'id' => 'newpub:'.$exam->id,
                        'title' => $submittedSession !== null
                            ? __('Assessment submitted')
                            : __('New assessment published'),
                        'body' => (string) $exam->title,
                        'href' => $this->assignmentCatalog->studentEntryHref($exam, $submittedSession),
                        'at' => $pubAt->toIso8601String(),
                    ];
                }
            }

            foreach ($published as $exam) {
                $alreadySubmitted = $submittedSessionsByExamId->has($exam->id);

                if ($exam->assessment_type === 'assignment' && ! $alreadySubmitted && $exam->isAvailableForStudentToStart($now)) {
                    $dueAt = $exam->due_at;
                    $showDue = $dueAt !== null
                        && $now->lessThanOrEqualTo($dueAt->copy()->addDays(7));
                    if ($showDue) {
                        $out[] = [
                            'id' => 'due:'.$exam->id,
                            'title' => $dueAt !== null && $now->greaterThan($dueAt)
                                ? __('Assignment overdue — still open')
                                : __('Assignment due soon'),
                            'body' => $exam->title.($dueAt !== null
                                ? ' · '.__('Due :d', ['d' => $dueAt->timezone($tz)->format('M j, H:i')])
                                : ''),
                            'href' => route('student.exam.prepare', $exam),
                            'at' => ($dueAt ?? $now)->toIso8601String(),
                        ];
                    }
                }
                if ($exam->assessment_type !== 'assignment' && $exam->start_time !== null && ! $alreadySubmitted) {
                    if ($exam->start_time->greaterThan($now) && $exam->start_time->lessThanOrEqualTo($now->copy()->addDay())) {
                        $out[] = [
                            'id' => 'start:'.$exam->id,
                            'title' => __('Assessment starting soon'),
                            'body' => $exam->title.' · '.__('Opens :d', ['d' => $exam->start_time->timezone($tz)->format('M j, H:i')]),
                            'href' => route('student.exam.instructions', $exam),
                            'at' => $exam->start_time->toIso8601String(),
                        ];
                    }
                }
            }
        }

        // Audit Phase 10 / P1: held + pending used to run a per-row SELECT
        // for the related ExamSession. Batch into a single query for both
        // status sets.
        $held = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'held')
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $pending = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending_manual')
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $reviewExamIds = $held->pluck('quiz_id')->merge($pending->pluck('quiz_id'))->unique()->all();
        $reviewSessionsByExamId = collect();
        if ($reviewExamIds !== []) {
            $reviewSessionsByExamId = ExamSession::query()
                ->where('student_id', $user->id)
                ->whereIn('exam_id', $reviewExamIds)
                ->where('status', 'submitted')
                ->orderByDesc('id')
                ->get(['id', 'session_id', 'exam_id'])
                ->unique('exam_id')
                ->keyBy('exam_id');
        }

        foreach ($held as $r) {
            $session = $reviewSessionsByExamId->get($r->quiz_id);
            $out[] = [
                'id' => 'held:'.$r->id,
                'title' => __('Held for review'),
                'body' => __('Your work on :title is under review before a result can be released.', ['title' => $r->quiz?->title ?? __('this assessment')]),
                'href' => $session ? route('student.results.show', $session) : route('student.results.index'),
                'at' => ($r->submitted_at ?? $r->updated_at ?? $now)->toIso8601String(),
            ];
        }

        foreach ($pending as $r) {
            $session = $reviewSessionsByExamId->get($r->quiz_id);
            $out[] = [
                'id' => 'pend:'.$r->id,
                'title' => __('Awaiting grading'),
                'body' => __(':title is submitted and waiting to be marked.', ['title' => $r->quiz?->title ?? __('Your assessment')]),
                'href' => $session ? route('student.results.show', $session) : route('student.results.index'),
                'at' => ($r->submitted_at ?? $r->updated_at ?? $now)->toIso8601String(),
            ];
        }

        $autoSessions = ExamSession::query()
            ->where('student_id', $user->id)
            ->where('status', 'submitted')
            ->whereNotNull('auto_submit_reason_code')
            ->where(function ($q) use ($now) {
                $q->whereNull('end_time')
                    ->orWhere('end_time', '>=', $now->copy()->subDays(14));
            })
            ->with(['exam:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        foreach ($autoSessions as $s) {
            $out[] = [
                'id' => 'auto:'.$s->id,
                'title' => __('Submitted for review'),
                'body' => __(':title was auto-submitted and may need a quick review before your result is final.', ['title' => $s->exam?->title ?? __('An assessment')]),
                'href' => route('student.results.show', $s),
                'at' => ($s->end_time ?? $s->updated_at ?? $now)->toIso8601String(),
            ];
        }

        if ($user->class_id === null) {
            $out[] = [
                'id' => 'noclass',
                'title' => __('Class not assigned'),
                'body' => __('Ask your coordinator to place you in a class so assessments and materials appear.'),
                'href' => route('profile.edit'),
                'at' => $now->toIso8601String(),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        $seen = [];
        $dedup = [];
        foreach ($out as $row) {
            $k = $row['id'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $row['is_unread'] = $this->isUnread((string) ($row['at'] ?? ''), $seenAt);
            $dedup[] = $row;
            if (count($dedup) >= $limit) {
                break;
            }
        }

        return $dedup;
    }

    private function isUnread(string $at, ?string $seenAt): bool
    {
        if ($seenAt === null || $seenAt === '') {
            return true;
        }
        if ($at === '') {
            return false;
        }

        return strcmp($at, $seenAt) > 0;
    }

    /**
     * Open or newly published assessments for the dashboard spotlight.
     *
     * @return list<array{
     *     quiz_id: int,
     *     title: string,
     *     course_line: string,
     *     type_label: string,
     *     href: string,
     *     published_at: string,
     *     cta_label: string,
     *     countdown_ends_at: ?string,
     *     countdown_prefix: ?string,
     *     countdown_expired_cta: ?string,
     *     countdown_expired_state: ?string,
     * }>
     */
    public function dashboardOpenAssessments(User $user, int $newWithinDays = 7, int $limit = 8): array
    {
        if ($user->role !== 'student' || $user->class_id === null) {
            return [];
        }

        $now = Carbon::now();
        $newSince = $now->copy()->subDays(max(1, $newWithinDays));

        $courseIds = DB::table('class_course')
            ->where('class_id', $user->class_id)
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return [];
        }

        $quizzes = Quiz::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', 'published')
            ->where('university_id', $user->university_id)
            ->where(function ($q) use ($user) {
                $q->whereDoesntHave('targetClassrooms')
                    ->orWhereHas('targetClassrooms', function ($q2) use ($user) {
                        $q2->where('classes.id', (int) $user->class_id);
                    });
            })
            ->with(['course:id,code,title'])
            ->orderByDesc('published_at')
            ->get();

        $submittedSessionsByExamId = ExamSession::query()
            ->where('student_id', $user->id)
            ->whereIn('exam_id', $quizzes->pluck('id'))
            ->where('status', 'submitted')
            ->orderByDesc('id')
            ->get(['id', 'session_id', 'exam_id'])
            ->unique('exam_id')
            ->keyBy('exam_id');

        $tz = (string) config('app.timezone');
        $out = [];

        foreach ($quizzes as $exam) {
            $submittedSession = $submittedSessionsByExamId->get($exam->id);
            if ($submittedSession !== null) {
                continue;
            }

            $pub = $exam->published_at;
            $isNew = $pub !== null && $pub->greaterThanOrEqualTo($newSince);
            $canStart = $exam->isAvailableForStudentToStart($now);
            $upcoming = $exam->start_time !== null && $now->lt($exam->start_time);

            if (! $isNew && ! $canStart && ! $upcoming) {
                continue;
            }

            $typeLabel = match ($exam->assessment_type) {
                'assignment' => __('Assignment'),
                'quiz' => __('Quiz'),
                'mid' => __('Mid-semester'),
                'exam' => __('Exam'),
                default => __('Assessment'),
            };

            $courseLine = trim(implode(' — ', array_filter([
                $exam->course?->code,
                $exam->course?->title,
            ])));

            $countdown = $this->resolveDashboardCountdown($exam, $now);

            // The "Start" CTA label per assessment type — shared by both the
            // live cta_label (when the quiz is already open) and the
            // post-expiry CTA (when the "Opens in" countdown completes and the
            // window has just become startable in the browser).
            $startLabel = match ((string) ($exam->assessment_type ?? 'exam')) {
                'quiz' => __('Start quiz'),
                'mid' => __('Start mid-semester'),
                'exam' => __('Start exam'),
                'assignment' => __('Open assignment'),
                default => __('Start'),
            };

            $ctaLabel = match (true) {
                $exam->isAssignment() => __('Open assignment'),
                $canStart => $startLabel,
                $upcoming => null,
                default => __('Instructions'),
            };

            // When the live countdown hits 00:00:00 in the browser, the timer
            // surface swaps from the clock to this CTA without a page reload.
            // Driven by the prefix:
            //   - "Opens in" → the window has just opened, so promote to "Start"
            //   - "Closes in" → the window has just closed, so signal "Closed"
            //   - "Due in"   → the assignment hit its due moment → "Submit now"
            $prefixKey = strtolower((string) ($countdown['prefix'] ?? ''));
            [$expiredCta, $expiredState] = match (true) {
                $prefixKey === '' => [null, null],
                str_contains($prefixKey, 'open') => [$startLabel, 'ready'],
                str_contains($prefixKey, 'close') => [__('Closed'), 'closed'],
                str_contains($prefixKey, 'due') => [__('Submit now'), 'overdue'],
                default => [$startLabel, 'ready'],
            };

            $out[] = [
                'quiz_id' => (int) $exam->id,
                'title' => (string) $exam->title,
                'course_line' => $courseLine,
                'type_label' => $typeLabel,
                'href' => $this->assignmentCatalog->studentEntryHref($exam, null),
                'published_at' => $pub !== null
                    ? $pub->timezone($tz)->format('M j, Y')
                    : '',
                'published_at_sort' => $pub?->toIso8601String() ?? '',
                'cta_label' => $ctaLabel,
                'countdown_ends_at' => $countdown['ends_at'] ?? null,
                'countdown_prefix' => $countdown['prefix'] ?? null,
                'countdown_expired_cta' => $expiredCta,
                'countdown_expired_state' => $expiredState,
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $aEnd = $a['countdown_ends_at'] ?? null;
            $bEnd = $b['countdown_ends_at'] ?? null;
            if ($aEnd !== null && $bEnd !== null) {
                return strcmp($aEnd, $bEnd);
            }
            if ($aEnd !== null) {
                return -1;
            }
            if ($bEnd !== null) {
                return 1;
            }

            return strcmp((string) ($b['published_at_sort'] ?? ''), (string) ($a['published_at_sort'] ?? ''));
        });

        return array_slice(array_map(static function (array $row): array {
            unset($row['published_at_sort']);

            return $row;
        }, $out), 0, max(1, $limit));
    }

    public function openAssessmentsCount(User $user): int
    {
        return count($this->dashboardOpenAssessments($user, 7, 50));
    }

    /**
     * @return array{ends_at: string, prefix: string}|null
     */
    private function resolveDashboardCountdown(Quiz $exam, Carbon $now): ?array
    {
        if ($exam->isAssignment()) {
            return AssignmentDueCountdown::resolve($exam, $now);
        }

        $opensWindow = $now->copy()->addDays(AssignmentDueCountdown::WINDOW_DAYS);
        $closesWindow = $now->copy()->addDay();

        if ($exam->start_time !== null && $now->lt($exam->start_time)) {
            $start = $exam->start_time->copy();
            if ($start->lessThanOrEqualTo($opensWindow)) {
                return [
                    'ends_at' => $start->toIso8601String(),
                    'prefix' => __('Opens in'),
                ];
            }
        }

        if ($exam->end_time !== null && $exam->isAvailableForStudentToStart($now)) {
            $end = $exam->end_time->copy();
            if ($end->greaterThan($now) && $end->lessThanOrEqualTo($closesWindow)) {
                return [
                    'ends_at' => $end->toIso8601String(),
                    'prefix' => __('Closes in'),
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array{quiz_id: int, title: string, course_line: string, type_label: string, href: string, published_at: string, cta_label: string}>
     */
    public function recentlyPublishedAssessments(User $user, int $withinDays = 7, int $limit = 8): array
    {
        return $this->dashboardOpenAssessments($user, $withinDays, $limit);
    }

    public function noticeCount(User $user, ?string $seenAt = null): int
    {
        $notices = $this->noticesFor($user, 50, $seenAt);
        if ($seenAt === null || $seenAt === '') {
            return count($notices);
        }

        return count(array_filter($notices, static fn (array $n): bool => (bool) ($n['is_unread'] ?? false)));
    }
}
