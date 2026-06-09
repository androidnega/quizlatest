<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ExamSession;
use App\Models\PracticeAttempt;
use App\Models\Result;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight “since last visit” and habit hints for the student home screen.
 */
final class StudentDashboardDigestService
{
    public function __construct(
        private readonly StudentAssignmentCatalogService $assignmentCatalog,
    ) {}

    /** @return array<string, mixed> */
    public function forStudent(User $user, PracticeModuleSettings $practiceSettings): array
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);

        $since = $user->student_last_dashboard_at;

        $noticeDigest = app(StudentNoticeDigestService::class);
        // Audit Phase 7: notices were instantiated twice (one local var,
        // one resolved again) and noticesFor() was always called even when
        // we only needed the count.
        $notices = $noticeDigest->noticesFor($user, 20);
        $newAssessments = $noticeDigest->dashboardOpenAssessments($user, 7, 8);
        $assignmentsDueCount = $this->assignmentCatalog->openAssignmentsDueCount($user);

        // Audit P2.4: compute streak ONCE via a single grouped DATE() query
        // (was 60 SELECT EXISTS round trips per dashboard load) and reuse
        // it for both the streak metric and the week-nudge.
        $practiceEnabled = $practiceSettings->studentPracticeEnabled();
        $streakDays = $practiceEnabled ? $this->practiceStreakDays($user, $tz, $now) : 0;

        return [
            'dashboard_notices' => array_slice($notices, 0, 8),
            'dashboard_new_assessments' => $newAssessments,
            'dashboard_course_new_materials' => $this->newMaterialHints($user, $since),
            'dashboard_stat_open_assessments' => $noticeDigest->openAssessmentsCount($user),
            'dashboard_stat_assignments_due' => max($assignmentsDueCount, $this->countNoticesByPrefix($notices, 'due:')),
            'dashboard_stat_pending_results' => $this->pendingResultsCount($user),
            'dashboard_stat_notice_count' => count($notices),
            'dashboard_assignment_due_notice' => $this->firstNoticeByPrefix($notices, 'due:'),
            'dashboard_next_action' => $this->resolveNextAction($user, $notices, $newAssessments),
            'dashboard_practice_streak_days' => $streakDays,
            'dashboard_practice_week_nudge' => $practiceEnabled
                && $streakDays < 2
                && $this->needsPracticeWeekNudge($user, $tz, $now),
            'dashboard_tip' => $this->rotatingTip($user, $now),
            'dashboard_policy_notice' => $this->policyNotice($user),
        ];
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    /**
     * @param  list<array{id: string, title: string, body: string, href: ?string, at: string}>  $notices
     */
    private function countNoticesByPrefix(array $notices, string $prefix): int
    {
        $n = 0;
        foreach ($notices as $row) {
            if (str_starts_with((string) ($row['id'] ?? ''), $prefix)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  list<array{id: string, title: string, body: string, href: ?string, at: string}>  $notices
     * @return array{id: string, title: string, body: string, href: ?string, at: string}|null
     */
    private function firstNoticeByPrefix(array $notices, string $prefix): ?array
    {
        foreach ($notices as $row) {
            if (str_starts_with((string) ($row['id'] ?? ''), $prefix)) {
                return $row;
            }
        }

        return null;
    }

    private function pendingResultsCount(User $user): int
    {
        return (int) Result::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['held', 'pending_manual'])
            ->count();
    }

    /**
     * @param  list<array{id: string, title: string, body: string, href: ?string, at: string}>  $notices
     * @param  list<array{quiz_id: int, title: string, course_line: string, type_label: string, href: string, published_at: string}>  $newAssessments
     * @return array{kind: string, title: string, subtitle: string, href: ?string, chip: string}|null
     */
    private function resolveNextAction(User $user, array $notices, array $newAssessments): ?array
    {
        $activeSession = ExamSession::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['exam:id,title'])
            ->first();

        if ($activeSession !== null && in_array($activeSession->status, ['active', 'paused'], true)) {
            $isAssignment = $activeSession->exam?->isAssignment() ?? false;

            return [
                'kind' => 'resume',
                'title' => $isAssignment ? __('Resume assignment') : __('Resume assessment'),
                'subtitle' => (string) ($activeSession->exam?->title ?? __('In progress')),
                'href' => route('student.exam.take', $activeSession),
                'chip' => $activeSession->status === 'paused' ? __('Paused') : __('Continue'),
            ];
        }

        $nextAssignment = $this->assignmentCatalog->nextOpenAssignment($user);
        if ($nextAssignment !== null) {
            $dueLine = $nextAssignment->due_at !== null
                ? __('Due :d', ['d' => $nextAssignment->due_at->timezone((string) config('app.timezone'))->format('M j, H:i')])
                : __('No due date set');

            return [
                'kind' => 'due',
                'title' => (string) $nextAssignment->title,
                'subtitle' => $dueLine,
                'href' => route('student.exam.prepare', $nextAssignment),
                'chip' => __('Submit'),
            ];
        }

        if ($newAssessments !== []) {
            $first = $newAssessments[0];

            return [
                'kind' => 'new',
                'title' => $first['title'],
                'subtitle' => trim($first['course_line'] ?: $first['type_label'].' · '.__('Published :d', ['d' => $first['published_at']])),
                'href' => $first['href'],
                'chip' => __('New'),
            ];
        }

        $due = $this->firstNoticeByPrefix($notices, 'due:');
        if ($due !== null) {
            return [
                'kind' => 'due',
                'title' => $due['title'],
                'subtitle' => (string) $due['body'],
                'href' => $due['href'],
                'chip' => __('Due'),
            ];
        }

        return [
            'kind' => 'idle',
            'title' => __('No urgent task right now'),
            'subtitle' => __('Check your worklist when you are ready.'),
            'href' => route('student.work.index'),
            'chip' => __('OK'),
        ];
    }

    private function newMaterialHints(User $user, ?Carbon $since): array
    {
        if ($since === null || $user->class_id === null) {
            return [];
        }

        $courseIds = DB::table('class_course')
            ->where('class_id', $user->class_id)
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return [];
        }

        return Course::query()
            ->whereIn('id', $courseIds)
            ->withCount([
                'courseMaterials as new_materials_count' => function ($q) use ($user, $since) {
                    $q->visibleToStudent($user)
                        ->where('course_materials.created_at', '>', $since);
                },
            ])
            ->get()
            ->filter(fn (Course $c) => ($c->new_materials_count ?? 0) > 0)
            ->map(fn (Course $c) => [
                'name' => (string) ($c->title ?: $c->code ?: __('Course')),
                'count' => (int) $c->new_materials_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Audit P2.4: practice streak is computed via ONE grouped query that
     * pulls the distinct local DATE(submitted_at) values from the last 60
     * days, then walked in PHP. Was 60 separate SELECT EXISTS queries.
     *
     * The cache is per-user with a short TTL because dashboards reload
     * frequently and the streak only changes after a new submission.
     */
    private function practiceStreakDays(User $user, string $tz, Carbon $now): int
    {
        return (int) \Illuminate\Support\Facades\Cache::remember(
            "student:{$user->id}:practice_streak",
            now()->addSeconds(60),
            function () use ($user, $tz, $now): int {
                $sinceUtc = $now->copy()->subDays(60)->startOfDay()->utc();
                $rows = PracticeAttempt::query()
                    ->where('student_id', $user->id)
                    ->whereNotNull('submitted_at')
                    ->where('submitted_at', '>=', $sinceUtc)
                    ->pluck('submitted_at');

                $localDays = [];
                foreach ($rows as $ts) {
                    if ($ts === null) {
                        continue;
                    }
                    $local = Carbon::parse((string) $ts)->setTimezone($tz)->format('Y-m-d');
                    $localDays[$local] = true;
                }

                $streak = 0;
                $cursor = $now->copy()->startOfDay();
                while (isset($localDays[$cursor->format('Y-m-d')])) {
                    $streak++;
                    $cursor->subDay();
                    if ($streak >= 60) {
                        break;
                    }
                }

                return $streak;
            },
        );
    }

    private function needsPracticeWeekNudge(User $user, string $tz, Carbon $now): bool
    {
        $weekStartUtc = $now->copy()->startOfWeek()->utc();

        return ! PracticeAttempt::query()
            ->where('student_id', $user->id)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $weekStartUtc)
            ->exists();
    }

    private function rotatingTip(User $user, Carbon $now): string
    {
        if (! (bool) config('student-dashboard.show_rotating_tips', false)) {
            return '';
        }

        $tips = [
            __('Use the bathroom before starting — the timer will not pause.'),
            __('Close extra tabs so notifications do not pull you away mid-quiz.'),
            __('Charge your laptop or plug in before you begin.'),
        ];

        $idx = ((int) floor($now->timestamp / 86400) + (int) $user->id) % max(1, count($tips));

        return $tips[$idx];
    }

    /**
     * @return array{version: int, message: string, faq_url: string}|null
     */
    private function policyNotice(User $user): ?array
    {
        $version = (int) config('student-dashboard.policy.version', 0);
        if ($version <= 0) {
            return null;
        }

        $message = trim((string) config('student-dashboard.policy.message', ''));
        if ($message === '') {
            return null;
        }

        $ack = (int) ($user->policy_notice_ack_version ?? 0);
        if ($ack >= $version) {
            return null;
        }

        return [
            'version' => $version,
            'message' => $message,
            'faq_url' => trim((string) config('student-dashboard.policy.faq_url', '')),
        ];
    }
}
