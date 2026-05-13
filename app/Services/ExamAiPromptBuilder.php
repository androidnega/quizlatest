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
        $count = max(1, min(250, (int) ($params['count'] ?? 5)));
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

Schema (same as JSON import — use correct_answer, never answer_key):
{
  "sections": [
    {
      "title": "string",
      "questions": [
        {
          "type": "mcq",
          "question_text": "string",
          "marks": number,
          "options": ["string", "string", "..."],
          "correct_answer": "exact option text" | 0 | [0, 1],
          "topic": "string (optional)",
          "difficulty": "string (optional)",
          "learning_outcome": "string (optional)",
          "explanation": "string (optional)"
        },
        {
          "type": "true_false",
          "question_text": "string",
          "marks": number,
          "correct_answer": true | false,
          "topic": "string (optional)",
          "difficulty": "string (optional)",
          "learning_outcome": "string (optional)",
          "explanation": "string (optional)"
        },
        {
          "type": "fill_blank",
          "question_text": "string with ___ for each blank",
          "marks": number,
          "correct_answer": ["blank1_answer", "blank2_answer"],
          "topic": "string (optional)",
          "difficulty": "string (optional)",
          "learning_outcome": "string (optional)",
          "explanation": "string (optional)"
        },
        {
          "type": "essay",
          "question_text": "string",
          "marks": number,
          "marking_guide": "string (required when possible — how marks are awarded)",
          "sample_answer": "string (optional)",
          "rubric": "string or short structured text (optional)",
          "topic": "string (optional)",
          "difficulty": "string (optional)",
          "learning_outcome": "string (optional)",
          "explanation": "string (optional)"
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
- MCQ: at least two options; correct_answer must be the exact text of one correct option, or a zero-based index, or an array of indices / matching strings for multiple correct answers.
- True/False and Fill-in-the-blank: correct_answer is required.
- Essay: do not include correct_answer. Include marking_guide whenever possible.
- Add topic, difficulty, learning_outcome, and explanation metadata when it improves reviewability.
- Do not include images, links, or keys outside the schema (except optional metadata fields listed above).
PROMPT;
    }
}
