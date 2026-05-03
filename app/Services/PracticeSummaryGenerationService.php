<?php

namespace App\Services;

use App\Models\CourseMaterial;
use App\Models\PracticeSummary;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PracticeSummaryGenerationService
{
    public function __construct(
        private readonly DeepSeekAiService $deepSeek,
        private readonly PracticeAiQuotaService $quota,
        private readonly PracticeModuleSettings $practiceModule,
    ) {}

    public function generate(User $student, int $courseId, ?int $materialId): PracticeSummary
    {
        if (! $this->practiceModule->aiSummaryEnabled()) {
            throw ValidationException::withMessages([
                'practice' => __('AI summaries are disabled.'),
            ]);
        }
        if (! $this->practiceModule->deepseekConfigured()) {
            throw ValidationException::withMessages([
                'ai' => __('DeepSeek API key is not configured.'),
            ]);
        }

        $this->quota->assertCanGenerateAiSummary($student);

        $text = $this->loadSourceText($student, $courseId, $materialId);

        $system = <<<'SYS'
You write concise study summaries for students. Use clear headings and bullet points.
Do not invent facts beyond the excerpt. Keep under 1200 words.
SYS;

        $userPrompt = "Summarize the following study material:\n\n".$text;

        $response = $this->deepSeek->chatPlainInstruction($student, $system, $userPrompt);

        $summary = PracticeSummary::query()->create([
            'student_id' => $student->id,
            'course_id' => $courseId,
            'course_material_id' => $materialId,
            'title' => 'Summary · '.now()->format('Y-m-d H:i'),
            'body' => trim($response['content']),
        ]);

        $this->quota->logUsage(
            $student,
            'practice_summary',
            $this->practiceModule->practiceAiProvider(),
            $response['model'],
            $response['prompt_tokens'],
            $response['completion_tokens'],
            $response['total_tokens'],
        );

        return $summary;
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

            return mb_substr($text, 0, 48_000);
        }

        throw ValidationException::withMessages([
            'material' => __('Select a course material to summarize.'),
        ]);
    }
}
