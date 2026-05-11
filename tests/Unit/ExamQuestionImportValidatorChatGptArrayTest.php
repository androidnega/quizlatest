<?php

namespace Tests\Unit;

use App\Services\ExamQuestionImportValidator;
use Tests\TestCase;

class ExamQuestionImportValidatorChatGptArrayTest extends TestCase
{
    public function test_accepts_flat_mcq_json_root_array(): void
    {
        $json = <<<'JSON'
[
  {
    "text": "What is 2+2?",
    "options": {"A": "3", "B": "4", "C": "5", "D": "6"},
    "correct": "B",
    "topic": "Arithmetic"
  }
]
JSON;

        $result = app(ExamQuestionImportValidator::class)->validateJsonString($json);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['sections']);
        $this->assertSame('Arithmetic', $result['sections'][0]['title']);
        $this->assertCount(1, $result['sections'][0]['questions']);
        $q = $result['sections'][0]['questions'][0];
        $this->assertSame('mcq', $q['type']);
        $this->assertSame('What is 2+2?', $q['question_text']);
        $this->assertSame([1], $q['correct_answer']);
    }
}
