<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ExamSessionQuestion;
use App\Models\Question;

/**
 * Ensures every exam question has an {@see ExamSessionAnswer} row so grading and result status stay consistent.
 */
class ExamAnswerSynthesisService
{
    public function ensureEveryQuestionHasAnswer(ExamSession $examSession): void
    {
        $examSession->loadMissing('exam');

        $quiz = $examSession->exam;
        if ($quiz === null) {
            return;
        }

        $assignedIds = ExamSessionQuestion::query()
            ->where('exam_session_id', $examSession->id)
            ->pluck('question_id')
            ->all();

        if ($assignedIds === []) {
            $questions = $quiz->questions()->get();
        } else {
            $questions = Question::query()->whereIn('id', $assignedIds)->get();
        }

        $existing = ExamSessionAnswer::query()
            ->where('exam_session_id', $examSession->id)
            ->pluck('question_id')
            ->flip();

        foreach ($questions as $question) {
            if ($existing->has($question->id)) {
                continue;
            }

            ExamSessionAnswer::query()->create([
                'exam_session_id' => $examSession->id,
                'question_id' => $question->id,
                'answer_text' => null,
                'answer_payload' => $this->emptyPayloadForType((string) $question->type),
                'saved_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayloadForType(string $type): array
    {
        return match ($type) {
            'mcq' => ['type' => 'mcq', 'selected' => []],
            'true_false' => ['type' => 'true_false'],
            'fill_blank' => ['type' => 'fill_blank', 'blanks' => []],
            'essay' => ['type' => 'essay', 'text' => ''],
            default => ['type' => 'mcq', 'selected' => []],
        };
    }
}
