<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\Result;
use App\Support\StudentExamResultBreakdown;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentResultController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $sessions = ExamSession::query()
            ->where('student_id', $user->id)
            ->where('status', 'submitted')
            ->with(['exam:id,title,total_marks,course_id,assessment_type,grades_released_at', 'exam.course:id,code,title'])
            ->orderByDesc('end_time')
            ->orderByDesc('id')
            ->get();

        $quizIds = $sessions->pluck('exam_id')->unique()->filter()->values();
        $resultsByQuiz = Result::query()
            ->where('user_id', $user->id)
            ->whereIn('quiz_id', $quizIds)
            ->get(['id', 'user_id', 'quiz_id', 'score', 'status'])
            ->keyBy('quiz_id');

        $sessions->each(function (ExamSession $session) use ($resultsByQuiz): void {
            $session->setRelation('result', $resultsByQuiz->get($session->exam_id));
        });

        return view('student.results.index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(ExamSession $examSession): View
    {
        $user = auth()->user();
        $this->authorize('viewStudentResult', $examSession);

        $examSession->load([
            'exam:id,title,total_marks,proctoring_settings,course_id,assessment_type,grades_released_at',
            'exam.course:id,code,title',
        ]);

        $result = Result::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $examSession->exam_id)
            ->first(['id', 'score', 'status', 'feedback', 'submitted_at', 'graded_at']);

        $status = $result?->status ?? 'pending_manual';

        $assignmentGradesPending = $examSession->exam?->isAssignment()
            && $status === 'graded'
            && ! $examSession->exam->assignmentGradesVisibleToStudents();

        $assessmentType = (string) ($examSession->exam?->assessment_type ?? 'exam');
        $resultKindLabel = match ($assessmentType) {
            'assignment' => __('Assignment result'),
            'quiz' => __('Quiz result'),
            'mid' => __('Mid-semester result'),
            'exam' => __('Exam result'),
            default => __('Assessment result'),
        };

        $breakdown = [];
        $percentage = null;
        $examinerFeedback = null;
        $showCorrect = false;

        if ($status === 'graded' && $examSession->exam !== null && ! $assignmentGradesPending) {
            $showCorrect = $examSession->exam->revealsCorrectAnswersForStudentResults();
            $breakdown = StudentExamResultBreakdown::rows($examSession, $showCorrect);
            $totalMarks = (float) ($examSession->exam->total_marks ?? 0);
            $percentage = $totalMarks > 0
                ? round(((float) ($result?->score ?? 0)) / $totalMarks * 100, 2)
                : null;
            $examinerFeedback = self::formatExaminerFeedback($result?->feedback);
        }

        return view('student.results.show', [
            'session' => $examSession,
            'result' => $result,
            'resultStatus' => $status,
            'assignmentGradesPending' => $assignmentGradesPending,
            'resultKindLabel' => $resultKindLabel,
            'breakdown' => $breakdown,
            'percentage' => $percentage,
            'examinerFeedback' => $examinerFeedback,
            'showCorrectSummaries' => $showCorrect,
        ]);
    }

    public function pdf(ExamSession $examSession): Response|StreamedResponse
    {
        $user = auth()->user();
        $this->authorize('downloadStudentResultPdf', $examSession);

        $examSession->load([
            'exam:id,title,total_marks,proctoring_settings,assessment_type,grades_released_at',
            'student:id,name,index_number',
        ]);

        $result = Result::query()
            ->where('user_id', $user->id)
            ->where('quiz_id', $examSession->exam_id)
            ->first(['id', 'score', 'status', 'feedback']);

        abort_if($result === null || $result->status !== 'graded', 403);

        if ($examSession->exam?->isAssignment() && ! $examSession->exam->assignmentGradesVisibleToStudents()) {
            abort(403);
        }

        $showCorrect = $examSession->exam?->revealsCorrectAnswersForStudentResults() ?? false;
        $breakdown = StudentExamResultBreakdown::rows($examSession, $showCorrect);
        $totalMarks = (float) ($examSession->exam->total_marks ?? 0);
        $percentage = $totalMarks > 0
            ? round(((float) $result->score) / $totalMarks * 100, 2)
            : null;

        $pdf = Pdf::loadView('student.results.pdf', [
            'session' => $examSession,
            'result' => $result,
            'breakdown' => $breakdown,
            'percentage' => $percentage,
            'examinerFeedback' => self::formatExaminerFeedback($result->feedback),
            'showCorrectSummaries' => $showCorrect,
            'documentTitle' => match ((string) ($examSession->exam?->assessment_type ?? 'exam')) {
                'assignment' => __('Assignment result'),
                'quiz' => __('Quiz result'),
                'mid' => __('Mid-semester result'),
                'exam' => __('Exam result'),
                default => __('Assessment result'),
            },
        ]);

        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '-', $examSession->session_id) ?: 'exam';
        $filePrefix = match ((string) ($examSession->exam?->assessment_type ?? 'exam')) {
            'assignment' => 'assignment-result',
            'quiz' => 'quiz-result',
            'mid' => 'mid-semester-result',
            'exam' => 'exam-result',
            default => 'assessment-result',
        };

        return $pdf->download($filePrefix.'-'.$safeId.'.pdf');
    }

    /**
     * @param  array<string, mixed>|null  $feedback
     */
    private static function formatExaminerFeedback(?array $feedback): ?string
    {
        if ($feedback === null || $feedback === []) {
            return null;
        }

        if (isset($feedback['note']) && is_string($feedback['note'])) {
            $t = trim($feedback['note']);

            return $t !== '' ? $t : null;
        }

        if (isset($feedback['text']) && is_string($feedback['text'])) {
            $t = trim($feedback['text']);

            return $t !== '' ? $t : null;
        }

        $encoded = json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $encoded !== '' ? $encoded : null;
    }
}
