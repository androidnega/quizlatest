<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ephemeral “notifications” for students (no persistence): built from existing
 * assessments, results, and sessions so the UI can surface timely notices.
 */
final class StudentNoticeDigestService
{
    /**
     * @return list<array{id: string, title: string, body: string, href: ?string, at: string}>
     */
    public function noticesFor(User $user, int $limit = 20): array
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

            foreach ($published as $exam) {
                $pubAt = $exam->published_at;
                if ($pubAt !== null && $pubAt->greaterThanOrEqualTo($now->copy()->subDays(7))) {
                    $out[] = [
                        'id' => 'newpub:'.$exam->id,
                        'title' => __('New assessment published'),
                        'body' => (string) $exam->title,
                        'href' => route('student.exam.instructions', $exam),
                        'at' => $pubAt->toIso8601String(),
                    ];
                }
            }

            foreach ($published as $exam) {
                $alreadySubmitted = ExamSession::query()
                    ->where('student_id', $user->id)
                    ->where('exam_id', $exam->id)
                    ->where('status', 'submitted')
                    ->exists();

                if ($exam->assessment_type === 'assignment' && $exam->due_at !== null && ! $alreadySubmitted) {
                    if ($now->lt($exam->due_at) && $now->greaterThanOrEqualTo($exam->due_at->copy()->subHours(48))) {
                        $out[] = [
                            'id' => 'due:'.$exam->id,
                            'title' => __('Assignment due soon'),
                            'body' => $exam->title.' · '.__('Due :d', ['d' => $exam->due_at->timezone($tz)->format('M j, H:i')]),
                            'href' => route('student.exam.prepare', $exam),
                            'at' => $now->toIso8601String(),
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

        $held = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'held')
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        foreach ($held as $r) {
            $session = ExamSession::query()
                ->where('student_id', $user->id)
                ->where('exam_id', $r->quiz_id)
                ->where('status', 'submitted')
                ->orderByDesc('id')
                ->first(['id', 'session_id']);
            $out[] = [
                'id' => 'held:'.$r->id,
                'title' => __('Held for review'),
                'body' => __('Your work on :title is under review before a result can be released.', ['title' => $r->quiz?->title ?? __('this assessment')]),
                'href' => $session ? route('student.results.show', $session) : route('student.results.index'),
                'at' => ($r->submitted_at ?? $r->updated_at ?? $now)->toIso8601String(),
            ];
        }

        $pending = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending_manual')
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        foreach ($pending as $r) {
            $session = ExamSession::query()
                ->where('student_id', $user->id)
                ->where('exam_id', $r->quiz_id)
                ->where('status', 'submitted')
                ->orderByDesc('id')
                ->first(['id', 'session_id']);
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
            $dedup[] = $row;
            if (count($dedup) >= $limit) {
                break;
            }
        }

        return $dedup;
    }

    public function noticeCount(User $user): int
    {
        return count($this->noticesFor($user, 50));
    }
}
