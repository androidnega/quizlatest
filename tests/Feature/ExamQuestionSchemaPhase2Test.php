<?php

namespace Tests\Feature;

use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ExamAiPromptBuilder;
use App\Services\ExamQuestionImportValidator;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamQuestionSchemaPhase2Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, exam: Quiz}
     */
    private function seedDraftExamWithTypes(array $selectedTypes): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.schema.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-SCHEMA',
            'title' => 'Schema Course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $courseId,
            'examiner_user_id' => $examiner->id,
            'assigned_by' => null,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Schema exam',
            'description' => null,
            'assessment_type' => 'exam',
            'selected_question_types' => json_encode($selectedTypes),
            'status' => 'draft',
            'duration_minutes' => 30,
            'total_marks' => 0,
            'proctoring_settings' => json_encode(new \stdClass),
            'published_at' => null,
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'examiner' => $examiner->fresh(),
            'exam' => Quiz::query()->findOrFail($quizId),
        ];
    }

    public function test_import_mcq_with_string_correct_answer_persisted_as_draft_with_metadata(): void
    {
        $ctx = $this->seedDraftExamWithTypes(['mcq', 'essay']);
        $payload = [
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Capital of Ghana?',
                    'marks' => 2,
                    'options' => ['Accra', 'Kumasi', 'Tamale', 'Cape Coast'],
                    'correct_answer' => 'Accra',
                    'topic' => 'Geography',
                ]],
            ],
            ],
        ];

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
            'import_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $this->post(route('examiner.exams.questions.import.commit', $ctx['exam']))->assertRedirect();

        $q = Question::query()->where('quiz_id', $ctx['exam']->id)->where('question_text', 'Capital of Ghana?')->first();
        $this->assertNotNull($q);
        $this->assertSame('draft', $q->pool_status);
        $this->assertSame([0], $q->correct_answer);
        $this->assertSame('Geography', data_get($q->metadata, 'topic'));
    }

    public function test_import_essay_marking_guide_preserved(): void
    {
        $ctx = $this->seedDraftExamWithTypes(['essay']);
        $payload = [
            'sections' => [[
                'title' => 'Essays',
                'questions' => [[
                    'type' => 'essay',
                    'question_text' => 'Discuss inflation.',
                    'marks' => 10,
                    'marking_guide' => 'Award marks for definition, causes, examples, and conclusion.',
                    'difficulty' => 'hard',
                ]],
            ],
            ],
        ];

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
            'import_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $this->post(route('examiner.exams.questions.import.commit', $ctx['exam']))->assertRedirect();

        $q = Question::query()->where('quiz_id', $ctx['exam']->id)->first();
        $this->assertSame('essay', $q->type);
        $this->assertNull($q->correct_answer);
        $this->assertStringContainsString('definition', (string) data_get($q->metadata, 'marking_guide'));
        $this->assertSame('hard', data_get($q->metadata, 'difficulty'));
    }

    public function test_preview_rejects_answer_key(): void
    {
        $ctx = $this->seedDraftExamWithTypes(['mcq']);
        $payload = [
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Q?',
                    'marks' => 1,
                    'options' => ['a', 'b'],
                    'answer_key' => 0,
                ]],
            ],
            ],
        ];

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
            'import_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertSessionHasErrors('import_json');
    }

    public function test_preview_rejects_type_not_enabled(): void
    {
        $ctx = $this->seedDraftExamWithTypes(['mcq']);
        $payload = [
            'sections' => [[
                'title' => 'A',
                'questions' => [[
                    'type' => 'essay',
                    'question_text' => 'Long',
                    'marks' => 5,
                ]],
            ],
            ],
        ];

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
            'import_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertSessionHasErrors('import_json');
    }

    public function test_preview_rejects_duplicate_text_in_batch(): void
    {
        $ctx = $this->seedDraftExamWithTypes(['mcq']);
        $payload = [
            'sections' => [[
                'title' => 'A',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question_text' => 'Same text',
                        'marks' => 1,
                        'options' => ['a', 'b'],
                        'correct_answer' => 0,
                    ],
                    [
                        'type' => 'mcq',
                        'question_text' => 'Same text',
                        'marks' => 1,
                        'options' => ['c', 'd'],
                        'correct_answer' => 0,
                    ],
                ],
            ]],
        ];

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
            'import_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertSessionHasErrors('import_json');
    }

    public function test_preview_rejects_duplicate_against_existing_pool(): void
    {
        $ctx = $this->seedDraftExamWithTypes(['mcq']);
        $sec = ExamSection::query()->create([
            'exam_id' => $ctx['exam']->id,
            'title' => 'Existing',
            'section_order' => 1,
        ]);
        Question::query()->create([
            'quiz_id' => $ctx['exam']->id,
            'section_id' => $sec->id,
            'question_text' => 'Already in pool',
            'type' => 'mcq',
            'options' => ['x', 'y'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 1,
            'pool_status' => 'draft',
        ]);

        $payload = [
            'sections' => [[
                'title' => 'New',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Already in pool',
                    'marks' => 1,
                    'options' => ['a', 'b'],
                    'correct_answer' => 0,
                ]],
            ],
            ],
        ];

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
            'import_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertSessionHasErrors('import_json');
    }

    public function test_ai_prompt_contains_string_correct_answer_schema(): void
    {
        $prompt = app(ExamAiPromptBuilder::class)->build([
            'topic' => 'Economics',
            'count' => 3,
            'types' => ['mcq', 'essay'],
            'difficulty' => 'mixed',
            'marks_per_question' => 2,
        ]);
        $this->assertStringContainsString('correct_answer', $prompt);
        $this->assertStringContainsString('marking_guide', $prompt);
        $this->assertStringContainsString('never answer_key', $prompt);
    }

    public function test_validator_matches_ai_output_schema_for_mcq_string(): void
    {
        $v = app(ExamQuestionImportValidator::class);
        $json = json_encode([
            'sections' => [[
                'title' => 'T',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Pick one',
                    'marks' => 1,
                    'options' => ['Red', 'Blue'],
                    'correct_answer' => 'Blue',
                ]],
            ],
            ],
        ], JSON_THROW_ON_ERROR);

        $r = $v->validateJsonString($json, ['mcq', 'true_false'], null);
        $this->assertTrue($r['ok']);
        $this->assertSame([1], $r['sections'][0]['questions'][0]['correct_answer']);
    }
}
