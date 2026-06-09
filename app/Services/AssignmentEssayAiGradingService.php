<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suggests marks and feedback for essay / assignment answers using the configured AI provider.
 * Examiner review is still required before release to students.
 */
final class AssignmentEssayAiGradingService
{
    public function __construct(
        private readonly DeepSeekAiService $ai,
        private readonly ResultFinalizationService $resultFinalization,
        private readonly SystemSettingsService $systemSettings,
    ) {}

    /**
     * @return array{graded: int, skipped: int, errors: list<string>, graded_answer_ids: list<int>}
     */
    public function gradePendingForExam(Quiz $exam, User $grader): array
    {
        if (! $exam->isAssignment()) {
            throw ValidationException::withMessages([
                'exam' => __('AI assist grading is only available for assignments.'),
            ]);
        }

        if (! $this->systemSettings->getBool('enable_ai', true)) {
            throw ValidationException::withMessages([
                'ai' => __('AI is disabled for this institution.'),
            ]);
        }

        $graded = 0;
        $skipped = 0;
        $errors = [];
        $gradedAnswerIds = [];

        $answers = ExamSessionAnswer::query()
            ->where('evaluation_status', 'pending_manual')
            ->whereHas('question', fn ($q) => $q->where('quiz_id', $exam->id)->where('type', 'essay'))
            ->whereHas('examSession', fn ($s) => $s->where('exam_id', $exam->id)->where('status', 'submitted'))
            ->with(['question', 'examSession'])
            ->orderBy('id')
            ->get();

        foreach ($answers as $answer) {
            try {
                $this->applyAiGrade($answer, $grader, $exam);
                $graded++;
                $gradedAnswerIds[] = (int) $answer->id;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = __('Session :id: :msg', [
                    'id' => $answer->exam_session_id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return [
            'graded' => $graded,
            'skipped' => $skipped,
            'errors' => $errors,
            'graded_answer_ids' => $gradedAnswerIds,
        ];
    }

    public function applyAiGrade(ExamSessionAnswer $answer, User $grader, Quiz $exam): void
    {
        $answer->loadMissing(['question', 'examSession']);
        $question = $answer->question;
        abort_if($question === null || ! $question->isEssay(), 404);

        $max = (float) $question->marks;
        $rawText = (string) ($answer->answer_payload['text'] ?? '');
        $studentText = trim(\App\Support\EssayAnswerHtml::toPlainText($rawText));
        if ($studentText === '') {
            throw ValidationException::withMessages([
                'answer' => __('No typed response to grade.'),
            ]);
        }

        $md = is_array($question->metadata) ? $question->metadata : [];
        $markingGuide = trim((string) ($md['marking_guide'] ?? ''));
        $sampleAnswer = trim((string) ($md['sample_answer'] ?? ''));
        $rubric = $md['rubric'] ?? null;
        $rubricText = is_array($rubric)
            ? json_encode($rubric, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : trim((string) $rubric);

        $system = <<<'SYS'
You are an academic grader. Return JSON only with keys:
- points_awarded (number, 0 to max_marks inclusive)
- feedback (string, constructive, 2-6 sentences)
- strengths (string, optional brief)
- improvements (string, optional brief)
Be fair, cite rubric when provided, and never award more than max_marks.
SYS;

        $user = implode("\n\n", array_filter([
            'max_marks: '.$max,
            'assignment_title: '.(string) $exam->title,
            'question: '.(string) $question->question_text,
            $markingGuide !== '' ? "marking_guide:\n".$markingGuide : null,
            $rubricText !== '' ? "rubric:\n".$rubricText : null,
            $sampleAnswer !== '' ? "sample_answer:\n".$sampleAnswer : null,
            "student_submission:\n".$studentText,
        ]));

        $result = $this->ai->chatJsonInstruction($grader, $system, $user);
        $parsed = json_decode($result['content'], true);
        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                'ai' => __('AI returned invalid grading JSON.'),
            ]);
        }

        $points = (float) ($parsed['points_awarded'] ?? 0);
        $points = max(0.0, min($max, $points));
        $feedback = trim((string) ($parsed['feedback'] ?? ''));
        if ($feedback === '') {
            $feedback = trim(implode(' ', array_filter([
                (string) ($parsed['strengths'] ?? ''),
                (string) ($parsed['improvements'] ?? ''),
            ])));
        }

        DB::transaction(function () use ($answer, $grader, $points, $feedback, $result, $parsed, $exam): void {
            $prev = is_array($answer->evaluation_detail) ? $answer->evaluation_detail : [];
            $history = $prev['grading_history'] ?? [];
            $history[] = [
                'graded_at' => now()->toIso8601String(),
                'grader_id' => $grader->id,
                'points_awarded' => $points,
                'grader_feedback' => $feedback !== '' ? $feedback : null,
                'action' => 'ai_assist',
                'ai_model' => $result['model'] ?? null,
            ];

            $detail = array_merge($prev, [
                'graded' => true,
                'grading_history' => $history,
                'last_points_awarded' => $points,
                'ai_assist' => [
                    'strengths' => $parsed['strengths'] ?? null,
                    'improvements' => $parsed['improvements'] ?? null,
                    'tokens' => $result['total_tokens'] ?? null,
                ],
            ]);

            $answer->update([
                'points_awarded' => $points,
                'evaluation_status' => 'manual_graded',
                'evaluation_detail' => $detail,
                'grader_feedback' => $feedback !== '' ? $feedback : null,
            ]);

            ActivityLog::query()->create([
                'user_id' => $grader->id,
                'quiz_id' => $exam->id,
                'event_type' => 'assignment_ai_grade',
                'event_data' => [
                    'exam_session_answer_id' => $answer->id,
                    'exam_session_id' => $answer->exam_session_id,
                    'points_awarded' => $points,
                ],
                'created_at' => now(),
            ]);

            $session = $answer->examSession;
            if ($session instanceof ExamSession) {
                $this->resultFinalization->finalizeAfterManualGrading($session->fresh(['answers']), $grader);
            }
        });
    }
}
