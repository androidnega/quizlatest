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
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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

        return view('student.dashboard', $this->buildStudentDashboardData($user, app(PracticeModuleSettings::class)));
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
                ->with(['course:id,code,title'])
                ->orderBy('start_time')
                ->orderBy('title')
                ->get();
        }

        $submittedExamIds = ExamSession::query()
            ->where('student_id', $user->id)
            ->where('status', 'submitted')
            ->pluck('exam_id');

        $latestSessionsByExamId = collect();
        if ($publishedExams->isNotEmpty()) {
            $latestSessionsByExamId = ExamSession::query()
                ->where('student_id', $user->id)
                ->whereIn('exam_id', $publishedExams->pluck('id'))
                ->orderByDesc('id')
                ->get()
                ->unique('exam_id')
                ->keyBy('exam_id');
        }

        $examRows = $this->buildStudentExamRows(
            $publishedExams,
            $now,
            $activeSession,
            $submittedExamIds,
            $latestSessionsByExamId,
        );

        $coursesWithExams = $examRows
            ->groupBy('course_id')
            ->map(fn (Collection $rows) => $rows->sortBy(fn (array $r) => [
                $r['exam']->start_time?->timestamp ?? 0,
                $r['exam']->title,
            ])->values());

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

        return [
            'user' => $user,
            'activeSession' => $activeSession,
            'availableExams' => $availableNow,
            'upcomingExams' => $upcoming,
            'gradedResultsCount' => $gradedCount,
            'heldResults' => $heldResults,
            'pendingManualResults' => $pendingManual,
            'hasClass' => $user->class_id !== null,
            'studentProfileReady' => $user->student_onboarded_at !== null,
            'classYearOk' => $classYearOk,
            'practiceEnabled' => $practiceEnabled,
            'practiceQuizCount' => $practiceQuizCount,
            'recentPracticeScores' => $recentPracticeScores,
            'examRows' => $examRows,
            'coursesWithExams' => $coursesWithExams,
            'submittedExamsCount' => $submittedExamIds->unique()->count(),
        ];
    }

    /**
     * @param  Collection<int, Quiz>  $publishedExams
     * @param  Collection<int, mixed>  $submittedExamIds
     * @param  Collection<int, ExamSession>  $latestSessionsByExamId
     * @return Collection<int, array{exam: Quiz, course_id: int, state: string, progress: int, href: ?string, label: string}>
     */
    private function buildStudentExamRows(
        Collection $publishedExams,
        Carbon $now,
        ?ExamSession $activeSession,
        Collection $submittedExamIds,
        Collection $latestSessionsByExamId,
    ): Collection {
        $rows = collect();

        foreach ($publishedExams as $exam) {
            $from = $exam->start_time;
            $to = $exam->end_time;
            $courseId = (int) $exam->course_id;
            $session = $latestSessionsByExamId->get($exam->id);

            if ($submittedExamIds->contains($exam->id)) {
                $rows->push([
                    'exam' => $exam,
                    'course_id' => $courseId,
                    'state' => 'completed',
                    'progress' => 100,
                    'href' => $session !== null ? route('student.results.show', $session, false) : route('student.results.index', [], false),
                    'label' => __('Submitted'),
                ]);

                continue;
            }

            if ($activeSession !== null && (int) $activeSession->exam_id === (int) $exam->id) {
                $rows->push([
                    'exam' => $exam,
                    'course_id' => $courseId,
                    'state' => 'in_progress',
                    'progress' => 62,
                    'href' => route('student.exam.take', $activeSession, false),
                    'label' => __('In progress'),
                ]);

                continue;
            }

            if ($activeSession !== null && (int) $activeSession->exam_id !== (int) $exam->id) {
                $rows->push([
                    'exam' => $exam,
                    'course_id' => $courseId,
                    'state' => 'blocked',
                    'progress' => 0,
                    'href' => null,
                    'label' => __('Finish your current exam first'),
                ]);

                continue;
            }

            if ($from !== null && $now->lt($from)) {
                $rows->push([
                    'exam' => $exam,
                    'course_id' => $courseId,
                    'state' => 'upcoming',
                    'progress' => 0,
                    'href' => null,
                    'label' => __('Not open yet'),
                ]);

                continue;
            }

            if ($to !== null && $now->gt($to)) {
                $rows->push([
                    'exam' => $exam,
                    'course_id' => $courseId,
                    'state' => 'closed',
                    'progress' => 0,
                    'href' => route('student.results.index', [], false),
                    'label' => __('Window closed'),
                ]);

                continue;
            }

            $rows->push([
                'exam' => $exam,
                'course_id' => $courseId,
                'state' => 'available',
                'progress' => 12,
                'href' => route('student.exam.prepare', $exam, false),
                'label' => __('Ready to start'),
            ]);
        }

        return $rows;
    }
}
