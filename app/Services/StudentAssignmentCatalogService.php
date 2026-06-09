<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Published assignments visible to a student, grouped for list UIs.
 */
final class StudentAssignmentCatalogService
{
    /**
     * @return array{
     *     activeSession: ExamSession|null,
     *     courses: list<array{
     *         course: Course,
     *         in_progress: Collection<int, Quiz>,
     *         open: Collection<int, Quiz>,
     *         upcoming: Collection<int, Quiz>,
     *         submitted: list<array{exam: Quiz, session: ExamSession, result: Result|null}>,
     *         missed: Collection<int, Quiz>,
     *     }>,
     *     summaryOpen: int,
     *     summaryInProgress: int,
     *     summaryUpcoming: int,
     *     summarySubmitted: int,
     *     summaryMissed: int,
     * }
     */
    public function catalogFor(User $user): array
    {
        $user->loadMissing(['classroom']);

        $now = Carbon::now();
        $activeSession = ExamSession::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['exam:id,title,course_id,assessment_type'])
            ->first();

        $courses = collect();
        $summaryOpen = 0;
        $summaryInProgress = 0;
        $summaryUpcoming = 0;
        $summarySubmitted = 0;
        $summaryMissed = 0;

        if ($user->class_id === null) {
            return [
                'activeSession' => $activeSession,
                'courses' => [],
                'summaryOpen' => 0,
                'summaryInProgress' => 0,
                'summaryUpcoming' => 0,
                'summarySubmitted' => 0,
                'summaryMissed' => 0,
            ];
        }

        $courseIds = DB::table('class_course')
            ->where('class_id', $user->class_id)
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return [
                'activeSession' => $activeSession,
                'courses' => [],
                'summaryOpen' => 0,
                'summaryInProgress' => 0,
                'summaryUpcoming' => 0,
                'summarySubmitted' => 0,
                'summaryMissed' => 0,
            ];
        }

        $courseModels = Course::query()
            ->whereIn('id', $courseIds)
            ->orderBy('code')
            ->get();

        $allExamIds = Quiz::query()
            ->whereIn('course_id', $courseIds)
            ->where('status', 'published')
            ->where('assessment_type', 'assignment')
            ->where('university_id', $user->university_id)
            ->where(function ($q) use ($user) {
                $q->whereDoesntHave('targetClassrooms')
                    ->orWhereHas('targetClassrooms', function ($q2) use ($user) {
                        $q2->where('classes.id', (int) $user->class_id);
                    });
            })
            ->pluck('id');

        $sessionsByExam = ExamSession::query()
            ->where('student_id', $user->id)
            ->whereIn('exam_id', $allExamIds)
            ->orderByDesc('id')
            ->get()
            ->unique('exam_id')
            ->keyBy('exam_id');

        $resultsByExam = Result::query()
            ->where('user_id', $user->id)
            ->whereIn('quiz_id', $allExamIds)
            ->get()
            ->keyBy('quiz_id');

        foreach ($courseModels as $course) {
            $exams = Quiz::query()
                ->where('course_id', $course->id)
                ->where('status', 'published')
                ->where('assessment_type', 'assignment')
                ->where('university_id', $user->university_id)
                ->where(function ($q) use ($user) {
                    $q->whereDoesntHave('targetClassrooms')
                        ->orWhereHas('targetClassrooms', function ($q2) use ($user) {
                            $q2->where('classes.id', (int) $user->class_id);
                        });
                })
                ->orderBy('due_at')
                ->orderBy('title')
                ->get();

            $inProgress = collect();
            $open = collect();
            $upcoming = collect();
            $submitted = [];
            $missed = collect();

            foreach ($exams as $exam) {
                if ($activeSession !== null
                    && (int) $activeSession->exam_id === (int) $exam->id
                    && in_array($activeSession->status, ['active', 'paused'], true)) {
                    $inProgress->push($exam);

                    continue;
                }

                $session = $sessionsByExam->get($exam->id);
                if ($session !== null && $session->status === 'submitted') {
                    $submitted[] = [
                        'exam' => $exam,
                        'session' => $session,
                        'result' => $resultsByExam->get($exam->id),
                    ];

                    continue;
                }

                if ($exam->start_time !== null && $now->lt($exam->start_time)) {
                    $upcoming->push($exam);

                    continue;
                }

                if ($exam->isAvailableForStudentToStart($now)) {
                    $open->push($exam);

                    continue;
                }

                $missed->push($exam);
            }

            if ($inProgress->isNotEmpty() || $open->isNotEmpty() || $upcoming->isNotEmpty() || $submitted !== [] || $missed->isNotEmpty()) {
                $courses->push([
                    'course' => $course,
                    'in_progress' => $inProgress->values(),
                    'open' => $open->values(),
                    'upcoming' => $upcoming->values(),
                    'submitted' => $submitted,
                    'missed' => $missed->values(),
                ]);
            }

            $summaryOpen += $open->count();
            $summaryInProgress += $inProgress->count();
            $summaryUpcoming += $upcoming->count();
            $summarySubmitted += count($submitted);
            $summaryMissed += $missed->count();
        }

        return [
            'activeSession' => $activeSession,
            'courses' => $courses->all(),
            'summaryOpen' => $summaryOpen,
            'summaryInProgress' => $summaryInProgress,
            'summaryUpcoming' => $summaryUpcoming,
            'summarySubmitted' => $summarySubmitted,
            'summaryMissed' => $summaryMissed,
        ];
    }

    /**
     * Open assignments the student can still submit, ordered by due date.
     *
     * @return Collection<int, Quiz>
     */
    public function openAssignments(User $user): Collection
    {
        $catalog = $this->catalogFor($user);
        $out = collect();
        foreach ($catalog['courses'] as $row) {
            foreach ($row['open'] as $exam) {
                $out->push($exam);
            }
        }

        return $out->sortBy(fn (Quiz $q) => $q->due_at?->timestamp ?? PHP_INT_MAX)->values();
    }

    public function openAssignmentsDueCount(User $user, int $withinDays = 14): int
    {
        $now = Carbon::now();
        $until = $now->copy()->addDays(max(1, $withinDays));

        return $this->openAssignments($user)
            ->filter(function (Quiz $exam) use ($now, $until) {
                if ($exam->due_at === null) {
                    return true;
                }

                return $exam->due_at->greaterThanOrEqualTo($now->copy()->subDay())
                    && $exam->due_at->lessThanOrEqualTo($until);
            })
            ->count();
    }

    public function nextOpenAssignment(User $user): ?Quiz
    {
        return $this->openAssignments($user)->first();
    }

    public function studentEntryHref(Quiz $exam, ?ExamSession $submittedSession = null): string
    {
        if ($submittedSession !== null) {
            return route('student.results.show', $submittedSession);
        }

        if ($exam->isAssignment()) {
            return route('student.exam.prepare', $exam);
        }

        return route('student.exam.instructions', $exam);
    }
}
