<?php

namespace App\Services;

use App\Models\PracticeAnswer;
use App\Models\PracticeAttempt;
use App\Models\PracticeQuestion;
use App\Models\PracticeQuiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PracticeAttemptGradingService
{
    /**
     * @param  array<int, mixed>  $answers  keyed by practice_question_id
     */
    public function gradeAndStore(PracticeQuiz $quiz, User $student, array $answers): PracticeAttempt
    {
        abort_unless((int) $quiz->student_id === (int) $student->id, 403);
        abort_unless($quiz->status === PracticeQuiz::STATUS_READY, 422);

        return DB::transaction(function () use ($quiz, $student, $answers): PracticeAttempt {
            $questions = $quiz->questions()->get();
            $total = (float) $questions->count();
            $score = 0.0;

            $attempt = PracticeAttempt::query()->create([
                'practice_quiz_id' => $quiz->id,
                'student_id' => $student->id,
                'score' => 0,
                'total_marks' => $total,
                'percentage' => null,
                'started_at' => now(),
                'submitted_at' => now(),
            ]);

            foreach ($questions as $q) {
                $payload = $answers[$q->id] ?? null;
                [$points, $correct, $storedPayload] = $this->scoreQuestion($q, $payload);
                $score += $points;

                PracticeAnswer::query()->create([
                    'practice_attempt_id' => $attempt->id,
                    'practice_question_id' => $q->id,
                    'answer_payload' => $storedPayload,
                    'points_awarded' => $points,
                    'is_correct' => $correct,
                ]);
            }

            $pct = $total > 0 ? round(($score / $total) * 100, 2) : null;

            $attempt->update([
                'score' => $score,
                'percentage' => $pct,
            ]);

            return $attempt->fresh();
        });
    }

    /**
     * @return array{0: float, 1: bool, 2: array<string, mixed>}
     */
    private function scoreQuestion(PracticeQuestion $q, mixed $payload): array
    {
        $marks = 1.0;
        $correct = false;
        $stored = ['raw' => $payload];

        $ca = $q->correct_answer;

        if ($q->type === 'mcq') {
            $idx = is_numeric($payload) ? (int) $payload : null;
            $expected = is_array($ca) ? ($ca['correct_index'] ?? null) : null;
            if ($idx !== null && $expected !== null && $idx === (int) $expected) {
                $correct = true;
            }
            $stored['choice_index'] = $idx;
        } elseif ($q->type === 'true_false') {
            $b = null;
            if ($payload === '1' || $payload === 1 || $payload === true) {
                $b = true;
            } elseif ($payload === '0' || $payload === 0 || $payload === false) {
                $b = false;
            } else {
                $b = filter_var($payload, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($b === null && is_string($payload)) {
                    $b = match (strtolower($payload)) {
                        'true', 'yes' => true,
                        'false', 'no' => false,
                        default => null,
                    };
                }
            }
            $expected = is_array($ca) ? ($ca['correct_bool'] ?? null) : null;
            if (is_bool($b) && is_bool($expected) && $b === $expected) {
                $correct = true;
            }
            $stored['bool'] = $b;
        } elseif ($q->type === 'fill_blank') {
            $exp = is_array($ca) ? ($ca['correct_text'] ?? '') : '';
            $got = is_string($payload) ? trim(mb_strtolower($payload)) : '';
            if ($got !== '' && $exp !== '' && $got === mb_strtolower(trim($exp))) {
                $correct = true;
            }
            $stored['text'] = is_string($payload) ? $payload : '';
        } elseif ($q->type === 'essay') {
            $text = is_string($payload) ? trim($payload) : '';
            if ($text !== '') {
                $correct = true;
                $stored['essay_length'] = mb_strlen($text);
            }
        }

        $points = $correct ? $marks : 0.0;

        return [$points, $correct, $stored];
    }
}
