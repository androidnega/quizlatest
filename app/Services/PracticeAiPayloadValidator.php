<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Validates and normalizes AI JSON output for unofficial practice quizzes only.
 */
class PracticeAiPayloadValidator
{
    /**
     * @return list<array<string, mixed>>
     */
    public function validateQuestionsPayload(string $json, int $expectedCount): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'ai' => __('AI returned invalid JSON.'),
            ]);
        }

        $questions = $decoded['questions'] ?? null;
        if (! is_array($questions) || $questions === []) {
            throw ValidationException::withMessages([
                'ai' => __('AI response missing questions array.'),
            ]);
        }

        if (count($questions) !== $expectedCount) {
            throw ValidationException::withMessages([
                'ai' => __('AI returned the wrong number of questions.'),
            ]);
        }

        $out = [];
        foreach ($questions as $idx => $q) {
            if (! is_array($q)) {
                throw ValidationException::withMessages([
                    'ai' => __('Invalid question structure.'),
                ]);
            }

            $type = $q['type'] ?? null;
            $text = $q['question_text'] ?? null;
            if (! is_string($type) || ! in_array($type, ['mcq', 'true_false', 'fill_blank', 'essay'], true)) {
                throw ValidationException::withMessages([
                    'ai' => __('Invalid question type.'),
                ]);
            }
            if (! is_string($text) || trim($text) === '') {
                throw ValidationException::withMessages([
                    'ai' => __('Question text missing.'),
                ]);
            }

            $options = null;
            $correct = null;

            if ($type === 'mcq') {
                $options = $q['options'] ?? null;
                if (! is_array($options) || count($options) < 2) {
                    throw ValidationException::withMessages([
                        'ai' => __('MCQ requires at least two options.'),
                    ]);
                }
                foreach ($options as $opt) {
                    if (! is_string($opt) || $opt === '') {
                        throw ValidationException::withMessages([
                            'ai' => __('Invalid MCQ option.'),
                        ]);
                    }
                }
                $correctIndex = $q['correct_index'] ?? null;
                if (! is_int($correctIndex) && ! (is_numeric($correctIndex) && (int) $correctIndex == $correctIndex)) {
                    throw ValidationException::withMessages([
                        'ai' => __('MCQ requires integer correct_index.'),
                    ]);
                }
                $correctIndex = (int) $correctIndex;
                if ($correctIndex < 0 || $correctIndex >= count($options)) {
                    throw ValidationException::withMessages([
                        'ai' => __('MCQ correct_index out of range.'),
                    ]);
                }
                $correct = ['correct_index' => $correctIndex];
            } elseif ($type === 'true_false') {
                $cv = $q['correct_bool'] ?? null;
                if (! is_bool($cv)) {
                    throw ValidationException::withMessages([
                        'ai' => __('True/false requires boolean correct_bool.'),
                    ]);
                }
                $correct = ['correct_bool' => $cv];
            } elseif ($type === 'fill_blank') {
                $ans = $q['correct_text'] ?? null;
                if (! is_string($ans) || trim($ans) === '') {
                    throw ValidationException::withMessages([
                        'ai' => __('Fill-in-the-blank requires correct_text.'),
                    ]);
                }
                $correct = ['correct_text' => trim($ans)];
            } else {
                $rubric = $q['rubric_hint'] ?? '';
                $correct = ['essay' => true, 'rubric_hint' => is_string($rubric) ? $rubric : ''];
            }

            $explanation = $q['explanation'] ?? '';
            if ($explanation !== null && ! is_string($explanation)) {
                throw ValidationException::withMessages([
                    'ai' => __('Invalid explanation field.'),
                ]);
            }

            $out[] = [
                'type' => $type,
                'question_text' => trim($text),
                'options' => $options,
                'correct_answer' => $correct,
                'explanation' => is_string($explanation) ? trim($explanation) : '',
                'display_order' => $idx,
            ];
        }

        return $out;
    }
}
