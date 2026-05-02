<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
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
            'faceRequired' => $this->examPolicy->isProctoringEnabled() && $this->examPolicy->isFaceVerificationRequired(),
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
        abort_unless($quiz->status === 'published', 403);

        abort_if($user->class_id === null, 403, 'You must be assigned to a class before taking exams.');

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
