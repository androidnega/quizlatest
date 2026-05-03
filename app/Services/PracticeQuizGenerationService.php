<?php

namespace App\Services;

use App\Models\CourseMaterial;
use App\Models\PracticeQuestion;
use App\Models\PracticeQuiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PracticeQuizGenerationService
{
    public function __construct(
        private readonly DeepSeekAiService $deepSeek,
        private readonly PracticeAiPayloadValidator $validator,
        private readonly PracticeAiQuotaService $quota,
        private readonly PracticeModuleSettings $practiceModule,
    ) {}

    public function generate(
        User $student,
        int $courseId,
        ?int $classId,
        ?int $materialId,
        string $quizType,
        string $difficulty,
        int $questionCount,
    ): PracticeQuiz {
        if (! $this->practiceModule->aiPracticeQuizGenerationEnabled()) {
            throw ValidationException::withMessages([
                'practice' => __('AI practice quiz generation is disabled.'),
            ]);
        }
        if (! $this->practiceModule->deepseekConfigured()) {
            throw ValidationException::withMessages([
                'ai' => __('DeepSeek API key is not configured.'),
            ]);
        }

        $this->quota->assertCanGenerateAiPracticeQuiz($student);

        $sourceText = $this->loadSourceText($student, $courseId, $materialId);

        $quiz = PracticeQuiz::query()->create([
            'student_id' => $student->id,
            'course_id' => $courseId,
            'class_id' => $classId,
            'course_material_id' => $materialId,
            'title' => 'AI practice (generating…)',
            'quiz_type' => $quizType,
            'difficulty' => $difficulty,
            'question_count' => $questionCount,
            'status' => PracticeQuiz::STATUS_DRAFT,
            'generated_by_ai' => true,
            'generation_error' => null,
        ]);

        try {
            $system = $this->buildSystemPrompt($quizType, $difficulty, $questionCount);
            $userPrompt = "Course material excerpt (may be truncated):\n---\n".$sourceText."\n---\n".
                "Produce exactly {$questionCount} questions as JSON per instructions.";

            $response = $this->deepSeek->chatJsonInstruction($student, $system, $userPrompt);

            $normalized = $this->validator->validateQuestionsPayload($response['content'], $questionCount);

            DB::transaction(function () use ($quiz, $normalized): void {
                foreach ($normalized as $row) {
                    PracticeQuestion::query()->create([
                        'practice_quiz_id' => $quiz->id,
                        'type' => $row['type'],
                        'question_text' => $row['question_text'],
                        'options' => $row['options'],
                        'correct_answer' => $row['correct_answer'],
                        'explanation' => $row['explanation'] ?? '',
                        'display_order' => $row['display_order'],
                    ]);
                }
                $code = $quiz->course()->value('code') ?? 'Course';
                $quiz->update([
                    'status' => PracticeQuiz::STATUS_READY,
                    'title' => 'Practice · '.$code.' · '.$questionCount.'q',
                ]);
            });

            $this->quota->logUsage(
                $student,
                'practice_quiz',
                $this->practiceModule->practiceAiProvider(),
                $response['model'],
                $response['prompt_tokens'],
                $response['completion_tokens'],
                $response['total_tokens'],
            );
        } catch (\Throwable $e) {
            $quiz->update([
                'status' => PracticeQuiz::STATUS_FAILED,
                'generation_error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $quiz->fresh(['questions']);
    }

    private function loadSourceText(User $student, int $courseId, ?int $materialId): string
    {
        if ($materialId !== null) {
            $material = CourseMaterial::query()
                ->visibleToStudent($student)
                ->where('course_id', $courseId)
                ->whereKey($materialId)
                ->first();
            if ($material === null) {
                throw ValidationException::withMessages([
                    'material' => __('Material not found or not available for your class.'),
                ]);
            }
            if ($material->extracted_text_path === null) {
                throw ValidationException::withMessages([
                    'material' => __('Text has not been extracted for this file yet.'),
                ]);
            }
            $text = Storage::disk('local')->get($material->extracted_text_path);
            if ($text === null || $text === '') {
                throw ValidationException::withMessages([
                    'material' => __('Could not read extracted text.'),
                ]);
            }

            return mb_substr($text, 0, 24_000);
        }

        throw ValidationException::withMessages([
            'material' => __('Select a course material to generate questions from.'),
        ]);
    }

    private function buildSystemPrompt(string $quizType, string $difficulty, int $count): string
    {
        $typeHint = match ($quizType) {
            'mcq' => 'Use only multiple-choice questions with exactly four options each.',
            'true_false' => 'Use only true/false questions.',
            'fill_blank' => 'Use only short fill-in-the-blank questions with a single precise answer.',
            'essay' => 'You may include brief essay prompts; otherwise prefer objective types.',
            default => 'Mix mcq, true_false, and fill_blank unless impossible from the text.',
        };

        return <<<SYS
You generate unofficial practice quiz content for students. Respond with JSON ONLY using this shape:
{"questions":[{"type":"mcq|true_false|fill_blank|essay","question_text":"string","options":["only for mcq, min 2 strings"],"correct_index":0,"correct_bool":true,"correct_text":"only for fill_blank","explanation":"short rationale","rubric_hint":"optional for essay"}]}
Rules:
- Include exactly {$count} questions in the "questions" array.
- Difficulty target: {$difficulty}.
- Question style: {$typeHint}
- For mcq: include "options" array (strings) and integer "correct_index" (0-based).
- For true_false: include boolean "correct_bool" only (no options).
- For fill_blank: include "correct_text" (canonical answer, short).
- For essay: omit machine-gradable fields; keep brief.
- Base every question strictly on the supplied material; if unsure, simplify.
- The word "json" appears in this message so JSON output mode works reliably.
SYS;
    }
}
