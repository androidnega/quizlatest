<?php

namespace App\Services;

/**
 * Validates QUIZSNAP exam question JSON imports (paste or AI output).
 *
 * Canonical schema (sections root):
 * {
 *   "sections": [
 *     {
 *       "title": "Section A",
 *       "questions": [
 *         { "type": "mcq", "question_text": "...", "marks": 2, "options": ["a","b"], "correct_answer": "a" | 0 | [0,1] },
 *         { "type": "true_false", "question_text": "...", "marks": 1, "correct_answer": true },
 *         { "type": "fill_blank", "question_text": "...", "marks": 1, "correct_answer": ["x"] | [["x","y"]] },
 *         { "type": "essay", "question_text": "...", "marks": 5, "marking_guide": "...", "topic": "..." }
 *       ]
 *     }
 *   ]
 * }
 *
 * Use correct_answer only — not answer_key.
 *
 * External flat MCQ JSON shape (root JSON array) is still supported for legacy tools.
 */
final class ExamQuestionImportValidator
{
    private const ALLOWED_TYPES = ['mcq', 'true_false', 'fill_blank', 'essay'];

    /** @var list<string> */
    private const ESSAY_EXTRA_METADATA_KEYS = ['marking_guide', 'sample_answer', 'rubric'];

    /** @var list<string> */
    private const COMMON_METADATA_KEYS = ['topic', 'difficulty', 'learning_outcome', 'explanation'];

    /**
     * @param  list<string>|null  $allowedTypes  When set, question type must be in this list (e.g. assessment selected_question_types).
     * @param  list<string>|null  $existingQuestionTextsNormalized  Lowercased trimmed texts already in the pool (non-archived), for duplicate detection.
     * @return array{ok: true, sections: list<array{title: string, questions: list<array<string, mixed>>}>}|array{ok: false, errors: list<string>}
     */
    public function validateJsonString(?string $json, ?array $allowedTypes = null, ?array $existingQuestionTextsNormalized = null): array
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
            return ['ok' => false, 'errors' => ['JSON root must be an array or object.']];
        }

        if ($this->looksLikeChatGptMcqItemsArray($decoded)) {
            /** @var list<array<string, mixed>> $decoded */
            $convErrors = [];
            $wrapped = $this->convertChatGptMcqItemsToSections($decoded, $convErrors);
            if ($wrapped === null) {
                return ['ok' => false, 'errors' => $convErrors !== [] ? $convErrors : ['Could not convert external MCQ JSON.']];
            }

            return $this->validateDecoded($wrapped, $allowedTypes, $existingQuestionTextsNormalized);
        }

        return $this->validateDecoded($decoded, $allowedTypes, $existingQuestionTextsNormalized);
    }

    /**
     * @param  array<int|string, mixed>  $decoded
     */
    private function looksLikeChatGptMcqItemsArray(array $decoded): bool
    {
        if ($decoded === [] || ! array_is_list($decoded)) {
            return false;
        }

        $first = $decoded[0];
        if (! is_array($first)) {
            return false;
        }

        return isset($first['text'], $first['options'], $first['correct'])
            && is_string($first['text'])
            && is_array($first['options']);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $errors
     * @return array{sections: list<array{title: string, questions: list<array<string, mixed>>}>}|null
     */
    private function convertChatGptMcqItemsToSections(array $items, array &$errors): ?array
    {
        $byTopic = [];

        foreach ($items as $i => $item) {
            $p = 'items['.$i.']';
            if (! is_array($item)) {
                $errors[] = "{$p}: each item must be an object.";

                continue;
            }

            $text = $item['text'] ?? $item['question_text'] ?? null;
            if (! is_string($text) || trim($text) === '') {
                $errors[] = "{$p}.text: required non-empty string.";

                continue;
            }

            $optsIn = $item['options'] ?? null;
            if (! is_array($optsIn)) {
                $errors[] = "{$p}.options: must be an object with A–D keys.";

                continue;
            }

            $normKeys = [];
            foreach ($optsIn as $k => $v) {
                if (! is_string($k) || ! is_string($v) || trim($v) === '') {
                    $errors[] = "{$p}.options: each key must be A–D and each value a non-empty string.";

                    continue 2;
                }
                $normKeys[strtoupper(trim($k))] = trim($v);
            }

            $ordered = [];
            foreach (['A', 'B', 'C', 'D'] as $L) {
                if (! isset($normKeys[$L])) {
                    $errors[] = "{$p}.options: missing key \"{$L}\".";

                    continue 2;
                }
                $ordered[] = $normKeys[$L];
            }

            $correctRaw = $item['correct'] ?? null;
            if (! is_string($correctRaw) || $correctRaw === '') {
                $errors[] = "{$p}.correct: must be a single letter A–D.";

                continue;
            }
            $letter = strtoupper(trim($correctRaw));
            if (! preg_match('/^[ABCD]$/', $letter)) {
                $errors[] = "{$p}.correct: must be exactly one of A, B, C, D.";

                continue;
            }
            $idx = ord($letter) - ord('A');

            $topic = $item['topic'] ?? null;
            $topicTitle = is_string($topic) && trim($topic) !== '' ? trim($topic) : 'General';

            $byTopic[$topicTitle] ??= [];
            $meta = [];
            if (is_string($topic) && trim($topic) !== '') {
                $meta['topic'] = trim($topic);
            }
            $byTopic[$topicTitle][] = [
                'type' => 'mcq',
                'question_text' => trim($text),
                'marks' => 1,
                'options' => $ordered,
                'correct_answer' => [$idx],
                'metadata' => $meta === [] ? null : $meta,
            ];
        }

        if ($errors !== []) {
            return null;
        }

        if ($byTopic === []) {
            $errors[] = 'No questions were parsed from the array.';

            return null;
        }

        $sections = [];
        foreach ($byTopic as $title => $questions) {
            $sections[] = [
                'title' => $title,
                'questions' => $questions,
            ];
        }

        return ['sections' => $sections];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  list<string>|null  $allowedTypes
     * @param  list<string>|null  $existingQuestionTextsNormalized
     * @return array{ok: true, sections: list<array{title: string, questions: list<array<string, mixed>>}>}|array{ok: false, errors: list<string>}
     */
    public function validateDecoded(array $decoded, ?array $allowedTypes = null, ?array $existingQuestionTextsNormalized = null): array
    {
        $errors = [];

        if (! isset($decoded['sections']) || ! is_array($decoded['sections'])) {
            return ['ok' => false, 'errors' => ['Missing or invalid "sections" array.']];
        }

        if ($decoded['sections'] === []) {
            return ['ok' => false, 'errors' => ['"sections" must contain at least one section.']];
        }

        $seenInBatch = [];
        foreach ($existingQuestionTextsNormalized ?? [] as $t) {
            if (is_string($t) && $t !== '') {
                $seenInBatch[$t] = true;
            }
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

                continue;
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

                $nq = $this->normalizeQuestion($q, $qp, $allowedTypes, $errors);
                if ($nq === null) {
                    $normQuestions[] = null;

                    continue;
                }

                $normKey = mb_strtolower(trim((string) $nq['question_text']));
                if ($normKey === '') {
                    $errors[] = "{$qp}.question_text: required non-empty string.";
                    $normQuestions[] = null;

                    continue;
                }

                if (isset($seenInBatch[$normKey])) {
                    $errors[] = "{$qp}: duplicate question_text in this import or already present in the pool for this assessment.";
                    $normQuestions[] = null;

                    continue;
                }
                $seenInBatch[$normKey] = true;

                $normQuestions[] = $nq;
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
     * @param  list<string>|null  $allowedTypes
     * @param  list<string>  $errors
     * @return array<string, mixed>|null
     */
    private function normalizeQuestion(array $q, string $qp, ?array $allowedTypes, array &$errors): ?array
    {
        if (array_key_exists('answer_key', $q)) {
            $errors[] = "{$qp}: use correct_answer, not answer_key.";

            return null;
        }

        $type = $q['type'] ?? null;
        if (! is_string($type) || trim($type) === '') {
            $errors[] = "{$qp}.type: required string.";

            return null;
        }
        $type = strtolower(trim($type));
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            $errors[] = "{$qp}.type: \"{$type}\" is not a supported question type. Use one of: ".implode(', ', self::ALLOWED_TYPES).'.';

            return null;
        }

        if ($allowedTypes !== null && $allowedTypes !== [] && ! in_array($type, $allowedTypes, true)) {
            $errors[] = "Question type {$type} is not enabled for this assessment.";

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

        $metadata = $this->extractMetadataForType($q, $type);

        $row = [
            'type' => $type,
            'question_text' => trim($text),
            'marks' => $marksFloat,
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'metadata' => $metadata,
        ];

        if ($type === 'mcq') {
            $opts = $q['options'] ?? null;
            if (! is_array($opts)) {
                $errors[] = "{$qp}.options: MCQ questions require at least two options.";

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
                $errors[] = "{$qp}.options: MCQ questions require at least two options.";

                return null;
            }

            if (! array_key_exists('correct_answer', $q)) {
                $errors[] = "{$qp}.correct_answer: MCQ questions require correct_answer.";

                return null;
            }

            $indices = $this->resolveMcqCorrectIndices($q['correct_answer'], $options, "{$qp}.correct_answer", $errors);
            if ($indices === null) {
                return null;
            }

            $row['options'] = $options;
            $row['correct_answer'] = array_values(array_unique($indices));
            sort($row['correct_answer']);

            return $row;
        }

        if ($type === 'true_false') {
            if (! array_key_exists('correct_answer', $q)) {
                $errors[] = "{$qp}.correct_answer: True/False questions require correct_answer.";

                return null;
            }
            $correct = $q['correct_answer'];
            $bool = $this->normalizeBool($correct);
            if ($bool === null) {
                $errors[] = "{$qp}.correct_answer: must be boolean true/false or \"true\"/\"false\".";

                return null;
            }
            $row['correct_answer'] = $bool;

            return $row;
        }

        if ($type === 'fill_blank') {
            if (! array_key_exists('correct_answer', $q)) {
                $errors[] = "{$qp}.correct_answer: Fill-in-the-Blank questions require correct_answer.";

                return null;
            }
            $correct = $q['correct_answer'];
            if ($correct === null) {
                $errors[] = "{$qp}.correct_answer: Fill-in-the-Blank questions require correct_answer.";

                return null;
            }
            $groups = $this->normalizeFillBlankAcceptedList($correct, "{$qp}.correct_answer", $errors);
            if ($groups === null) {
                return null;
            }
            $row['correct_answer'] = $this->fillBlankCorrectAnswerForStorage($groups);
            $row['answer_schema'] = ['blank_count' => count($groups)];

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
     * @param  array<string, mixed>  $q
     * @return array<string, mixed>|null
     */
    private function extractMetadataForType(array $q, string $type): ?array
    {
        $keys = self::COMMON_METADATA_KEYS;
        if ($type === 'essay') {
            $keys = array_merge(self::ESSAY_EXTRA_METADATA_KEYS, $keys);
        }

        $out = [];
        foreach ($keys as $k) {
            if (! array_key_exists($k, $q)) {
                continue;
            }
            $v = $q[$k];
            if ($v === null) {
                continue;
            }
            if ($k === 'rubric' && is_array($v)) {
                $out[$k] = $v;

                continue;
            }
            if (is_string($v) && trim($v) !== '') {
                $out[$k] = trim($v);
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  list<string>  $options
     * @return list<int>|null
     */
    private function resolveMcqCorrectIndices(mixed $correct, array $options, string $path, array &$errors): ?array
    {
        $n = count($options);

        if (is_string($correct)) {
            $s = trim($correct);
            if ($s === '') {
                $errors[] = "{$path}: MCQ questions require correct_answer.";

                return null;
            }
            if (preg_match('/^[ABCD]$/i', $s) && $n >= 2 && $n <= 4) {
                $idx = ord(strtoupper($s)) - ord('A');
                if ($idx >= 0 && $idx < $n) {
                    return [$idx];
                }
            }

            $matches = [];
            foreach ($options as $i => $opt) {
                if (mb_strtolower(trim($opt)) === mb_strtolower($s)) {
                    $matches[] = $i;
                }
            }
            if (count($matches) === 1) {
                return [$matches[0]];
            }
            if (count($matches) === 0) {
                $errors[] = "{$path}: must match one of the option values exactly (or use a zero-based index).";

                return null;
            }
            $errors[] = "{$path}: matches more than one option; disambiguate or use indices.";

            return null;
        }

        if (is_int($correct) || (is_string($correct) && preg_match('/^-?\d+$/', (string) $correct))) {
            $idx = (int) $correct;
            if ($idx < 0 || $idx >= $n) {
                $errors[] = "{$path}: index {$idx} out of range for options (0–".($n - 1).').';

                return null;
            }

            return [$idx];
        }

        if (is_array($correct)) {
            if ($correct === []) {
                $errors[] = "{$path}: select at least one correct option.";

                return null;
            }

            $allNumeric = true;
            foreach ($correct as $v) {
                if (! (is_int($v) || (is_string($v) && ctype_digit((string) $v)))) {
                    $allNumeric = false;
                    break;
                }
            }

            if ($allNumeric) {
                $indices = [];
                foreach ($correct as $i => $v) {
                    $idx = (int) $v;
                    if ($idx < 0 || $idx >= $n) {
                        $errors[] = "{$path}[{$i}]: index {$idx} out of range for options (0–".($n - 1).').';

                        return null;
                    }
                    $indices[] = $idx;
                }

                return $indices;
            }

            $indices = [];
            foreach ($correct as $i => $item) {
                if (! is_string($item)) {
                    $errors[] = "{$path}[{$i}]: must be a string matching an option or use numeric indices.";

                    return null;
                }
                $sub = $this->resolveMcqCorrectIndices($item, $options, "{$path}[{$i}]", $errors);
                if ($sub === null) {
                    return null;
                }
                foreach ($sub as $ix) {
                    $indices[] = $ix;
                }
            }

            return $indices;
        }

        $errors[] = "{$path}: must be a string (matching one option), an integer index, or an array of indices or option strings.";

        return null;
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
     * @return list<list<string>>|null
     */
    private function normalizeFillBlankAcceptedList(mixed $correct, string $path, array &$errors): ?array
    {
        if (is_string($correct)) {
            $lines = preg_split('/\r\n|\r|\n/', $correct);
            $trimmed = array_values(array_filter(array_map('trim', $lines ?: []), fn ($s) => $s !== ''));
            if ($trimmed === []) {
                $errors[] = "{$path}: non-empty string or array of blank answers required.";

                return null;
            }
            $out = [];
            foreach ($trimmed as $line) {
                $out[] = [$this->normalizeBlank($line)];
            }

            return $out;
        }
        if (! is_array($correct)) {
            $errors[] = "{$path}: must be a string, array of strings, or array of alternatives per blank.";

            return null;
        }
        if ($correct === []) {
            $errors[] = "{$path}: provide at least one blank answer.";

            return null;
        }

        $out = [];
        foreach ($correct as $i => $item) {
            if (is_string($item)) {
                $n = $this->normalizeBlank(trim($item));
                if ($n === '') {
                    $errors[] = "{$path}[{$i}]: blank answer cannot be empty.";

                    return null;
                }
                $out[] = [$n];

                continue;
            }
            if (is_array($item)) {
                $alts = [];
                foreach ($item as $j => $alt) {
                    if (! is_string($alt)) {
                        $errors[] = "{$path}[{$i}][{$j}]: must be a string.";

                        return null;
                    }
                    $t = $this->normalizeBlank(trim($alt));
                    if ($t !== '') {
                        $alts[] = $t;
                    }
                }
                $alts = array_values(array_unique($alts));
                if ($alts === []) {
                    $errors[] = "{$path}[{$i}]: provide at least one non-empty accepted answer.";

                    return null;
                }
                $out[] = $alts;

                continue;
            }
            $errors[] = "{$path}[{$i}]: must be a string or array of string alternatives.";

            return null;
        }

        return $out;
    }

    /**
     * @param  list<list<string>>  $groups
     * @return list<string>|list<list<string>>
     */
    private function fillBlankCorrectAnswerForStorage(array $groups): array
    {
        $multi = false;
        foreach ($groups as $g) {
            if (count($g) > 1) {
                $multi = true;
                break;
            }
        }
        if ($multi) {
            return $groups;
        }

        return array_map(fn (array $g) => $g[0], $groups);
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
