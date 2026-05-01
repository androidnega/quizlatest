<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Question;
use Illuminate\Support\Collection;

class AnswerEvaluationService
{
    /**
     * @return array{total_score: float, results: list<array{question_id: int, points_awarded: float, evaluation_status: string, evaluation_detail: array<string, mixed>}>}
     */
    public function evaluateAndPersist(ExamSession $examSession): array
    {
        $examSession->load(['answers', 'exam.questions']);

        $questions = $examSession->exam?->questions;
        if (! $questions instanceof Collection) {
            $questions = collect();
        }

        /** @var Collection<int, Question> $byId */
        $byId = $questions->keyBy('id');

        $results = [];
        $total = 0.0;

        foreach ($examSession->answers as $answer) {
            /** @var ExamSessionAnswer $answer */
            $question = $byId->get($answer->question_id);
            if (! $question) {
                $this->persistAnswer($answer, 0.0, 'error', ['message' => 'Question not found for exam.']);
                $results[] = [
                    'question_id' => (int) $answer->question_id,
                    'points_awarded' => 0.0,
                    'evaluation_status' => 'error',
                    'evaluation_detail' => ['message' => 'Question not found for exam.'],
                ];

                continue;
            }

            $breakdown = $this->scoreQuestion($question, $answer);
            $points = $breakdown['points_awarded'];
            $status = $breakdown['evaluation_status'];
            $detail = $breakdown['evaluation_detail'];

            $this->persistAnswer($answer, $points, $status, $detail);
            $total += $points;

            $results[] = [
                'question_id' => (int) $question->id,
                'points_awarded' => $points,
                'evaluation_status' => $status,
                'evaluation_detail' => $detail,
            ];
        }

        return [
            'total_score' => round($total, 2),
            'results' => $results,
        ];
    }

    /**
     * @return array{points_awarded: float, evaluation_status: string, evaluation_detail: array<string, mixed>}
     */
    private function scoreQuestion(Question $question, ExamSessionAnswer $answer): array
    {
        $max = (float) $question->marks;

        if ($question->isEssay()) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'pending_manual',
                'evaluation_detail' => ['reason' => 'essay_requires_manual_grading', 'max_marks' => $max],
            ];
        }

        if ($question->isTrueFalse()) {
            return $this->scoreTrueFalse($question, $answer, $max);
        }

        if ($question->isFillBlank()) {
            return $this->scoreFillBlank($question, $answer, $max);
        }

        if ($question->isMCQ()) {
            return $this->scoreMcq($question, $answer, $max);
        }

        return [
            'points_awarded' => 0.0,
            'evaluation_status' => 'error',
            'evaluation_detail' => ['message' => 'Unknown question type.'],
        ];
    }

    /**
     * @return array{points_awarded: float, evaluation_status: string, evaluation_detail: array<string, mixed>}
     */
    private function scoreTrueFalse(Question $question, ExamSessionAnswer $answer, float $max): array
    {
        $expected = $question->correct_answer;
        if (! is_bool($expected)) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'error',
                'evaluation_detail' => ['message' => 'Missing correct answer.'],
            ];
        }

        $actual = $this->readTrueFalseAnswer($answer);
        if ($actual === null) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'auto_scored',
                'evaluation_detail' => ['correct' => false, 'reason' => 'no_answer', 'expected' => $expected],
            ];
        }

        $ok = $actual === $expected;

        return [
            'points_awarded' => $ok ? $max : 0.0,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => [
                'correct' => $ok,
                'expected' => $expected,
                'given' => $actual,
            ],
        ];
    }

    /**
     * @return array{points_awarded: float, evaluation_status: string, evaluation_detail: array<string, mixed>}
     */
    private function scoreMcq(Question $question, ExamSessionAnswer $answer, float $max): array
    {
        $expected = $question->correct_answer;
        if (! is_array($expected)) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'error',
                'evaluation_detail' => ['message' => 'Invalid MCQ configuration.'],
            ];
        }

        $actual = $this->readMcqSelection($answer);
        $expectedSorted = $expected;
        sort($expectedSorted);
        $actualSorted = $actual;
        sort($actualSorted);

        $ok = $actualSorted !== [] && $actualSorted === $expectedSorted;

        return [
            'points_awarded' => $ok ? $max : 0.0,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => [
                'correct' => $ok,
                'expected_indices' => $expectedSorted,
                'given_indices' => $actualSorted,
            ],
        ];
    }

    /**
     * @return array{points_awarded: float, evaluation_status: string, evaluation_detail: array<string, mixed>}
     */
    private function scoreFillBlank(Question $question, ExamSessionAnswer $answer, float $max): array
    {
        $expected = $question->correct_answer;
        if (! is_array($expected) || $expected === []) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'error',
                'evaluation_detail' => ['message' => 'Missing acceptable answers.'],
            ];
        }

        $given = $this->readFillBlankAnswers($answer);
        $n = count($expected);
        $matched = 0;
        $pairResults = [];

        for ($i = 0; $i < $n; $i++) {
            $exp = $this->normalizeBlank((string) ($expected[$i] ?? ''));
            $got = $this->normalizeBlank((string) ($given[$i] ?? ''));
            $pairOk = $exp !== '' && $got !== '' && strcasecmp($exp, $got) === 0;
            if ($pairOk) {
                $matched++;
            }
            $pairResults[] = ['expected' => $exp, 'given' => $got, 'match' => $pairOk];
        }

        $ratio = $n > 0 ? $matched / $n : 0.0;
        $points = round($max * $ratio, 2);

        return [
            'points_awarded' => $points,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => [
                'blanks_matched' => $matched,
                'blanks_total' => $n,
                'pairs' => $pairResults,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function persistAnswer(ExamSessionAnswer $answer, float $points, string $status, array $detail): void
    {
        $answer->forceFill([
            'points_awarded' => $points,
            'evaluation_status' => $status,
            'evaluation_detail' => $detail,
        ])->save();
    }

    private function readTrueFalseAnswer(ExamSessionAnswer $answer): ?bool
    {
        $payload = $answer->answer_payload ?? [];
        if (is_array($payload) && array_key_exists('value', $payload)) {
            return filter_var($payload['value'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        $text = strtolower(trim((string) ($answer->answer_text ?? '')));
        if ($text === '') {
            return null;
        }

        if (in_array($text, ['true', '1', 'yes'], true)) {
            return true;
        }
        if (in_array($text, ['false', '0', 'no'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function readMcqSelection(ExamSessionAnswer $answer): array
    {
        $payload = $answer->answer_payload ?? [];
        if (! is_array($payload)) {
            return [];
        }

        if (isset($payload['selected'])) {
            $raw = $payload['selected'];
            if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
                return [(int) $raw];
            }
            if (is_array($raw)) {
                $out = [];
                foreach ($raw as $v) {
                    if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                        $out[] = (int) $v;
                    }
                }

                return array_values(array_unique($out));
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function readFillBlankAnswers(ExamSessionAnswer $answer): array
    {
        $payload = $answer->answer_payload ?? [];
        if (is_array($payload) && isset($payload['blanks']) && is_array($payload['blanks'])) {
            return array_map(fn ($s) => (string) $s, array_values($payload['blanks']));
        }

        $text = (string) ($answer->answer_text ?? '');
        if ($text === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
    }

    private function normalizeBlank(string $s): string
    {
        return preg_replace('/\s+/', ' ', trim($s)) ?? '';
    }
}
