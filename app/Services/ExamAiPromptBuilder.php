<?php

namespace App\Services;

/**
 * Builds a strict JSON-only prompt for external LLMs or the internal AI caller.
 */
class ExamAiPromptBuilder
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

        // Optional per-type breakdown. When provided, the prompt emits an
        // explicit "produce exactly X MCQ, Y True/False, Z Fill-in" line so
        // the model can't just default to all MCQ.
        //
        // @var array<string, int> $typeCounts
        $typeCounts = [];
        if (isset($params['type_counts']) && is_array($params['type_counts'])) {
            foreach ($params['type_counts'] as $t => $n) {
                if (! is_string($t)) {
                    continue;
                }
                $t = strtolower(trim($t));
                if (! in_array($t, $types, true)) {
                    continue;
                }
                $ni = (int) $n;
                if ($ni > 0) {
                    $typeCounts[$t] = $ni;
                }
            }
        }

        $difficulty = trim((string) ($params['difficulty'] ?? 'mixed'));
        $marks = (float) ($params['marks_per_question'] ?? 1);
        if ($marks < 0) {
            $marks = 1;
        }

        $typesJson = json_encode($types, JSON_THROW_ON_ERROR);

        $countLine = "- Produce exactly {$count} questions total across one or more sections (you may use a single section).";
        if ($typeCounts !== []) {
            $labels = [
                'mcq' => 'MCQ',
                'true_false' => 'True/False',
                'fill_blank' => 'Fill-in-the-blank',
                'essay' => 'Essay',
            ];
            $parts = [];
            foreach ($typeCounts as $t => $n) {
                $parts[] = $n.' '.($labels[$t] ?? $t);
            }
            $breakdown = implode(', ', $parts);
            $countLine = "- Produce EXACTLY this breakdown of questions, no more and no fewer of any type: {$breakdown}. Total = {$count}.";
        }

        $manualExclusionLine = '';
        if (! in_array('essay', $types, true)) {
            $manualExclusionLine = "\n- DO NOT generate any essay or open-ended manually-graded questions. Use only the listed auto-gradable types.";
        }

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
- Use only these question types: {$typesJson}.{$manualExclusionLine}
{$countLine}
- Topic focus: {$topic}
- Difficulty guidance: {$difficulty}
- Default marks per question: {$marks} (you may vary slightly if justified).
- MCQ: at least two options; every option text must be distinct (no duplicates or near-duplicates). correct_answer must be the exact text of one correct option, or a zero-based index, or an array of indices / matching strings for multiple correct answers.
- True/False and Fill-in-the-blank: correct_answer is required.
- Essay: do not include correct_answer. Include marking_guide whenever possible.
- Add topic, difficulty, learning_outcome, and explanation metadata when it improves reviewability.
- Do not include images, links, or keys outside the schema (except optional metadata fields listed above).
PROMPT;
    }
}
