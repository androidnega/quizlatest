<?php

namespace App\Services;

use App\Models\AssignmentSubmissionFile;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentAnalyticsService
{
    /**
     * @return Collection<int, int>
     */
    public function eligibleClassIds(Quiz $exam): Collection
    {
        $exam->loadMissing('targetClassrooms');
        if ($exam->targetClassrooms->isNotEmpty()) {
            return $exam->targetClassrooms->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        }

        return DB::table('class_course')
            ->where('course_id', (int) $exam->course_id)
            ->pluck('class_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int> user ids
     */
    public function eligibleStudentIds(Quiz $exam): Collection
    {
        $classIds = $this->eligibleClassIds($exam);
        if ($classIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('role', 'student')
            ->whereIn('class_id', $classIds->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Latest session per student for this exam (by highest id).
     *
     * @return Collection<int, ExamSession> keyed by student_id
     */
    public function latestSessionsByStudent(Quiz $exam): Collection
    {
        return ExamSession::query()
            ->where('exam_id', (int) $exam->id)
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function cohortOverview(Quiz $exam): array
    {
        $eligibleIds = $this->eligibleStudentIds($exam);
        $assigned = $eligibleIds->count();

        $latest = $this->latestSessionsByStudent($exam);
        $startedStudentIds = $latest->keys();
        $started = $startedStudentIds->count();

        $inProgress = $latest->filter(fn (ExamSession $s) => in_array($s->status, ['active', 'paused'], true))->count();
        $submittedSessions = $latest->filter(fn (ExamSession $s) => $s->status === 'submitted');

        $results = Result::query()
            ->where('quiz_id', (int) $exam->id)
            ->get()
            ->keyBy('user_id');

        $submitted = $submittedSessions->count();
        $notStarted = max(0, $assigned - $started);

        $awaitingGrading = $submittedSessions->filter(function (ExamSession $s) use ($results) {
            $r = $results->get($s->student_id);

            return $r && $r->status === 'pending_manual';
        })->count();

        $graded = $submittedSessions->filter(function (ExamSession $s) use ($results) {
            $r = $results->get($s->student_id);

            return $r && $r->status === 'graded';
        })->count();

        $held = $submittedSessions->filter(function (ExamSession $s) use ($results) {
            $r = $results->get($s->student_id);

            return $r && $r->status === 'held';
        })->count();

        $publishedResult = $submittedSessions->filter(function (ExamSession $s) use ($results, $exam) {
            $r = $results->get($s->student_id);
            if (! $r || ! in_array($r->status, ['graded', 'published'], true)) {
                return false;
            }
            if ($exam->isAssignment() && ! $exam->assignmentGradesVisibleToStudents()) {
                return false;
            }

            return true;
        })->count();

        $autoSubmitted = $submittedSessions->filter(fn (ExamSession $s) => filled($s->auto_submit_reason_code))->count();

        $flagged = $submittedSessions->filter(function (ExamSession $s) use ($results) {
            if (in_array($s->risk_state, ['suspicious', 'critical', 'locked'], true)) {
                return true;
            }
            $r = $results->get($s->student_id);

            return $r && $r->status === 'held';
        })->count();

        $scoreStats = Result::query()
            ->where('quiz_id', (int) $exam->id)
            ->whereIn('status', ['graded', 'published'])
            ->selectRaw('AVG(score) as avg_score, MIN(score) as min_score, MAX(score) as max_score, COUNT(*) as c')
            ->first();

        $avgScore = $scoreStats && (int) ($scoreStats->c ?? 0) > 0 ? round((float) $scoreStats->avg_score, 2) : null;
        $minScore = $scoreStats && (int) ($scoreStats->c ?? 0) > 0 ? round((float) $scoreStats->min_score, 2) : null;
        $maxScore = $scoreStats && (int) ($scoreStats->c ?? 0) > 0 ? round((float) $scoreStats->max_score, 2) : null;

        $totalMarks = (float) ($exam->total_marks ?? 0);
        $passPct = null;
        $passThreshold = data_get($exam->proctoring_settings, 'pass_mark_percent');
        if (is_numeric($passThreshold) && $totalMarks > 0 && $scoreStats && (int) ($scoreStats->c ?? 0) > 0) {
            $need = ((float) $passThreshold / 100) * $totalMarks;
            $passPct = round(
                100 * Result::query()
                    ->where('quiz_id', (int) $exam->id)
                    ->whereIn('status', ['graded', 'published'])
                    ->where('score', '>=', $need)
                    ->count() / max(1, (int) $scoreStats->c),
                1,
            );
        }

        $durations = [];
        foreach ($submittedSessions as $s) {
            $r = $results->get($s->student_id);
            if ($r && $r->time_taken !== null && (int) $r->time_taken > 0) {
                $durations[] = (int) $r->time_taken;

                continue;
            }
            if ($s->start_time && $s->end_time) {
                $durations[] = max(0, $s->end_time->diffInSeconds($s->start_time));
            }
        }
        $avgDurationSec = $durations !== [] ? (int) round(array_sum($durations) / count($durations)) : null;

        $lateSubmissions = $exam->isAssignment()
            ? $submittedSessions->filter(fn (ExamSession $s) => (bool) $s->submitted_late)->count()
            : 0;

        return [
            'assigned_students' => $assigned,
            'started_students' => $started,
            'not_started' => $notStarted,
            'in_progress' => $inProgress,
            'submitted' => $submitted,
            'awaiting_grading' => $awaitingGrading,
            'graded' => $graded,
            'published_result' => $publishedResult,
            'held' => $held,
            'auto_submitted' => $autoSubmitted,
            'flagged_sessions' => $flagged,
            'avg_score' => $avgScore,
            'min_score' => $minScore,
            'max_score' => $maxScore,
            'pass_rate_percent' => $passPct,
            'pass_mark_percent_config' => is_numeric($passThreshold) ? (float) $passThreshold : null,
            'avg_completion_seconds' => $avgDurationSec,
            'late_submissions' => $lateSubmissions,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function assignmentExtras(Quiz $exam): ?array
    {
        if (! $exam->isAssignment()) {
            return null;
        }

        $sessionIds = ExamSession::query()
            ->where('exam_id', (int) $exam->id)
            ->where('status', 'submitted')
            ->pluck('id');

        $textCount = ExamSessionAnswer::query()
            ->whereIn('exam_session_id', $sessionIds)
            ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
            ->where(function ($q): void {
                $q->where(function ($q2): void {
                    $q2->whereNotNull('answer_text')->where('answer_text', '!=', '');
                })->orWhereNotNull('answer_payload');
            })
            ->distinct()
            ->count('exam_session_id');

        $fileSessionIds = AssignmentSubmissionFile::query()
            ->where('quiz_id', (int) $exam->id)
            ->distinct()
            ->pluck('exam_session_id');

        $fileCount = $fileSessionIds->count();

        $allowsFiles = (bool) ($exam->assignment_allows_files ?? false);
        $attachmentRequired = (bool) ($exam->assignment_attachment_required ?? false);
        $optionalUsed = 0;
        if ($allowsFiles && ! $attachmentRequired) {
            $optionalUsed = (int) AssignmentSubmissionFile::query()
                ->where('quiz_id', (int) $exam->id)
                ->select('exam_session_id')
                ->groupBy('exam_session_id')
                ->get()
                ->count();
        }

        $missingRequired = 0;
        if ($attachmentRequired) {
            $submitted = ExamSession::query()
                ->where('exam_id', (int) $exam->id)
                ->where('status', 'submitted')
                ->pluck('id');
            $withFiles = AssignmentSubmissionFile::query()
                ->whereIn('exam_session_id', $submitted)
                ->distinct()
                ->pluck('exam_session_id');
            $missingRequired = $submitted->diff($withFiles)->count();
        }

        $pasteAttempts = 0;
        if ((bool) ($exam->assignment_disable_paste ?? true)) {
            $pasteAttempts = ProctoringEvent::query()
                ->where('quiz_id', (int) $exam->id)
                ->where('event_type', 'essay_clipboard_attempt')
                ->count();
        }

        $awaitingRelease = 0;
        if ($exam->grades_released_at === null) {
            $awaitingRelease = Result::query()
                ->where('quiz_id', (int) $exam->id)
                ->where('status', 'graded')
                ->count();
        }

        return [
            'text_submissions' => $textCount,
            'file_submissions' => $fileCount,
            'optional_attachment_used' => $optionalUsed,
            'required_attachment_missing' => $missingRequired,
            'paste_attempts' => $pasteAttempts,
            'awaiting_feedback_release' => $awaitingRelease,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questionPerformance(Quiz $exam): array
    {
        $exam->loadMissing(['sections.questions']);
        $questions = Question::query()
            ->where('quiz_id', (int) $exam->id)
            ->where('pool_status', 'approved')
            ->with('section:id,title,section_order')
            ->orderBy('question_order')
            ->get();

        $sessionIds = ExamSession::query()
            ->where('exam_id', (int) $exam->id)
            ->where('status', 'submitted')
            ->pluck('id');

        $submittedSessionCount = $sessionIds->count();

        if ($sessionIds->isEmpty()) {
            return $questions->map(fn (Question $q) => $this->emptyQuestionRow($q))->all();
        }

        $answers = ExamSessionAnswer::query()
            ->whereIn('exam_session_id', $sessionIds)
            ->whereIn('question_id', $questions->pluck('id'))
            ->get(['exam_session_id', 'question_id', 'points_awarded', 'evaluation_status', 'answer_payload', 'evaluation_detail']);

        $grouped = $answers->groupBy('question_id');
        $rows = [];
        foreach ($questions as $q) {
            $rows[] = $this->buildQuestionRow($q, $grouped->get($q->id, collect()), $submittedSessionCount);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sectionPerformance(Quiz $exam, array $questionRows): array
    {
        $exam->loadMissing('sections');
        $bySection = collect($questionRows)->groupBy('section_id');
        $out = [];
        foreach ($exam->sections->sortBy('section_order') as $section) {
            $qs = $bySection->get($section->id, collect());
            if ($qs->isEmpty()) {
                continue;
            }
            $marks = (float) $qs->sum('marks');
            $avg = $qs->avg('avg_score');
            $min = $qs->min('min_score');
            $max = $qs->max('max_score');
            $weak = $qs->sortBy('avg_ratio')->first();
            $strong = $qs->sortByDesc('avg_ratio')->first();
            $topicRows = $this->topicPerformance($qs->values()->all());
            $out[] = [
                'section_id' => $section->id,
                'title' => $section->title,
                'question_count' => $qs->count(),
                'total_marks' => round($marks, 2),
                'avg_score' => $avg !== null ? round((float) $avg, 2) : null,
                'min_score' => $min !== null ? round((float) $min, 2) : null,
                'max_score' => $max !== null ? round((float) $max, 2) : null,
                'weakest_question' => $weak['preview'] ?? null,
                'strongest_question' => $strong['preview'] ?? null,
                'topic_performance' => $topicRows,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topicPerformance(array $questionRows): array
    {
        $withTopic = collect($questionRows)->filter(fn ($r) => filled($r['topic'] ?? null));
        if ($withTopic->isEmpty()) {
            return [];
        }

        return $withTopic
            ->groupBy('topic')
            ->map(function (Collection $rows, string $topic) {
                $avg = $rows->avg('avg_score');
                $avgRatio = $rows->avg('avg_ratio');

                return [
                    'topic' => $topic,
                    'question_count' => $rows->count(),
                    'avg_score' => $avg !== null ? round((float) $avg, 2) : null,
                    'weak' => $avgRatio !== null && (float) $avgRatio < 0.45,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function studentPerformanceRows(Quiz $exam, ?string $filter = null): Collection
    {
        $eligible = $this->eligibleStudentIds($exam);
        $latest = $this->latestSessionsByStudent($exam);
        $results = Result::query()
            ->where('quiz_id', (int) $exam->id)
            ->get()
            ->keyBy('user_id');

        $students = User::query()
            ->whereIn('id', $eligible->all())
            ->with(['classroom:id,name,section'])
            ->orderBy('name')
            ->get(['id', 'name', 'index_number', 'class_id']);

        $rows = collect();
        foreach ($students as $stu) {
            $session = $latest->get($stu->id);
            $result = $results->get($stu->id);
            $row = $this->buildStudentRow($exam, $stu, $session, $result);
            if ($this->studentRowMatchesFilter($exam, $row, $filter)) {
                $rows->push($row);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function proctoringBlock(Quiz $exam): array
    {
        $sessionIds = ExamSession::query()->where('exam_id', (int) $exam->id)->pluck('id');
        $studentIds = ExamSession::query()->where('exam_id', (int) $exam->id)->pluck('student_id')->unique();

        $events = ProctoringEvent::query()
            ->where('quiz_id', (int) $exam->id)
            ->count();

        $flaggedSessions = (int) ExamSession::query()
            ->where('exam_id', (int) $exam->id)
            ->where(function ($q): void {
                $q->whereIn('risk_state', ['suspicious', 'critical', 'locked'])
                    ->orWhereNotNull('auto_submit_reason_code');
            })
            ->select('student_id')
            ->groupBy('student_id')
            ->get()
            ->count();

        $auto = ExamSession::query()
            ->where('exam_id', (int) $exam->id)
            ->whereNotNull('auto_submit_reason_code')
            ->count();

        $held = Result::query()
            ->where('quiz_id', (int) $exam->id)
            ->where('status', 'held')
            ->count();

        $tabLimit = ExamSession::query()
            ->where('exam_id', (int) $exam->id)
            ->where('auto_submit_reason_code', 'tab_switch_limit')
            ->count();

        $phone = ProctoringEvent::query()
            ->where('quiz_id', (int) $exam->id)
            ->where('event_type', 'phone_detected')
            ->count();

        $face = ProctoringEvent::query()
            ->where('quiz_id', (int) $exam->id)
            ->whereIn('event_type', ['face_covered', 'face_obstructed', 'face_missing', 'face_not_clear'])
            ->count();

        $screenshot = ProctoringEvent::query()
            ->where('quiz_id', (int) $exam->id)
            ->where('event_type', 'possible_screenshot_attempt')
            ->count();

        $external = ProctoringEvent::query()
            ->where('quiz_id', (int) $exam->id)
            ->where('event_type', 'external_display_risk')
            ->count();

        $cameraLost = ProctoringEvent::query()
            ->where('quiz_id', (int) $exam->id)
            ->whereIn('event_type', ['camera_permission_revoked', 'camera_lost', 'camera_stopped'])
            ->count();

        $avgRisk = ExamSession::query()
            ->where('exam_id', (int) $exam->id)
            ->whereNotNull('violation_score')
            ->avg('violation_score');

        $timeline = ProctoringEvent::query()
            ->where('quiz_id', (int) $exam->id)
            ->whereIn('user_id', $studentIds->all())
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'user_id', 'event_type', 'severity', 'flagged', 'action_taken', 'metadata', 'created_at']);

        $userNames = User::query()
            ->whereIn('id', $timeline->pluck('user_id')->unique()->filter()->all())
            ->pluck('name', 'id');

        $timelineRows = $timeline->map(function (ProctoringEvent $e) use ($userNames) {
            $meta = is_array($e->metadata) ? $e->metadata : [];

            return [
                'at' => $e->created_at,
                'student' => (string) ($userNames[$e->user_id] ?? '—'),
                'event_type' => $e->event_type,
                'risk_level' => $e->severity ?? '—',
                'action' => $e->action_taken ?? '—',
                'summary' => $this->safeMetadataSummary($meta),
            ];
        })->values()->all();

        return [
            'event_total' => $events,
            'flagged_sessions' => $flaggedSessions,
            'auto_submitted_sessions' => $auto,
            'held_results' => $held,
            'tab_switch_limit' => $tabLimit,
            'phone_detected' => $phone,
            'face_events' => $face,
            'screenshot_attempts' => $screenshot,
            'external_display_risk' => $external,
            'camera_permission_lost' => $cameraLost,
            'avg_violation_score' => $avgRisk !== null ? round((float) $avgRisk, 2) : null,
            'timeline' => $timelineRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function safeMetadataSummary(array $meta): string
    {
        $payload = $meta['payload'] ?? $meta;
        if (! is_array($payload)) {
            return '';
        }
        $parts = [];
        foreach (['signal', 'detection_note', 'reason', 'keys', 'confidence'] as $k) {
            if (isset($payload[$k]) && (is_string($payload[$k]) || is_numeric($payload[$k]))) {
                $parts[] = $k.': '.(string) $payload[$k];
            }
        }
        $s = implode(' · ', $parts);

        return strlen($s) > 220 ? substr($s, 0, 217).'…' : $s;
    }

    /**
     * @return array<string, int>
     */
    public function coordinatorDepartmentIds(\App\Models\User $coordinator): array
    {
        return $coordinator->coordinatorAssignments()
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function coordinatorSnapshot(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return [
                'published_quizzes' => 0,
                'submitted_sessions' => 0,
                'pending_grading' => 0,
                'assignments_due_soon' => 0,
                'assignments_overdue' => 0,
                'missing_submissions' => 0,
                'examiner_active_assignments' => 0,
                'course_publish_counts' => collect(),
            ];
        }

        $courseIds = DB::table('courses')
            ->whereIn('department_id', $departmentIds)
            ->pluck('id');

        $publishedQuizzes = Quiz::query()
            ->where('status', 'published')
            ->whereIn('course_id', $courseIds)
            ->count();

        $submittedSessions = ExamSession::query()
            ->where('status', 'submitted')
            ->whereHas('exam', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->count();

        $pendingGrading = Result::query()
            ->where('status', 'pending_manual')
            ->whereHas('quiz', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->count();

        $now = now();
        $soon = $now->copy()->addDays(7);
        $assignmentsDueSoon = Quiz::query()
            ->where('assessment_type', 'assignment')
            ->where('status', 'published')
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$now, $soon])
            ->count();

        $assignmentsOverdue = Quiz::query()
            ->where('assessment_type', 'assignment')
            ->where('status', 'published')
            ->whereIn('course_id', $courseIds)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        $examinerAssignments = DB::table('examiner_course_assignments')
            ->where('is_active', true)
            ->whereIn('course_id', $courseIds)
            ->distinct()
            ->count('examiner_user_id');

        $missing = $this->coordinatorMissingSubmissionEstimate($departmentIds, $courseIds);

        $courseSummary = Quiz::query()
            ->selectRaw('course_id, COUNT(*) as c')
            ->whereIn('course_id', $courseIds)
            ->groupBy('course_id')
            ->pluck('c', 'course_id');

        return [
            'published_quizzes' => $publishedQuizzes,
            'submitted_sessions' => $submittedSessions,
            'pending_grading' => $pendingGrading,
            'assignments_due_soon' => $assignmentsDueSoon,
            'assignments_overdue' => $assignmentsOverdue,
            'missing_submissions' => $missing,
            'examiner_active_assignments' => $examinerAssignments,
            'course_publish_counts' => $courseSummary,
        ];
    }

    /**
     * Rough lower-bound: eligible students minus distinct submitters per published quiz (may double-count across quizzes).
     */
    private function coordinatorMissingSubmissionEstimate(array $departmentIds, Collection $courseIds): int
    {
        if ($courseIds->isEmpty()) {
            return 0;
        }

        $eligibleStudents = User::query()
            ->where('role', 'student')
            ->whereHas('program', fn ($q) => $q->whereIn('department_id', $departmentIds))
            ->whereNotNull('class_id')
            ->count();

        $submittedDistinct = (int) ExamSession::query()
            ->where('status', 'submitted')
            ->whereHas('exam', function ($q) use ($courseIds): void {
                $q->whereIn('course_id', $courseIds->all())
                    ->where('status', 'published');
            })
            ->select('student_id')
            ->groupBy('student_id')
            ->get()
            ->count();

        return max(0, $eligibleStudents - $submittedDistinct);
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSystemSnapshot(): array
    {
        return [
            'assessments_total' => Quiz::query()->count(),
            'submissions_total' => ExamSession::query()->where('status', 'submitted')->count(),
            'students_total' => User::query()->where('role', 'student')->count(),
            'examiners_total' => User::query()->where('role', 'examiner')->count(),
            'coordinators_total' => User::query()->where('role', 'coordinator')->count(),
            'universities' => DB::table('universities')->count(),
            'departments' => DB::table('departments')->count(),
            'classes_active' => DB::table('classes')->where('is_active', true)->count(),
            'by_assessment_type' => Quiz::query()
                ->selectRaw('assessment_type, COUNT(*) as c')
                ->groupBy('assessment_type')
                ->pluck('c', 'assessment_type')
                ->all(),
            'submissions_by_type' => ExamSession::query()
                ->where('exam_sessions.status', 'submitted')
                ->join('quizzes', 'quizzes.id', '=', 'exam_sessions.exam_id')
                ->selectRaw('quizzes.assessment_type as t, COUNT(*) as c')
                ->groupBy('quizzes.assessment_type')
                ->pluck('c', 't')
                ->all(),
            'flagged_sessions' => ExamSession::query()
                ->whereIn('risk_state', ['suspicious', 'critical', 'locked'])
                ->count(),
            'results_graded' => Result::query()->where('status', 'graded')->count(),
            'pending_manual' => Result::query()->where('status', 'pending_manual')->count(),
            'results_published' => Result::query()->where('status', 'published')->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coordinatorClassCompletionRows(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return [];
        }

        $classes = Classroom::query()
            ->where('is_active', true)
            ->whereHas('program', fn ($p) => $p->whereIn('department_id', $departmentIds))
            ->with(['program:id,name'])
            ->orderBy('name')
            ->limit(250)
            ->get();

        $rows = [];
        foreach ($classes as $class) {
            $courseIds = DB::table('class_course')->where('class_id', $class->id)->pluck('course_id');
            $publishedExams = $courseIds->isNotEmpty()
                ? Quiz::query()->whereIn('course_id', $courseIds)->where('status', 'published')->count()
                : 0;
            $studentCount = User::query()->where('class_id', $class->id)->where('role', 'student')->count();
            $submissions = $courseIds->isNotEmpty()
                ? ExamSession::query()
                    ->where('status', 'submitted')
                    ->whereHas('student', fn ($s) => $s->where('class_id', $class->id))
                    ->whereHas('exam', fn ($e) => $e->whereIn('course_id', $courseIds)->where('status', 'published'))
                    ->count()
                : 0;

            $rows[] = [
                'class_id' => $class->id,
                'class_label' => trim($class->name.' '.(string) ($class->section ?? '')),
                'program' => $class->program?->name ?? '—',
                'students' => $studentCount,
                'published_assessments' => $publishedExams,
                'submitted_session_count' => $submissions,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coordinatorCoursePerformanceRows(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return [];
        }

        $courses = Course::query()
            ->whereIn('department_id', $departmentIds)
            ->where('is_active', true)
            ->orderBy('code')
            ->limit(250)
            ->get();

        $rows = [];
        foreach ($courses as $course) {
            $published = Quiz::query()->where('course_id', $course->id)->where('status', 'published')->count();
            $pending = Result::query()
                ->where('status', 'pending_manual')
                ->whereHas('quiz', fn ($q) => $q->where('course_id', $course->id))
                ->count();
            $avg = Result::query()
                ->whereIn('status', ['graded', 'published'])
                ->whereHas('quiz', fn ($q) => $q->where('course_id', $course->id)->where('total_marks', '>', 0))
                ->avg('score');
            $publishedResults = Result::query()
                ->where('status', 'published')
                ->whereHas('quiz', fn ($q) => $q->where('course_id', $course->id))
                ->count();
            $gradedUnpublished = Result::query()
                ->where('status', 'graded')
                ->whereHas('quiz', fn ($q) => $q->where('course_id', $course->id))
                ->count();

            $rows[] = [
                'course_id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'published_assessments' => $published,
                'pending_grading' => $pending,
                'avg_score' => $avg !== null ? round((float) $avg, 2) : null,
                'results_published' => $publishedResults,
                'graded_unpublished' => $gradedUnpublished,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coordinatorExaminerActivityRows(array $departmentIds): array
    {
        $courseIds = Course::query()->whereIn('department_id', $departmentIds)->pluck('id');
        if ($courseIds->isEmpty()) {
            return [];
        }

        $examinerIds = DB::table('examiner_course_assignments')
            ->where('is_active', true)
            ->whereIn('course_id', $courseIds)
            ->distinct()
            ->pluck('examiner_user_id');

        $rows = [];
        foreach ($examinerIds as $eid) {
            $user = User::query()->find((int) $eid);
            if (! $user) {
                continue;
            }
            $coursesAssigned = (int) DB::table('examiner_course_assignments')
                ->where('examiner_user_id', (int) $eid)
                ->where('is_active', true)
                ->whereIn('course_id', $courseIds)
                ->distinct()
                ->pluck('course_id')
                ->unique()
                ->count();
            $pending = Result::query()
                ->where('status', 'pending_manual')
                ->whereHas('quiz', fn ($q) => $q->where('created_by', (int) $eid)->whereIn('course_id', $courseIds))
                ->count();

            $rows[] = [
                'examiner_id' => (int) $eid,
                'name' => $user->name,
                'courses_assigned' => $coursesAssigned,
                'pending_grading' => $pending,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $rows;
    }

    private function emptyQuestionRow(Question $q): array
    {
        return [
            'question_id' => $q->id,
            'section_id' => $q->section_id,
            'preview' => str($q->question_text)->limit(120)->toString(),
            'type' => $q->type,
            'section' => $q->section?->title ?? '—',
            'topic' => (string) data_get($q->metadata, 'topic', ''),
            'marks' => (float) $q->marks,
            'answered' => 0,
            'correct' => 0,
            'wrong' => 0,
            'unanswered' => 0,
            'avg_score' => null,
            'difficulty' => '—',
            'mcq_distribution' => [],
            'mcq_correct_label' => null,
            'mcq_most_wrong_label' => null,
            'essay_graded' => 0,
            'essay_pending' => 0,
            'essay_avg' => null,
            'avg_ratio' => null,
        ];
    }

    /**
     * @param  Collection<int, ExamSessionAnswer>  $ans
     * @return array<string, mixed>
     */
    private function buildQuestionRow(Question $q, Collection $ans, int $submittedSessionCount): array
    {
        $answered = $ans->count();
        $answeredSessions = $ans->pluck('exam_session_id')->unique()->count();
        $marks = max(0.00001, (float) $q->marks);
        $avgPoints = $answered > 0 ? round((float) $ans->avg('points_awarded'), 3) : null;
        $ratio = $avgPoints !== null ? $avgPoints / $marks : null;

        $correct = 0;
        $wrong = 0;
        if ($q->isMCQ() || $q->isTrueFalse() || $q->isFillBlank()) {
            foreach ($ans as $a) {
                $detail = is_array($a->evaluation_detail ?? null) ? $a->evaluation_detail : [];
                if (array_key_exists('correct', $detail) && $detail['correct'] === true) {
                    $correct++;
                } elseif (array_key_exists('correct', $detail) && $detail['correct'] === false) {
                    $wrong++;
                } elseif ((float) $a->points_awarded + 1e-6 >= $marks) {
                    $correct++;
                } elseif ((float) $a->points_awarded <= 0.0) {
                    $wrong++;
                }
            }
        }

        $unanswered = max(0, $submittedSessionCount - $answeredSessions);

        $difficulty = '—';
        if ($ratio !== null) {
            if ($ratio >= 0.7) {
                $difficulty = 'easy';
            } elseif ($ratio >= 0.4) {
                $difficulty = 'moderate';
            } else {
                $difficulty = 'difficult';
            }
        }

        $correctIndices = $this->normalizedCorrectOptionIndices($q->correct_answer);
        $mcqCorrectLabel = null;
        $mcqMostWrongLabel = null;
        $mcqDistribution = [];
        if ($q->isMCQ() && is_array($q->options)) {
            $dist = [];
            foreach ($ans as $a) {
                $pl = $a->answer_payload ?? [];
                if (! is_array($pl)) {
                    continue;
                }
                $sel = $pl['selected'] ?? null;
                $indices = [];
                if (is_array($sel)) {
                    foreach ($sel as $v) {
                        if (is_int($v) || (is_string($v) && ctype_digit((string) $v))) {
                            $indices[] = (int) $v;
                        }
                    }
                } elseif (is_int($sel) || (is_string($sel) && ctype_digit((string) $sel))) {
                    $indices[] = (int) $sel;
                }
                foreach ($indices as $ix) {
                    $dist[$ix] = ($dist[$ix] ?? 0) + 1;
                }
            }
            foreach ($dist as $idx => $cnt) {
                $label = (string) ($q->options[$idx] ?? ('#'.$idx));
                $mcqDistribution[] = ['index' => $idx, 'label' => $label, 'count' => $cnt];
            }
            if ($correctIndices !== []) {
                $labels = [];
                foreach ($correctIndices as $ix) {
                    $labels[] = (string) ($q->options[$ix] ?? ('#'.$ix));
                }
                $mcqCorrectLabel = implode(', ', $labels);
            }
            $wrongCandidates = [];
            foreach ($dist as $idx => $cnt) {
                if (! in_array((int) $idx, $correctIndices, true)) {
                    $wrongCandidates[(int) $idx] = $cnt;
                }
            }
            if ($wrongCandidates !== []) {
                arsort($wrongCandidates);
                $worstIdx = (int) array_key_first($wrongCandidates);
                $mcqMostWrongLabel = (string) ($q->options[$worstIdx] ?? ('#'.$worstIdx));
            }
        }

        $essayGraded = 0;
        $essayPending = 0;
        $essayScores = [];
        if ($q->isEssay()) {
            foreach ($ans as $a) {
                if ($a->evaluation_status === 'pending_manual') {
                    $essayPending++;
                } elseif (in_array($a->evaluation_status, ['manual_graded', 'auto_scored'], true)) {
                    $essayGraded++;
                    $essayScores[] = (float) $a->points_awarded;
                }
            }
        }

        return [
            'question_id' => $q->id,
            'section_id' => $q->section_id,
            'preview' => str($q->question_text)->limit(120)->toString(),
            'type' => $q->type,
            'section' => $q->section?->title ?? '—',
            'topic' => (string) data_get($q->metadata, 'topic', ''),
            'marks' => (float) $q->marks,
            'answered' => $answered,
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'avg_score' => $avgPoints,
            'difficulty' => $difficulty,
            'mcq_distribution' => $mcqDistribution,
            'mcq_correct_label' => $mcqCorrectLabel,
            'mcq_most_wrong_label' => $mcqMostWrongLabel,
            'essay_graded' => $essayGraded,
            'essay_pending' => $essayPending,
            'essay_avg' => $essayScores !== [] ? round(array_sum($essayScores) / count($essayScores), 2) : null,
            'avg_ratio' => $ratio,
        ];
    }

    /**
     * @return list<int>
     */
    private function normalizedCorrectOptionIndices(mixed $correct): array
    {
        if ($correct === null) {
            return [];
        }
        if (is_int($correct) || (is_string($correct) && ctype_digit((string) $correct))) {
            return [(int) $correct];
        }
        if (is_array($correct)) {
            $out = [];
            foreach ($correct as $v) {
                if (is_int($v) || (is_string($v) && ctype_digit((string) $v))) {
                    $out[] = (int) $v;
                }
            }

            return array_values(array_unique($out));
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStudentRow(Quiz $exam, User $stu, ?ExamSession $session, ?Result $result): array
    {
        $status = 'not_started';
        if ($session) {
            if ($session->status === 'submitted') {
                $status = 'submitted';
            } else {
                $status = 'in_progress';
            }
        }

        $resultStatus = $result?->status;
        $durationSec = null;
        if ($session?->start_time && $session?->end_time) {
            $durationSec = max(0, $session->end_time->diffInSeconds($session->start_time));
        }
        if ($result?->time_taken) {
            $durationSec = (int) $result->time_taken;
        }

        $pct = null;
        if ($result && $exam->total_marks > 0) {
            $pct = round(100 * (float) $result->score / (float) $exam->total_marks, 1);
        }

        return [
            'student_id' => $stu->id,
            'name' => $stu->name,
            'index_number' => $stu->index_number,
            'class' => $stu->classroom ? trim($stu->classroom->name.' '.$stu->classroom->section) : '—',
            'session_status' => $status,
            'exam_session_id' => $session?->id,
            'session_id' => $session?->session_id,
            'started_at' => $session?->start_time,
            'submitted_at' => $session?->end_time,
            'duration_seconds' => $durationSec,
            'score' => $result?->score,
            'percentage' => $pct,
            'result_status' => $resultStatus,
            'risk_state' => $session?->risk_state,
            'auto_submit_reason' => $session?->auto_submit_reason_code,
        ];
    }

    private function studentRowMatchesFilter(Quiz $exam, array $row, ?string $filter): bool
    {
        if ($filter === null || $filter === '' || $filter === 'all') {
            return true;
        }

        $isAssignment = $exam->isAssignment();
        $assessmentType = (string) ($exam->assessment_type ?? 'exam');

        return match ($filter) {
            'submitted' => $row['session_status'] === 'submitted',
            'not_submitted' => $row['session_status'] !== 'submitted',
            'pending_grading' => $row['result_status'] === 'pending_manual',
            'graded' => $row['result_status'] === 'graded',
            'published' => $row['result_status'] === 'published',
            'held' => $row['result_status'] === 'held',
            'flagged' => in_array($row['risk_state'], ['suspicious', 'critical', 'locked'], true)
                || $row['result_status'] === 'held',
            'auto_submitted' => filled($row['auto_submit_reason']),
            'assignment_only' => $isAssignment,
            'quiz_exam_only' => ! $isAssignment && in_array($assessmentType, ['quiz', 'exam', 'mid'], true),
            default => true,
        };
    }
}
