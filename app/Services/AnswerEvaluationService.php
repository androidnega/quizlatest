<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Question;
use Illuminate\Support\Collection;

class AnswerEvaluationService
{
    /**
     * @return array{total_score: float, results: list<array<string, mixed>>}
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
                'evaluation_detail' => ['reason' => 'essay_requires_manual_grading'],
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

        $payload = $this->strictPayload($answer, 'true_false');
        if ($payload === null || ! array_key_exists('value', $payload)) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'auto_scored',
                'evaluation_detail' => ['reason' => 'invalid_payload'],
            ];
        }

        $actual = $payload['value'];
        if (! is_bool($actual)) {
            $converted = filter_var($actual, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $actual = is_bool($converted) ? $converted : null;
        }
        if (! is_bool($actual)) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'auto_scored',
                'evaluation_detail' => ['reason' => 'invalid_payload'],
            ];
        }

        $ok = $actual === $expected;

        return [
            'points_awarded' => $ok ? $max : 0.0,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => ['correct' => $ok],
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

        $payload = $this->strictPayload($answer, 'mcq');
        if ($payload === null) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'auto_scored',
                'evaluation_detail' => ['reason' => 'invalid_payload'],
            ];
        }

        $actual = $this->indicesFromMcqPayload($payload);
        $expectedSorted = $expected;
        sort($expectedSorted);
        $actualSorted = $actual;
        sort($actualSorted);

        $ok = $actualSorted !== [] && $actualSorted === $expectedSorted;

        return [
            'points_awarded' => $ok ? $max : 0.0,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => ['correct' => $ok],
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

        $payload = $this->strictPayload($answer, 'fill_blank');
        if ($payload === null || ! isset($payload['blanks']) || ! is_array($payload['blanks'])) {
            return [
                'points_awarded' => 0.0,
                'evaluation_status' => 'auto_scored',
                'evaluation_detail' => ['reason' => 'invalid_payload'],
            ];
        }

        /** @var list<string> $given */
        $given = array_map(fn ($s) => (string) $s, array_values($payload['blanks']));
        $n = count($expected);
        $matched = 0;

        for ($i = 0; $i < $n; $i++) {
            $exp = $this->normalizeBlank((string) ($expected[$i] ?? ''));
            $got = $this->normalizeBlank((string) ($given[$i] ?? ''));
            if ($exp !== '' && $got !== '' && strcasecmp($exp, $got) === 0) {
                $matched++;
            }
        }

        $ratio = $n > 0 ? $matched / $n : 0.0;
        $points = round($max * $ratio, 2);

        return [
            'points_awarded' => $points,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => ['blanks_matched' => $matched, 'blanks_total' => $n],
        ];
    }

    /**
     * @return array{type: string, value: bool}|array{type: string, selected: mixed}|array{type: string, blanks: list<string>}|null
     */
    private function strictPayload(ExamSessionAnswer $answer, string $type): ?array
    {
        $p = $answer->answer_payload;
        if (! is_array($p) || ($p['type'] ?? null) !== $type) {
            return null;
        }

        return $p;
    }

    /**
     * @param  array{type: string, selected: mixed}  $payload
     * @return list<int>
     */
    private function indicesFromMcqPayload(array $payload): array
    {
        $raw = $payload['selected'] ?? null;
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

        return array_values(array_unique($out));
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

    private function normalizeBlank(string $s): string
    {
        return preg_replace('/\s+/', ' ', trim($s)) ?? '';
    }
}
