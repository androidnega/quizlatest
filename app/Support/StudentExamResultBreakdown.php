<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Question;

/**
 * Builds student-safe question rows (no evaluation_detail, metadata, or embeddings).
 */
final class StudentExamResultBreakdown
{
    /**
     * @return list<array{number:int,type:string,points:float,max:float,feedback:?string,correct_summary:?string}>
     */
    public static function rows(ExamSession $session, bool $includeCorrectSummaries): array
    {
        $questionSelect = ['id', 'quiz_id', 'type', 'marks', 'question_order'];
        if ($includeCorrectSummaries) {
            $questionSelect[] = 'correct_answer';
        }

        $session->load([
            'answers' => fn ($q) => $q->select([
                'id',
                'exam_session_id',
                'question_id',
                'points_awarded',
                'grader_feedback',
            ]),
            'answers.question' => fn ($q) => $q->select($questionSelect),
        ]);

        /** @var list<ExamSessionAnswer> $sorted */
        $sorted = $session->answers
            ->filter(fn (ExamSessionAnswer $a) => $a->question !== null)
            ->sortBy(fn (ExamSessionAnswer $a) => $a->question->question_order ?? 999_999)
            ->values()
            ->all();

        $rows = [];
        $n = 0;
        foreach ($sorted as $answer) {
            $q = $answer->question;
            $n++;
            $rows[] = [
                'number' => $n,
                'type' => (string) $q->type,
                'points' => round((float) $answer->points_awarded, 2),
                'max' => round((float) $q->marks, 2),
                'feedback' => self::trimFeedback($answer->grader_feedback),
                'correct_summary' => $includeCorrectSummaries ? self::correctSummary($q) : null,
            ];
        }

        return $rows;
    }

    private static function trimFeedback(?string $feedback): ?string
    {
        $feedback = $feedback !== null ? trim($feedback) : '';

        return $feedback === '' ? null : $feedback;
    }

    private static function correctSummary(Question $question): ?string
    {
        $correct = $question->correct_answer;
        if ($correct === null) {
            return null;
        }

        return match ($question->type) {
            'true_false' => filter_var($correct, FILTER_VALIDATE_BOOLEAN) ? 'True' : 'False',
            'mcq' => is_scalar($correct) ? (string) $correct : json_encode($correct, JSON_UNESCAPED_UNICODE),
            'fill_blank' => is_array($correct)
                ? implode('; ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $correct))
                : (string) $correct,
            default => null,
        };
    }
}
