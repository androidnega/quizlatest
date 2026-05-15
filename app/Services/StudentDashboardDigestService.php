<?php

namespace App\Services;

use App\Models\Course;
use App\Models\PracticeAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight “since last visit” and habit hints for the student home screen.
 */
final class StudentDashboardDigestService
{
    /** @return array<string, mixed> */
    public function forStudent(User $user, PracticeModuleSettings $practiceSettings): array
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);

        $since = $user->student_last_dashboard_at;

        return [
            'dashboard_notices' => app(StudentNoticeDigestService::class)->noticesFor($user, 8),
            'dashboard_new_assessments' => app(StudentNoticeDigestService::class)->recentlyPublishedAssessments($user, 7, 8),
            'dashboard_course_new_materials' => $this->newMaterialHints($user, $since),
            'dashboard_practice_streak_days' => $practiceSettings->studentPracticeEnabled()
                ? $this->practiceStreakDays($user, $tz, $now)
                : 0,
            'dashboard_practice_week_nudge' => $practiceSettings->studentPracticeEnabled()
                && $this->practiceStreakDays($user, $tz, $now) < 2
                && $this->needsPracticeWeekNudge($user, $tz, $now),
            'dashboard_tip' => $this->rotatingTip($user, $now),
            'dashboard_policy_notice' => $this->policyNotice($user),
        ];
    }

    /**
     * @return list<array{name: string, count: int}>
     */
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

    private function practiceStreakDays(User $user, string $tz, Carbon $now): int
    {
        $streak = 0;
        for ($i = 0; $i < 60; $i++) {
            $localStart = $now->copy()->startOfDay()->subDays($i);
            $localEnd = $localStart->copy()->endOfDay();
            $has = PracticeAttempt::query()
                ->where('student_id', $user->id)
                ->whereNotNull('submitted_at')
                ->whereBetween('submitted_at', [$localStart->clone()->utc(), $localEnd->clone()->utc()])
                ->exists();
            if (! $has) {
                break;
            }
            $streak++;
        }

        return $streak;
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
