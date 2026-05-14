<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuiz;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use App\Services\PracticeModuleSettings;
use App\Services\StudentDashboardDigestService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user->role === 'admin') {
            return app(Admin\DashboardController::class)->index();
        }

        if ($user->role === 'coordinator') {
            return app(Coordinator\DashboardController::class)->index();
        }

        if ($user->role === 'examiner') {
            return redirect()->route('examiner.dashboard');
        }

        if ($user->role !== 'student') {
            return view('dashboard', [
                'user' => $user,
                'stats' => [],
            ]);
        }

        $practice = app(PracticeModuleSettings::class);
        $data = $this->buildStudentDashboardData($user, $practice);
        $digest = app(StudentDashboardDigestService::class)->forStudent($user, $practice);
        User::query()->whereKey($user->id)->update(['student_last_dashboard_at' => now()]);

        return view('student.dashboard', array_merge($data, $digest));
    }

    public function dismissStudentPolicyNotice(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === 'student', 403);

        $version = (int) config('student-dashboard.policy.version', 0);
        if ($version > 0) {
            $user->forceFill(['policy_notice_ack_version' => $version])->save();
        }

        return redirect()->route('dashboard');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStudentDashboardData(User $user, PracticeModuleSettings $practiceSettings): array
    {
        $now = Carbon::now();

        $user->loadMissing(['program', 'level', 'classroom', 'university']);

        $activeYearId = AcademicYear::activeForUniversity((int) $user->university_id)?->id;

        $classYearOk = true;
        if ($user->class_id !== null && $activeYearId !== null) {
            $cid = Classroom::query()->whereKey($user->class_id)->value('academic_year_id');
            $classYearOk = $cid === null || (int) $cid === (int) $activeYearId;
        }

        $activeSession = ExamSession::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['exam.course:id,code,title'])
            ->first();

        $courseIds = collect();
        if ($user->class_id !== null) {
            $courseIds = DB::table('class_course')
                ->where('class_id', $user->class_id)
                ->pluck('course_id');
        }

        $publishedExams = collect();
        if ($courseIds->isNotEmpty()) {
            $publishedExams = Quiz::query()
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
                ->orderBy('start_time')
                ->orderBy('title')
                ->get();
        }

        $submittedExamIds = ExamSession::query()
            ->where('student_id', $user->id)
            ->where('status', 'submitted')
            ->pluck('exam_id');

        $examIds = $publishedExams->pluck('id');
        $sessionsLatestByExam = collect();
        if ($examIds->isNotEmpty()) {
            $sessionsLatestByExam = ExamSession::query()
                ->where('student_id', $user->id)
                ->whereIn('exam_id', $examIds)
                ->orderByDesc('id')
                ->get()
                ->unique('exam_id')
                ->keyBy('exam_id');
        }
        $resultsByQuiz = collect();
        if ($examIds->isNotEmpty()) {
            $resultsByQuiz = Result::query()
                ->where('user_id', $user->id)
                ->whereIn('quiz_id', $examIds)
                ->get()
                ->keyBy('quiz_id');
        }

        $studentAssessmentDeck = $this->buildStudentAssessmentDeck(
            $publishedExams,
            $sessionsLatestByExam,
            $resultsByQuiz,
            $now,
            $activeSession,
        );

        $availableNow = collect();
        $upcoming = collect();

        foreach ($publishedExams as $exam) {
            if ($submittedExamIds->contains($exam->id)) {
                continue;
            }

            if ($activeSession !== null && (int) $activeSession->exam_id !== (int) $exam->id) {
                continue;
            }

            $from = $exam->start_time;
            $to = $exam->end_time;

            if ($from !== null && $now->lt($from)) {
                $upcoming->push($exam);

                continue;
            }

            if ($to !== null && $now->gt($to)) {
                continue;
            }

            $availableNow->push($exam);
        }

        $availableNow = $availableNow->sort(function (Quiz $a, Quiz $b) {
            $aEnd = $a->end_time?->timestamp;
            $bEnd = $b->end_time?->timestamp;
            $aKey = $aEnd ?? PHP_INT_MAX;
            $bKey = $bEnd ?? PHP_INT_MAX;
            if ($aKey !== $bKey) {
                return $aKey <=> $bKey;
            }

            return ($b->start_time?->timestamp ?? 0) <=> ($a->start_time?->timestamp ?? 0);
        })->values();

        $upcoming = $upcoming->sortBy(function (Quiz $exam) {
            return $exam->start_time?->timestamp ?? PHP_INT_MAX;
        })->values();

        $gradedCount = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'graded')
            ->when($activeYearId !== null, function ($q) use ($activeYearId) {
                $q->where(function ($q2) use ($activeYearId) {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $activeYearId);
                });
            })
            ->count();

        $heldResults = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'held')
            ->when($activeYearId !== null, function ($q) use ($activeYearId) {
                $q->where(function ($q2) use ($activeYearId) {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $activeYearId);
                });
            })
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $pendingManual = Result::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending_manual')
            ->when($activeYearId !== null, function ($q) use ($activeYearId) {
                $q->where(function ($q2) use ($activeYearId) {
                    $q2->whereNull('academic_year_id')
                        ->orWhere('academic_year_id', $activeYearId);
                });
            })
            ->with(['quiz:id,title'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $practiceEnabled = $practiceSettings->studentPracticeEnabled();
        $practiceQuizCount = 0;
        $recentPracticeScores = collect();
        if ($practiceEnabled) {
            $practiceQuizCount = PracticeQuiz::query()->where('student_id', $user->id)->count();
            $recentPracticeScores = PracticeAttempt::query()
                ->where('student_id', $user->id)
                ->whereNotNull('submitted_at')
                ->with(['practiceQuiz.course:id,code,title'])
                ->orderByDesc('submitted_at')
                ->limit(3)
                ->get();
        }

        $heroAwaitingResult = null;
        if ($activeSession === null && $availableNow->isEmpty() && $upcoming->isEmpty()) {
            $heroAwaitingResult = Result::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending_manual', 'held'])
                ->when($activeYearId !== null, function ($q) use ($activeYearId) {
                    $q->where(function ($q2) use ($activeYearId) {
                        $q2->whereNull('academic_year_id')
                            ->orWhere('academic_year_id', $activeYearId);
                    });
                })
                ->with(['quiz:id,title', 'quiz.course:id,code,title'])
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->first();
        }

        return [
            'user' => $user,
            'activeSession' => $activeSession,
            'availableExams' => $availableNow,
            'upcomingExams' => $upcoming,
            'gradedResultsCount' => $gradedCount,
            'heldResults' => $heldResults,
            'pendingManualResults' => $pendingManual,
            'classYearOk' => $classYearOk,
            'practiceEnabled' => $practiceEnabled,
            'practiceQuizCount' => $practiceQuizCount,
            'recentPracticeScores' => $recentPracticeScores,
            'submittedExamsCount' => $submittedExamIds->unique()->count(),
            'heroAwaitingResult' => $heroAwaitingResult,
            'studentAssessmentDeck' => $studentAssessmentDeck,
        ];
    }

    /**
     * @param  Collection<int, Quiz>  $publishedExams
     * @param  Collection<int, ExamSession>  $sessionsLatestByExam
     * @param  Collection<int, Result>  $resultsByQuiz
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildStudentAssessmentDeck(
        Collection $publishedExams,
        Collection $sessionsLatestByExam,
        Collection $resultsByQuiz,
        Carbon $now,
        ?ExamSession $activeSession,
    ): array {
        $sections = [
            'active' => [],
            'upcoming' => [],
            'assignments_due' => [],
            'submitted_pending' => [],
            'released' => [],
            'closed' => [],
        ];

        foreach ($publishedExams as $exam) {
            /** @var Quiz $exam */
            $session = $sessionsLatestByExam->get($exam->id);
            $result = $resultsByQuiz->get($exam->id);
            $row = $this->mapStudentAssessmentDeckRow($exam, $session, $result, $now, $activeSession);
            if ($row === null) {
                continue;
            }
            $key = $row['section'];
            unset($row['section']);
            $sections[$key][] = $row;
        }

        return $sections;
    }

    /**
     * @return (array<string, mixed>&array{section: string})|null
     */
    private function mapStudentAssessmentDeckRow(
        Quiz $exam,
        ?ExamSession $session,
        ?Result $result,
        Carbon $now,
        ?ExamSession $activeSession,
    ): ?array {
        $isAssignment = $exam->isAssignment();
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

        $allowsText = (bool) ($exam->assignment_allows_text ?? true);
        $allowsFiles = (bool) ($exam->assignment_allows_files ?? false);
        if ($isAssignment) {
            $attachmentReq = (bool) ($exam->assignment_attachment_required ?? false);
            $submissionFormat = match (true) {
                $allowsText && $allowsFiles && $attachmentReq => __('Text and file (required upload)'),
                $allowsText && $allowsFiles => __('Text and optional file'),
                $allowsText => __('Text only'),
                $allowsFiles => __('File only'),
                default => __('—'),
            };
        } else {
            $submissionFormat = __('In-app questions');
        }

        $dueLine = $isAssignment && $exam->due_at
            ? __('Due :d', ['d' => $exam->due_at->timezone((string) config('app.timezone'))->format('M j, H:i')])
            : ($exam->end_time
                ? __('Closes :d', ['d' => $exam->end_time->timezone((string) config('app.timezone'))->format('M j, H:i')])
                : null);

        $inProgress = $session !== null && in_array($session->status, ['active', 'paused'], true);
        $submitted = $session !== null && $session->status === 'submitted';

        if ($inProgress) {
            return [
                'section' => 'active',
                'title' => (string) $exam->title,
                'course_line' => $courseLine,
                'type_label' => $typeLabel,
                'submission_format' => $submissionFormat,
                'due_line' => $dueLine,
                'status_label' => __('In progress'),
                'action_label' => __('Continue'),
                'action_href' => route('student.exam.take', $session),
                'is_assignment' => $isAssignment,
            ];
        }

        if ($submitted && $session !== null) {
            $rStatus = (string) ($result?->status ?? 'pending_manual');
            if ($rStatus === 'held') {
                return [
                    'section' => 'submitted_pending',
                    'title' => (string) $exam->title,
                    'course_line' => $courseLine,
                    'type_label' => $typeLabel,
                    'submission_format' => $submissionFormat,
                    'due_line' => $dueLine,
                    'status_label' => __('Held for review'),
                    'action_label' => __('Awaiting review'),
                    'action_href' => route('student.results.show', $session),
                    'is_assignment' => $isAssignment,
                ];
            }
            if ($rStatus === 'pending_manual') {
                return [
                    'section' => 'submitted_pending',
                    'title' => (string) $exam->title,
                    'course_line' => $courseLine,
                    'type_label' => $typeLabel,
                    'submission_format' => $submissionFormat,
                    'due_line' => $dueLine,
                    'status_label' => __('Awaiting grading'),
                    'action_label' => $isAssignment ? __('View submission') : __('View status'),
                    'action_href' => route('student.results.show', $session),
                    'is_assignment' => $isAssignment,
                ];
            }
            if ($rStatus === 'graded' && $isAssignment && ! $exam->assignmentGradesVisibleToStudents()) {
                return [
                    'section' => 'submitted_pending',
                    'title' => (string) $exam->title,
                    'course_line' => $courseLine,
                    'type_label' => $typeLabel,
                    'submission_format' => $submissionFormat,
                    'due_line' => $dueLine,
                    'status_label' => __('Graded — results not released yet'),
                    'action_label' => __('View submission'),
                    'action_href' => route('student.results.show', $session),
                    'is_assignment' => true,
                ];
            }
            if ($rStatus === 'graded') {
                return [
                    'section' => 'released',
                    'title' => (string) $exam->title,
                    'course_line' => $courseLine,
                    'type_label' => $typeLabel,
                    'submission_format' => $submissionFormat,
                    'due_line' => $dueLine,
                    'status_label' => __('Result released'),
                    'action_label' => __('View result'),
                    'action_href' => route('student.results.show', $session),
                    'is_assignment' => $isAssignment,
                ];
            }

            return [
                'section' => 'submitted_pending',
                'title' => (string) $exam->title,
                'course_line' => $courseLine,
                'type_label' => $typeLabel,
                'submission_format' => $submissionFormat,
                'due_line' => $dueLine,
                'status_label' => __('Submitted'),
                'action_label' => __('View status'),
                'action_href' => route('student.results.show', $session),
                'is_assignment' => $isAssignment,
            ];
        }

        if (! $exam->isAvailableForStudentToStart($now)) {
            if (! $submitted) {
                return [
                    'section' => 'closed',
                    'title' => (string) $exam->title,
                    'course_line' => $courseLine,
                    'type_label' => $typeLabel,
                    'submission_format' => $submissionFormat,
                    'due_line' => $dueLine,
                    'status_label' => $isAssignment ? __('Missed or closed') : __('Closed'),
                    'action_label' => __('Closed'),
                    'action_href' => null,
                    'is_assignment' => $isAssignment,
                ];
            }

            return null;
        }

        if ($activeSession !== null && (int) $activeSession->exam_id !== (int) $exam->id) {
            return null;
        }

        $from = $exam->start_time;
        if ($from !== null && $now->lt($from)) {
            return [
                'section' => 'upcoming',
                'title' => (string) $exam->title,
                'course_line' => $courseLine,
                'type_label' => $typeLabel,
                'submission_format' => $submissionFormat,
                'due_line' => $dueLine,
                'status_label' => __('Not started'),
                'action_label' => __('Opens soon'),
                'action_href' => route('student.exam.prepare', $exam),
                'is_assignment' => $isAssignment,
            ];
        }

        if ($isAssignment) {
            return [
                'section' => 'assignments_due',
                'title' => (string) $exam->title,
                'course_line' => $courseLine,
                'type_label' => $typeLabel,
                'submission_format' => $submissionFormat,
                'due_line' => $dueLine,
                'status_label' => __('Not started'),
                'action_label' => __('View assignment'),
                'action_href' => route('student.exam.prepare', $exam),
                'is_assignment' => true,
            ];
        }

        $startLabel = match ((string) ($exam->assessment_type ?? 'exam')) {
            'quiz' => __('Start quiz'),
            'exam' => __('Start exam'),
            'mid' => __('Start mid-semester'),
            default => __('Start assessment'),
        };

        return [
            'section' => 'active',
            'title' => (string) $exam->title,
            'course_line' => $courseLine,
            'type_label' => $typeLabel,
            'submission_format' => $submissionFormat,
            'due_line' => $dueLine,
            'status_label' => __('Not started'),
            'action_label' => $startLabel,
            'action_href' => route('student.exam.prepare', $exam),
            'is_assignment' => false,
        ];
    }
}
