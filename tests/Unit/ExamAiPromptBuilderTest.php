<?php

namespace Tests\Unit;

use App\Services\ExamAiPromptBuilder;
use PHPUnit\Framework\TestCase;

class ExamAiPromptBuilderTest extends TestCase
{
    public function test_emits_breakdown_line_when_type_counts_are_provided(): void
    {
        $builder = new ExamAiPromptBuilder();

        $prompt = $builder->build([
            'topic' => 'PHP basics',
            'count' => 5,
            'types' => ['mcq', 'true_false'],
            'type_counts' => ['mcq' => 3, 'true_false' => 2],
            'difficulty' => 'moderate',
            'marks_per_question' => 1,
        ]);

        $this->assertStringContainsString('Produce EXACTLY this breakdown of questions', $prompt);
        $this->assertStringContainsString('3 MCQ', $prompt);
        $this->assertStringContainsString('2 True/False', $prompt);
        $this->assertStringContainsString('Total = 5', $prompt);
    }

    public function test_emits_manual_exclusion_line_when_essay_not_in_types(): void
    {
        $builder = new ExamAiPromptBuilder();

        $prompt = $builder->build([
            'topic' => 'PHP basics',
            'count' => 3,
            'types' => ['mcq', 'fill_blank'],
            'difficulty' => 'moderate',
            'marks_per_question' => 1,
        ]);

        $this->assertStringContainsString('DO NOT generate any essay', $prompt);
    }

    public function test_does_not_emit_exclusion_line_when_essay_is_allowed(): void
    {
        $builder = new ExamAiPromptBuilder();

        $prompt = $builder->build([
            'topic' => 'PHP basics',
            'count' => 3,
            'types' => ['mcq', 'essay'],
            'difficulty' => 'moderate',
            'marks_per_question' => 1,
        ]);

        $this->assertStringNotContainsString('DO NOT generate any essay', $prompt);
    }

    public function test_falls_back_to_total_count_line_without_type_counts(): void
    {
        $builder = new ExamAiPromptBuilder();

        $prompt = $builder->build([
            'topic' => 'PHP basics',
            'count' => 7,
            'types' => ['mcq'],
            'difficulty' => 'moderate',
            'marks_per_question' => 1,
        ]);

        $this->assertStringContainsString('Produce exactly 7 questions total', $prompt);
        $this->assertStringNotContainsString('Produce EXACTLY this breakdown', $prompt);
    }

    public function test_drops_type_counts_for_types_not_in_allowed_types(): void
    {
        // If the caller supplies a breakdown that includes a type the pool
        // doesn't allow, that type is silently dropped so it can't leak into
        // the prompt — the prompt only ever sees the intersection.
        $builder = new ExamAiPromptBuilder();

        $prompt = $builder->build([
            'topic' => 'PHP basics',
            'count' => 3,
            'types' => ['mcq'],
            'type_counts' => ['mcq' => 3, 'true_false' => 5],
            'difficulty' => 'moderate',
            'marks_per_question' => 1,
        ]);

        $this->assertStringContainsString('3 MCQ', $prompt);
        // The disallowed type-count should never reach the breakdown line.
        $this->assertStringNotContainsString('5 True/False', $prompt);
        $this->assertStringContainsString('Total = 3', $prompt);
    }
}
