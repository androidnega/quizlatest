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

    public function test_fill_blank_accepts_multiple_accepted_strings_per_blank(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'fill_blank',
                    'question_text' => 'Capital',
                    'marks' => 2,
                    'correct_answer' => [['Accra', 'Greater Accra'], 'Kumasi'],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertTrue($r['ok']);
        $fb = $r['sections'][0]['questions'][0];
        $this->assertSame([['Accra', 'Greater Accra'], ['Kumasi']], $fb['correct_answer']);
        $this->assertSame(['blank_count' => 2], $fb['answer_schema']);
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

    public function test_mcq_collapses_duplicate_option_strings_with_string_correct_answer(): void
    {
        // Regression: when the AI emits ["Yes","Yes","No","Maybe"] and picks
        // correct_answer:"Yes", the old validator failed with "matches more
        // than one option; disambiguate or use indices.". The new behaviour
        // dedupes duplicates and pins the answer to the first survivor.
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Pick one',
                    'marks' => 1,
                    'options' => ['Yes', 'Yes', 'No', 'Maybe'],
                    'correct_answer' => 'Yes',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);

        $this->assertTrue($r['ok'], 'expected duplicate-option payload to validate; errors: '.implode(' | ', $r['errors'] ?? []));
        $q = $r['sections'][0]['questions'][0];
        $this->assertSame(['Yes', 'No', 'Maybe'], $q['options']);
        $this->assertSame([0], $q['correct_answer']);
    }

    public function test_mcq_collapses_duplicate_options_and_remaps_integer_indices(): void
    {
        // When the LLM uses a numeric index against an option list that still
        // contains duplicates, the resolved index should follow the option
        // text to its surviving slot after dedup.
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Pick one',
                    'marks' => 1,
                    'options' => ['Apple', 'Apple', 'Orange', 'Mango'],
                    'correct_answer' => 2, // points at "Orange" originally
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);

        $this->assertTrue($r['ok'], implode(' | ', $r['errors'] ?? []));
        $q = $r['sections'][0]['questions'][0];
        $this->assertSame(['Apple', 'Orange', 'Mango'], $q['options']);
        $this->assertSame([1], $q['correct_answer']); // Orange is now index 1
    }

    public function test_mcq_rejects_when_dedup_leaves_fewer_than_two_options(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Pick one',
                    'marks' => 1,
                    'options' => ['Same', 'same', '  SAME  '],
                    'correct_answer' => 'Same',
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('at least two distinct options', implode(' ', $r['errors']));
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

    public function test_lenient_mode_silently_drops_duplicates_within_batch(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [
                    ['type' => 'mcq', 'question_text' => 'Unique 1?', 'marks' => 1, 'options' => ['a', 'b'], 'correct_answer' => 0],
                    ['type' => 'mcq', 'question_text' => 'Dup', 'marks' => 1, 'options' => ['c', 'd'], 'correct_answer' => 0],
                    ['type' => 'mcq', 'question_text' => 'Dup', 'marks' => 1, 'options' => ['e', 'f'], 'correct_answer' => 0],
                    ['type' => 'mcq', 'question_text' => 'Unique 2?', 'marks' => 1, 'options' => ['g', 'h'], 'correct_answer' => 0],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json, null, null, lenient: true);

        $this->assertTrue($r['ok']);
        $texts = array_map(fn ($q) => $q['question_text'], $r['sections'][0]['questions']);
        $this->assertSame(['Unique 1?', 'Dup', 'Unique 2?'], $texts);
    }

    public function test_lenient_mode_silently_drops_questions_already_in_pool(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [
                    ['type' => 'mcq', 'question_text' => 'Fresh question?', 'marks' => 1, 'options' => ['a', 'b'], 'correct_answer' => 0],
                    ['type' => 'mcq', 'question_text' => 'Already in pool', 'marks' => 1, 'options' => ['c', 'd'], 'correct_answer' => 0],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json, null, ['already in pool'], lenient: true);

        $this->assertTrue($r['ok']);
        $texts = array_map(fn ($q) => $q['question_text'], $r['sections'][0]['questions']);
        $this->assertSame(['Fresh question?'], $texts);
    }

    public function test_lenient_mode_silently_drops_malformed_questions(): void
    {
        $v = new ExamQuestionImportValidator;
        $json = json_encode([
            'sections' => [[
                'title' => 'A',
                'questions' => [
                    ['type' => 'mcq', 'question_text' => 'Good MCQ?', 'marks' => 1, 'options' => ['a', 'b'], 'correct_answer' => 0],
                    // Bad: MCQ with only one option
                    ['type' => 'mcq', 'question_text' => 'Bad MCQ?', 'marks' => 1, 'options' => ['only-one'], 'correct_answer' => 0],
                    ['type' => 'true_false', 'question_text' => 'Good TF?', 'marks' => 1, 'correct_answer' => true],
                ],
            ]],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json, null, null, lenient: true);

        $this->assertTrue($r['ok'], 'errors: '.implode(' | ', $r['errors'] ?? []));
        $texts = array_map(fn ($q) => $q['question_text'], $r['sections'][0]['questions']);
        $this->assertSame(['Good MCQ?', 'Good TF?'], $texts);
    }
}
