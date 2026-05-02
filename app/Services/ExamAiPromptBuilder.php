<?php

namespace App\Services;

/**
 * Builds a strict JSON-only prompt for external LLMs or the internal AI caller.
 */
final class ExamAiPromptBuilder
{
    /**
     * @param  list<string>  $questionTypes
     */
    public function build(array $params): string
    {
        $topic = trim((string) ($params['topic'] ?? ''));
        $count = max(1, min(50, (int) ($params['count'] ?? 5)));
        $types = $params['types'] ?? ['mcq'];
        if (! is_array($types)) {
            $types = ['mcq'];
        }
        $types = array_values(array_filter(array_map(fn ($t) => is_string($t) ? trim($t) : '', $types)));
        $allowed = ['mcq', 'true_false', 'fill_blank', 'essay'];
        $types = array_values(array_intersect($types, $allowed));
        if ($types === []) {
            $types = ['mcq'];
        }

        $difficulty = trim((string) ($params['difficulty'] ?? 'mixed'));
        $marks = (float) ($params['marks_per_question'] ?? 1);
        if ($marks < 0) {
            $marks = 1;
        }

        $typesJson = json_encode($types, JSON_THROW_ON_ERROR);

        return <<<PROMPT
You are generating assessment content for QUIZSNAP. Respond with ONE JSON object only (no markdown fences, no commentary).

Schema:
{
  "sections": [
    {
      "title": "string",
      "questions": [
        {
          "type": "mcq",
          "question_text": "string",
          "marks": number,
          "options": ["string", "string", ...],
          "correct_answer": 0 | [0, 1]
        },
        {
          "type": "true_false",
          "question_text": "string",
          "marks": number,
          "correct_answer": true | false
        },
        {
          "type": "fill_blank",
          "question_text": "string with ___ or clear blanks",
          "marks": number,
          "correct_answer": ["answer_blank_1", "answer_blank_2"]
        },
        {
          "type": "essay",
          "question_text": "string",
          "marks": number
        }
      ]
    }
  ]
}

Rules:
- Use only these question types: {$typesJson}.
- Produce exactly {$count} questions total across one or more sections (you may use a single section).
- Topic focus: {$topic}
- Difficulty guidance: {$difficulty}
- Default marks per question: {$marks} (you may vary slightly if justified).
- MCQ: at least two options; correct_answer indices are zero-based.
- Do not include images, links, or keys outside the schema.
PROMPT;
    }
}
