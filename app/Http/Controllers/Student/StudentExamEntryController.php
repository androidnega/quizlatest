<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ProctoringGlobalControlService;
use App\Services\SystemExamPolicyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StudentExamEntryController extends Controller
{
    public function __construct(
        private readonly SystemExamPolicyService $examPolicy,
        private readonly ProctoringGlobalControlService $globalControl,
    ) {}

    public function prepare(Quiz $quiz): View|RedirectResponse
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

        $active = ExamSession::query()
            ->where('student_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->first();

        if ($active !== null) {
            if ((int) $active->exam_id === (int) $quiz->id) {
                return redirect()->route('student.exam.take', $active);
            }

            return redirect()->route('dashboard')
                ->withErrors(['exam' => __('You already have an exam in progress. Finish or submit it before starting another.')]);
        }

        $quiz->load(['course:id,code,title']);

        return view('student.exam.prepare', [
            'quiz' => $quiz,
            'otpEnabled' => $this->examPolicy->isOtpEnabled(),
            'smsEnabled' => $this->examPolicy->isSmsEnabled(),
            'snapshotRequired' => $this->examPolicy->isExamStartSnapshotRequired(),
            'otpExpirySeconds' => $this->examPolicy->getOtpExpirySeconds(),
            'entryBlocked' => $this->globalControl->blocksExamStarts(),
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

        $alreadySubmitted = ExamSession::query()
            ->where('student_id', $user->id)
            ->where('exam_id', $quiz->id)
            ->where('status', 'submitted')
            ->exists();
        abort_unless(! $alreadySubmitted, 403);
    }
}
