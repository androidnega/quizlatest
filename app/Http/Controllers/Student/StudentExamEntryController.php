<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ProctoringGlobalControlService;
use App\Services\StudentExamSessionGateService;
use App\Services\SystemExamPolicyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StudentExamEntryController extends Controller
{
    public function __construct(
        private readonly SystemExamPolicyService $examPolicy,
        private readonly ProctoringGlobalControlService $globalControl,
        private readonly StudentExamSessionGateService $sessionGate,
    ) {}

    public function instructions(Quiz $quiz): View|RedirectResponse
    {
        return $this->renderExamGateView($quiz, 'student.exam.instructions');
    }

    public function prepare(Quiz $quiz): View|RedirectResponse
    {
        return $this->renderExamGateView($quiz, 'student.exam.prepare');
    }

    /**
     * @param  view-string  $view
     */
    private function renderExamGateView(Quiz $quiz, string $view): View|RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'student', 403);

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'index_number' => __('Your student account is not active. Please contact your coordinator.'),
            ]);
        }

        if ($user->student_onboarded_at === null) {
            return redirect()->route('login')->withErrors([
                'index_number' => __('Please complete your student onboarding before starting an exam.'),
            ]);
        }

        $this->assertEligibleForExamPage($user, $quiz);

        if ($redirect = $this->redirectIfAlreadySubmitted($user, $quiz)) {
            return $redirect;
        }

        $blocking = $this->sessionGate->blockingSessionFor($user, $quiz);

        if ($blocking !== null) {
            if ((int) $blocking->exam_id === (int) $quiz->id) {
                return redirect()->route('student.exam.take', $blocking);
            }

            return redirect()->route('dashboard')
                ->withErrors(['exam' => __('You already have a timed assessment in progress. Finish or submit it before starting another.')]);
        }

        $quiz->load(['course:id,code,title']);

        if ($view === 'student.exam.prepare') {
            return view($view, [
                'quiz' => $quiz,
                'isAssignment' => $quiz->isAssignment(),
                'snapshotRequired' => $this->examPolicy->isExamStartSnapshotRequiredForQuiz($quiz),
                'entryBlocked' => $this->globalControl->blocksExamStarts(),
            ]);
        }

        return view($view, [
            'quiz' => $quiz,
            'isAssignment' => $quiz->isAssignment(),
        ]);
    }

    /**
     * @param  User  $user
     */
    private function assertEligibleForExamPage($user, Quiz $quiz): void
    {
        abort_unless((int) $quiz->university_id === (int) $user->university_id, 403);

        $activeAyId = AcademicYear::activeForUniversity((int) $user->university_id)?->id;
        if ($quiz->academic_year_id !== null && $activeAyId !== null && (int) $quiz->academic_year_id !== (int) $activeAyId) {
            abort(403, 'This exam is not offered in the current academic period.');
        }

        if ($user->class_id !== null && $quiz->academic_year_id !== null) {
            $classAy = Classroom::query()->whereKey($user->class_id)->value('academic_year_id');
            if ($classAy !== null && (int) $classAy !== (int) $quiz->academic_year_id) {
                abort(403, 'Your class is not enrolled for this exam period.');
            }
        }

        abort_unless($quiz->status === 'published', 403, 'This exam is not available.');
        abort_unless($quiz->isAvailableForStudentToStart(now()), 403, 'This exam is outside its scheduled window.');

        abort_if($user->class_id === null, 403, __('student_ui.class_group_not_assigned'));

        $hasCourse = DB::table('class_course')
            ->where('class_id', $user->class_id)
            ->where('course_id', $quiz->course_id)
            ->exists();
        abort_unless($hasCourse, 403);

        if ($quiz->targetClassrooms()->exists()) {
            abort_unless(
                $quiz->targetClassrooms()->where('classes.id', (int) $user->class_id)->exists(),
                403,
                __('This quiz is not assigned to your class group.')
            );
        }

    }

    private function redirectIfAlreadySubmitted(User $user, Quiz $quiz): ?RedirectResponse
    {
        $session = ExamSession::query()
            ->where('student_id', $user->id)
            ->where('exam_id', $quiz->id)
            ->where('status', 'submitted')
            ->orderByDesc('id')
            ->first();

        if ($session === null) {
            return null;
        }

        return redirect()
            ->route('student.results.show', $session)
            ->with('status', __('You have already submitted this assessment.'));
    }
}
