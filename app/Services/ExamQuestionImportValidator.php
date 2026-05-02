<?php

namespace App\Services;

/**
 * Validates QUIZSNAP exam question JSON imports (paste or AI output).
 *
 * Root shape:
 * {
 *   "sections": [
 *     {
 *       "title": "Section A",
 *       "questions": [
 *         { "type": "mcq", "question_text": "...", "marks": 2, "options": ["a","b"], "correct_answer": 0 | [0,1] },
 *         { "type": "true_false", "question_text": "...", "marks": 1, "correct_answer": true },
 *         { "type": "fill_blank", "question_text": "Fill ___", "marks": 1, "correct_answer": ["x"] },
 *         { "type": "essay", "question_text": "...", "marks": 5 }
 *       ]
 *     }
 *   ]
 * }
 */
final class ExamQuestionImportValidator
{
    private const ALLOWED_TYPES = ['mcq', 'true_false', 'fill_blank', 'essay'];

    /**
     * @return array{ok: true, sections: list<array{title: string, questions: list<array<string, mixed>>}>}|array{ok: false, errors: list<string>}
     */
    public function validateJsonString(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return ['ok' => false, 'errors' => ['JSON body is empty.']];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ['ok' => false, 'errors' => ['Invalid JSON: '.$e->getMessage()]];
        }

        if (! is_array($decoded)) {
            return ['ok' => false, 'errors' => ['JSON root must be an object.']];
        }

        return $this->validateDecoded($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{ok: true, sections: list<array{title: string, questions: list<array<string, mixed>>}>}|array{ok: false, errors: list<string>}
     */
    public function validateDecoded(array $decoded): array
    {
        $errors = [];

        if (! isset($decoded['sections']) || ! is_array($decoded['sections'])) {
            return ['ok' => false, 'errors' => ['Missing or invalid "sections" array.']];
        }

        if ($decoded['sections'] === []) {
            return ['ok' => false, 'errors' => ['"sections" must contain at least one section.']];
        }

        $normalizedSections = [];

        foreach ($decoded['sections'] as $si => $section) {
            $prefix = 'sections['.(is_int($si) ? $si : 'key').']';
            if (! is_array($section)) {
                $errors[] = "{$prefix}: each section must be an object.";

                continue;
            }

            $title = $section['title'] ?? null;
            if (! is_string($title) || trim($title) === '') {
                $errors[] = "{$prefix}.title: required non-empty string.";
                $title = '';
            }

            $questionsRaw = $section['questions'] ?? null;
            if (! is_array($questionsRaw)) {
                $errors[] = "{$prefix}.questions: must be an array.";

                continue;
            }

            if ($questionsRaw === []) {
                $errors[] = "{$prefix}.questions: must contain at least one question.";

                continue;
            }

            $normQuestions = [];

            foreach ($questionsRaw as $qi => $q) {
                $qp = "{$prefix}.questions[".(is_int($qi) ? $qi : 'key').']';
                if (! is_array($q)) {
                    $errors[] = "{$qp}: question must be an object.";
                    $normQuestions[] = null;

                    continue;
                }

                $nq = $this->normalizeQuestion($q, $qp, $errors);
                $normQuestions[] = $nq;
            }

            if ($title === '') {
                continue;
            }

            if (in_array(null, $normQuestions, true)) {
                continue;
            }

            /** @var list<array<string, mixed>> $cleanQuestions */
            $cleanQuestions = array_values(array_filter($normQuestions, fn ($x) => $x !== null));

            if ($cleanQuestions === []) {
                continue;
            }

            if (count($cleanQuestions) !== count($questionsRaw)) {
                $errors[] = "{$prefix}: one or more questions failed validation.";

                continue;
            }

            $normalizedSections[] = [
                'title' => trim($title),
                'questions' => $cleanQuestions,
            ];
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        if ($normalizedSections === []) {
            return ['ok' => false, 'errors' => ['No valid sections with questions were found.']];
        }

        return ['ok' => true, 'sections' => $normalizedSections];
    }

    /**
     * @param  array<string, mixed>  $q
     * @param  list<string>  $errors
     * @return array<string, mixed>|null
     */
    private function normalizeQuestion(array $q, string $qp, array &$errors): ?array
    {
        $type = $q['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true)) {
            $errors[] = "{$qp}.type: must be one of ".implode(', ', self::ALLOWED_TYPES).'.';

            return null;
        }

        $text = $q['question_text'] ?? $q['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            $errors[] = "{$qp}.question_text: required non-empty string.";

            return null;
        }

        $marks = $q['marks'] ?? null;
        if (! is_numeric($marks)) {
            $errors[] = "{$qp}.marks: must be a number >= 0.";

            return null;
        }
        $marksFloat = (float) $marks;
        if ($marksFloat < 0) {
            $errors[] = "{$qp}.marks: must be >= 0.";

            return null;
        }

        $row = [
            'type' => $type,
            'question_text' => trim($text),
            'marks' => $marksFloat,
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
        ];

        if ($type === 'mcq') {
            $opts = $q['options'] ?? null;
            if (! is_array($opts)) {
                $errors[] = "{$qp}.options: MCQ requires an array of option strings.";

                return null;
            }
            $options = [];
            foreach ($opts as $oi => $opt) {
                if (! is_string($opt) || trim($opt) === '') {
                    $errors[] = "{$qp}.options[{$oi}]: must be a non-empty string.";

                    return null;
                }
                $options[] = trim($opt);
            }
            if (count($options) < 2) {
                $errors[] = "{$qp}.options: at least two options required.";

                return null;
            }

            $correct = $q['correct_answer'] ?? null;
            $indices = $this->normalizeMcqCorrect($correct, count($options), "{$qp}.correct_answer", $errors);
            if ($indices === null) {
                return null;
            }

            $row['options'] = $options;
            $row['correct_answer'] = array_values(array_unique($indices));
            sort($row['correct_answer']);

            return $row;
        }

        if ($type === 'true_false') {
            $correct = $q['correct_answer'] ?? null;
            $bool = $this->normalizeBool($correct);
            if ($bool === null) {
                $errors[] = "{$qp}.correct_answer: must be boolean true/false or \"true\"/\"false\".";

                return null;
            }
            $row['correct_answer'] = $bool;

            return $row;
        }

        if ($type === 'fill_blank') {
            $correct = $q['correct_answer'] ?? null;
            if ($correct === null) {
                $errors[] = "{$qp}.correct_answer: required array of acceptable answers per blank.";

                return null;
            }
            $list = $this->normalizeStringList($correct);
            if ($list === null || $list === []) {
                $errors[] = "{$qp}.correct_answer: non-empty array of strings required.";

                return null;
            }
            $normalizedBlanks = array_map(fn (string $s) => $this->normalizeBlank($s), $list);
            $row['correct_answer'] = $normalizedBlanks;
            $row['answer_schema'] = ['blank_count' => count($normalizedBlanks)];

            return $row;
        }

        // essay
        if (array_key_exists('correct_answer', $q) && $q['correct_answer'] !== null) {
            $errors[] = "{$qp}.correct_answer: must not be set for essay questions.";

            return null;
        }
        if (isset($q['options'])) {
            $errors[] = "{$qp}.options: must not be set for essay questions.";

            return null;
        }

        return $row;
    }

    /**
     * @return list<int>|null
     */
    private function normalizeMcqCorrect(mixed $correct, int $optionCount, string $path, array &$errors): ?array
    {
        $indices = [];
        if (is_int($correct) || (is_string($correct) && preg_match('/^-?\d+$/', (string) $correct))) {
            $indices = [(int) $correct];
        } elseif (is_array($correct)) {
            foreach ($correct as $i => $v) {
                if (is_int($v) || (is_string($v) && ctype_digit((string) $v))) {
                    $indices[] = (int) $v;
                } else {
                    $errors[] = "{$path}[{$i}]: must be an integer option index.";

                    return null;
                }
            }
        } else {
            $errors[] = "{$path}: must be an integer index or array of indices.";

            return null;
        }

        $indices = array_values(array_unique($indices));
        foreach ($indices as $idx) {
            if ($idx < 0 || $idx >= $optionCount) {
                $errors[] = "{$path}: index {$idx} out of range for options (0–".($optionCount - 1).').';

                return null;
            }
        }

        if ($indices === []) {
            $errors[] = "{$path}: select at least one correct option.";

            return null;
        }

        return $indices;
    }

    private function normalizeBool(mixed $v): ?bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || (is_string($v) && ctype_digit((string) $v))) {
            $n = (int) $v;

            return match ($n) {
                1 => true,
                0 => false,
                default => null,
            };
        }
        if (is_string($v)) {
            $s = strtolower(trim($v));

            return match ($s) {
                'true', 'yes', '1' => true,
                'false', 'no', '0' => false,
                default => null,
            };
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeStringList(mixed $correct): ?array
    {
        if (is_string($correct)) {
            $lines = preg_split('/\r\n|\r|\n/', $correct);

            return array_values(array_filter(array_map('trim', $lines ?: []), fn ($s) => $s !== ''));
        }
        if (! is_array($correct)) {
            return null;
        }
        $out = [];
        foreach ($correct as $item) {
            if (! is_string($item) || trim($item) === '') {
                return null;
            }
            $out[] = trim($item);
        }

        return $out;
    }

    private function normalizeBlank(string $s): string
    {
        return preg_replace('/\s+/', ' ', trim($s)) ?? '';
    }
}
