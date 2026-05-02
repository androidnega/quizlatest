<?php

namespace Tests\Unit;

use App\Services\ExamQuestionImportValidator;
use PHPUnit\Framework\TestCase;

class ExamQuestionImportValidatorTest extends TestCase
{
    public function test_rejects_invalid_json(): void
    {
        $v = new ExamQuestionImportValidator;
        $r = $v->validateJsonString('{');
        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['errors']);
    }

    public function test_accepts_valid_payload(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [
                [
                    'title' => 'A',
                    'questions' => [
                        [
                            'type' => 'mcq',
                            'question_text' => 'Pick two',
                            'marks' => 2,
                            'options' => ['a', 'b', 'c'],
                            'correct_answer' => [0, 1],
                        ],
                        [
                            'type' => 'true_false',
                            'question_text' => 'True?',
                            'marks' => 1,
                            'correct_answer' => true,
                        ],
                        [
                            'type' => 'fill_blank',
                            'question_text' => 'Capital of Ghana is ___',
                            'marks' => 1,
                            'correct_answer' => ['Accra'],
                        ],
                        [
                            'type' => 'essay',
                            'question_text' => 'Explain.',
                            'marks' => 5,
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertTrue($r['ok']);
        $this->assertCount(1, $r['sections']);
        $this->assertCount(4, $r['sections'][0]['questions']);
        $mcq = $r['sections'][0]['questions'][0];
        $this->assertSame([0, 1], $mcq['correct_answer']);
        $this->assertSame(['blank_count' => 1], $r['sections'][0]['questions'][2]['answer_schema']);
    }

    public function test_rejects_mcq_without_enough_options(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [
                [
                    'title' => 'A',
                    'questions' => [
                        [
                            'type' => 'mcq',
                            'question_text' => 'x',
                            'marks' => 1,
                            'options' => ['only'],
                            'correct_answer' => 0,
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertFalse($r['ok']);
    }
}
