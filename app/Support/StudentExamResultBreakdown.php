<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ExamSessionQuestion;
use App\Models\Question;

/**
 * Builds student-safe question rows (no evaluation_detail, metadata, or embeddings).
 */
final class StudentExamResultBreakdown
{
    /**
     * @return list<array{
     *     number: int,
     *     type: string,
     *     type_label: string,
     *     points: float,
     *     max: float,
     *     outcome: string,
     *     question_text: string,
     *     your_answer: ?string,
     *     correct_answer: ?string,
     *     feedback: ?string,
     *     options: ?list<string>
     * }>
     */
    public static function rows(ExamSession $session, bool $includeCorrectAnswers): array
    {
        $questionSelect = ['id', 'quiz_id', 'type', 'marks', 'question_order', 'question_text', 'options'];
        if ($includeCorrectAnswers) {
            $questionSelect[] = 'correct_answer';
        }

        $session->load([
            'sessionQuestions',
            'answers' => fn ($q) => $q->select([
                'id',
                'exam_session_id',
                'question_id',
                'answer_text',
                'answer_payload',
                'points_awarded',
                'grader_feedback',
            ]),
            'answers.question' => fn ($q) => $q->select($questionSelect),
        ]);

        $orderMap = $session->sessionQuestions->keyBy('question_id')->map(fn ($sq) => (int) $sq->display_order);
        $sessionQuestionByQuestionId = $session->sessionQuestions->keyBy('question_id');

        $assignedQuestionIds = $session->sessionQuestions->pluck('question_id')->map(fn ($id) => (int) $id)->all();

        /** @var list<ExamSessionAnswer> $sorted */
        $sorted = $session->answers
            ->filter(function (ExamSessionAnswer $a) use ($assignedQuestionIds) {
                if ($assignedQuestionIds !== [] && ! in_array((int) $a->question_id, $assignedQuestionIds, true)) {
                    return false;
                }

                return $a->question !== null;
            })
            ->sortBy(function (ExamSessionAnswer $a) use ($orderMap) {
                if ($orderMap->isNotEmpty()) {
                    return $orderMap->get($a->question_id) ?? 999_999;
                }

                return $a->question->question_order ?? 999_999;
            })
            ->values()
            ->all();

        $rows = [];
        $n = 0;
        foreach ($sorted as $answer) {
            $q = $answer->question;
            $n++;
            $points = round((float) $answer->points_awarded, 2);
            $max = round((float) $q->marks, 2);

            /** @var ExamSessionQuestion|null $sessionQuestion */
            $sessionQuestion = $sessionQuestionByQuestionId->get($answer->question_id);
            $displayOptions = self::displayOptionsForQuestion($q, $sessionQuestion);

            $rows[] = [
                'number' => $n,
                'type' => (string) $q->type,
                'type_label' => self::typeLabel((string) $q->type),
                'points' => $points,
                'max' => $max,
                'outcome' => self::outcome($points, $max),
                'question_text' => trim((string) $q->question_text),
                'your_answer' => self::formatStudentAnswer($q, $answer, $sessionQuestion),
                'correct_answer' => $includeCorrectAnswers
                    ? self::formatCorrectAnswer($q, $sessionQuestion)
                    : null,
                'feedback' => self::trimFeedback($answer->grader_feedback),
                'options' => $displayOptions,
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>|null
     */
    private static function displayOptionsForQuestion(Question $question, ?ExamSessionQuestion $sessionQuestion): ?array
    {
        if ($question->type !== 'mcq') {
            return null;
        }

        $opts = is_array($question->options) ? $question->options : [];
        $map = $sessionQuestion?->mcqDisplayToOriginal();
        if ($map === null || $map === []) {
            return array_values(array_map(fn ($o) => is_scalar($o) ? (string) $o : '', $opts));
        }

        $display = [];
        foreach ($map as $origIdx) {
            $display[] = isset($opts[$origIdx]) && is_scalar($opts[$origIdx]) ? (string) $opts[$origIdx] : '';
        }

        return $display;
    }

    private static function formatStudentAnswer(
        Question $question,
        ExamSessionAnswer $answer,
        ?ExamSessionQuestion $sessionQuestion,
    ): ?string {
        return match ($question->type) {
            'mcq' => self::formatMcqAnswer($question, $answer, $sessionQuestion),
            'true_false' => self::formatTrueFalseAnswer($answer),
            'fill_blank' => self::formatFillBlankAnswer($answer),
            'essay' => self::formatEssayAnswer($answer),
            default => self::trimFeedback($answer->answer_text),
        };
    }

    private static function formatCorrectAnswer(Question $question, ?ExamSessionQuestion $sessionQuestion): ?string
    {
        $correct = $question->correct_answer;
        if ($correct === null) {
            return null;
        }

        return match ($question->type) {
            'mcq' => self::formatMcqIndicesList(
                $question,
                self::normalizeMcqIndices($correct),
                $sessionQuestion,
            ),
            'true_false' => self::formatTrueFalseValue($correct),
            'fill_blank' => self::formatFillBlankCorrect($correct),
            default => null,
        };
    }

    private static function formatMcqAnswer(
        Question $question,
        ExamSessionAnswer $answer,
        ?ExamSessionQuestion $sessionQuestion,
    ): string {
        $indices = self::mcqIndicesFromAnswer($answer);
        if ($indices === []) {
            return __('No answer recorded');
        }

        return self::formatMcqIndicesList($question, $indices, $sessionQuestion);
    }

    /**
     * @param  list<int>  $indices  Original option indices
     */
    private static function formatMcqIndicesList(
        Question $question,
        array $indices,
        ?ExamSessionQuestion $sessionQuestion,
    ): string {
        $opts = is_array($question->options) ? $question->options : [];
        $map = $sessionQuestion?->mcqDisplayToOriginal();
        $letters = range('A', 'Z');
        $lines = [];

        foreach ($indices as $origIdx) {
            $text = isset($opts[$origIdx]) && is_scalar($opts[$origIdx]) ? (string) $opts[$origIdx] : '';
            $displayIdx = $origIdx;
            if ($map !== null && $map !== []) {
                $found = array_search($origIdx, $map, true);
                $displayIdx = $found !== false ? (int) $found : $origIdx;
            }
            $letter = $letters[$displayIdx] ?? (string) ($displayIdx + 1);
            $lines[] = $text === '' ? $letter : trim($letter.'. '.$text);
        }

        return $lines !== [] ? implode("\n", $lines) : __('No answer recorded');
    }

    /**
     * @return list<int>
     */
    private static function mcqIndicesFromAnswer(ExamSessionAnswer $answer): array
    {
        $payload = $answer->answer_payload;
        if (! is_array($payload)) {
            return [];
        }

        if (($payload['type'] ?? null) === 'mcq') {
            return self::normalizeMcqIndices($payload['selected'] ?? []);
        }

        if (array_key_exists('choice', $payload)) {
            return [(int) $payload['choice']];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private static function normalizeMcqIndices(mixed $raw): array
    {
        if (is_int($raw) || (is_string($raw) && preg_match('/^-?\d+$/', (string) $raw))) {
            return [(int) $raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit((string) $item))) {
                $out[] = (int) $item;
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    private static function formatTrueFalseAnswer(ExamSessionAnswer $answer): ?string
    {
        $payload = $answer->answer_payload;
        if (is_array($payload) && ($payload['type'] ?? null) === 'true_false' && array_key_exists('value', $payload)) {
            return self::formatTrueFalseValue($payload['value']);
        }

        if (is_string($answer->answer_text) && trim($answer->answer_text) !== '') {
            return trim($answer->answer_text);
        }

        return __('No answer recorded');
    }

    private static function formatTrueFalseValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? __('True') : __('False');
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1 ? __('True') : __('False');
        }

        $converted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $converted === true ? __('True') : ($converted === false ? __('False') : (string) $value);
    }

    private static function formatFillBlankAnswer(ExamSessionAnswer $answer): ?string
    {
        $payload = $answer->answer_payload;
        if (is_array($payload) && ($payload['type'] ?? null) === 'fill_blank' && isset($payload['blanks']) && is_array($payload['blanks'])) {
            $parts = array_map(fn ($b) => trim((string) $b), array_values($payload['blanks']));
            $parts = array_filter($parts, fn ($p) => $p !== '');

            return $parts !== [] ? implode('; ', $parts) : __('No answer recorded');
        }

        return self::trimFeedback($answer->answer_text) ?? __('No answer recorded');
    }

    private static function formatFillBlankCorrect(mixed $correct): string
    {
        if (! is_array($correct)) {
            return is_scalar($correct) ? (string) $correct : '';
        }

        $groups = [];
        foreach (array_values($correct) as $group) {
            if (is_array($group)) {
                $alts = array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $group);
                $alts = array_values(array_filter($alts, fn ($a) => $a !== ''));
                $groups[] = $alts !== [] ? implode(' / ', $alts) : '—';
            } elseif (is_scalar($group)) {
                $groups[] = (string) $group;
            }
        }

        return $groups !== [] ? implode('; ', $groups) : '—';
    }

    private static function formatEssayAnswer(ExamSessionAnswer $answer): ?string
    {
        $payload = $answer->answer_payload;
        if (is_array($payload) && ($payload['type'] ?? null) === 'essay' && isset($payload['text']) && is_string($payload['text'])) {
            $text = trim($payload['text']);

            return $text !== '' ? $text : __('No answer recorded');
        }

        return self::trimFeedback($answer->answer_text) ?? __('No answer recorded');
    }

    private static function outcome(float $points, float $max): string
    {
        if ($max <= 0) {
            return 'neutral';
        }

        if ($points >= $max) {
            return 'correct';
        }

        if ($points <= 0) {
            return 'incorrect';
        }

        return 'partial';
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'mcq' => __('Multiple choice'),
            'true_false' => __('True / false'),
            'fill_blank' => __('Fill in the blank'),
            'essay' => __('Essay'),
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    private static function trimFeedback(?string $feedback): ?string
    {
        $feedback = $feedback !== null ? trim($feedback) : '';

        return $feedback === '' ? null : $feedback;
    }
}
