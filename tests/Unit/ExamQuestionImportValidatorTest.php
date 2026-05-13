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
        $this->assertNull($r['sections'][0]['questions'][3]['metadata'] ?? null);
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

    public function test_mcq_accepts_correct_answer_as_option_string(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Capital?',
                    'marks' => 2,
                    'options' => ['Accra', 'Kumasi', 'Tamale', 'Cape Coast'],
                    'correct_answer' => 'Accra',
                ]],
            ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertTrue($r['ok']);
        $this->assertSame([0], $r['sections'][0]['questions'][0]['correct_answer']);
    }

    public function test_rejects_unsupported_type(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'short_answer',
                    'question_text' => 'x',
                    'marks' => 1,
                ]],
            ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('not a supported question type', implode(' ', $r['errors']));
    }

    public function test_rejects_mcq_missing_correct_answer(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'x',
                    'marks' => 1,
                    'options' => ['a', 'b'],
                ]],
            ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('require correct_answer', implode(' ', $r['errors']));
    }

    public function test_rejects_answer_key(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'x',
                    'marks' => 1,
                    'options' => ['a', 'b'],
                    'answer_key' => 0,
                ]],
            ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('not answer_key', implode(' ', $r['errors']));
    }

    public function test_rejects_essay_with_correct_answer(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'essay',
                    'question_text' => 'x',
                    'marks' => 1,
                    'correct_answer' => 'nope',
                ]],
            ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertFalse($r['ok']);
    }

    public function test_allowed_types_filter(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'essay',
                    'question_text' => 'E',
                    'marks' => 1,
                ]],
            ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json, ['mcq'], null);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('not enabled for this assessment', implode(' ', $r['errors']));
    }

    public function test_duplicate_in_batch(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question_text' => 'Dup',
                        'marks' => 1,
                        'options' => ['a', 'b'],
                        'correct_answer' => 0,
                    ],
                    [
                        'type' => 'mcq',
                        'question_text' => 'Dup',
                        'marks' => 1,
                        'options' => ['c', 'd'],
                        'correct_answer' => 0,
                    ],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('duplicate question_text', implode(' ', $r['errors']));
    }
}
