<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Selects approved pool questions once per session (stable across refreshes).
 */
final class ExamSessionQuestionAssignmentService
{
    /**
     * Idempotent: skips if rows already exist for the session.
     *
     * @throws \RuntimeException
     */
    public function assignForSession(ExamSession $examSession, Quiz $exam): void
    {
        if (ExamSessionQuestion::query()->where('exam_session_id', $examSession->id)->exists()) {
            return;
        }

        $approved = $this->orderedApprovedQuestions($exam);

        if ($approved->isEmpty()) {
            throw new \RuntimeException('No approved questions available for this exam.');
        }

        $configured = $exam->questions_per_student;
        $target = $configured !== null ? (int) $configured : $approved->count();
        $target = max(1, min($target, $approved->count()));

        $pool = $approved->values();
        if ($exam->randomize_questions) {
            $pool = $pool->shuffle();
        }

        /** @var Collection<int, Question> $picked */
        $picked = $pool->take($target)->values();

        // Randomize presentation order among the assigned subset (all types), independent of section layout.
        if ($exam->randomize_questions && $picked->isNotEmpty()) {
            $picked = $picked->shuffle()->values();
        }

        DB::transaction(function () use ($examSession, $picked, $exam): void {
            $order = 0;
            foreach ($picked as $question) {
                $order++;
                // Per-session option shuffle applies to MCQ only (never TF / fill_blank / essay).
                $optionOrder = null;
                if ($question->type === 'mcq' && $exam->randomize_options) {
                    $opts = is_array($question->options) ? $question->options : [];
                    $n = count($opts);
                    if ($n > 0) {
                        $perm = range(0, $n - 1);
                        shuffle($perm);
                        $optionOrder = array_values($perm);
                    }
                }

                ExamSessionQuestion::query()->create([
                    'exam_session_id' => $examSession->id,
                    'question_id' => $question->id,
                    'display_order' => $order,
                    'option_order' => $optionOrder,
                ]);
            }
        });
    }

    /**
     * @return Collection<int, Question>
     */
    private function orderedApprovedQuestions(Quiz $exam): Collection
    {
        $sectionLinked = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->whereNotNull('section_id')
            ->join('exam_sections', 'exam_sections.id', '=', 'questions.section_id')
            ->where('exam_sections.exam_id', $exam->id)
            ->orderBy('exam_sections.section_order')
            ->orderBy('questions.question_order')
            ->select('questions.*')
            ->get();

        $orphans = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->whereNull('section_id')
            ->orderBy('question_order')
            ->get();

        return $sectionLinked->concat($orphans);
    }
}
