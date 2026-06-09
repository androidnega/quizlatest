<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExaminerAssessmentCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedExaminerWithCourse(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $admin = User::query()->where('email', 'admin')->firstOrFail();
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.create.'.Str::lower(Str::random(8)).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'ASSESS101',
            'title' => 'Assessment Design',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $courseId,
            'examiner_user_id' => $examiner->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');
        $academicYearId = (int) AcademicYear::activeForUniversity($uniId)?->id;

        $classroom = Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'Scoped Class',
            'section' => 'A',
            'academic_year' => '2026',
            'academic_year_id' => $academicYearId > 0 ? $academicYearId : null,
            'is_active' => true,
        ]);

        DB::table('class_course')->insert([
            'class_id' => $classroom->id,
            'course_id' => $courseId,
            'assigned_by' => $admin->id,
            'academic_year_id' => $academicYearId > 0 ? $academicYearId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'examiner' => $examiner,
            'course_id' => $courseId,
            'academic_year_id' => $academicYearId,
            'classroom_id' => (int) $classroom->id,
        ];
    }

    public function test_create_page_shows_assessment_fields_and_helper_text(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->get(route('examiner.exams.create'))
            ->assertOk()
            ->assertSee('Create assessment', false)
            ->assertSee('Question types in pool', false)
            ->assertSee('Class groups', false)
            ->assertSee('Import JSON', false)
            ->assertSee('Next', false)
            ->assertSee('Proctoring options', false)
            ->assertSee('Save and continue', false)
            ->assertDontSee('AI question types', false)
            ->assertSee('Essay question', false);
    }

    public function test_examiner_can_create_assignment_without_timed_duration_or_quiz_question_ui(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $dueAt = now()->addDays(7)->format('Y-m-d\TH:i');

        $response = $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'assignment',
                'title' => 'Week 3 Essay',
                'description' => str_repeat('Write a full essay on the topic. ', 3),
                'assignment_question' => 'Explain third normal form with a worked example from your course material.',
                'assignment_marks' => 25,
                'due_at' => $dueAt,
                'selected_question_types' => ['essay'],
            ]);

        $quiz = Quiz::query()->where('title', 'Week 3 Essay')->firstOrFail();

        $response->assertRedirect(route('examiner.quizzes.workspace', $quiz));
        $this->assertSame('assignment', $quiz->assessment_type);
        $this->assertSame(['essay'], $quiz->selected_question_types);
        $this->assertSame(0, (int) $quiz->duration_minutes);
        $this->assertNotNull($quiz->due_at);
        $this->assertSame(1, (int) $quiz->questions_per_student);
        $this->assertSame(25.0, (float) $quiz->total_marks);
        $question = Question::query()->where('quiz_id', $quiz->id)->first();
        $this->assertNotNull($question);
        $this->assertSame('essay', $question->type);
        $this->assertSame('approved', $question->pool_status);
        $this->assertStringContainsString('third normal form', (string) $question->question_text);
    }

    public function test_examiner_can_create_quiz_with_small_ai_question_count(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'quiz',
                'title' => 'Small Pool Quiz',
                'duration_minutes' => 20,
                'question_source' => 'later',
                'selected_question_types' => ['mcq'],
                'ai_question_count' => 3,
                'questions_per_student' => 3,
            ])
            ->assertRedirect();

        $quiz = Quiz::query()->where('title', 'Small Pool Quiz')->firstOrFail();
        $this->assertSame(20, (int) $quiz->duration_minutes);
        $this->assertSame(3, (int) $quiz->questions_per_student);
    }

    public function test_examiner_can_create_draft_assessment_and_redirect_to_workspace(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $response = $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'mid',
                'title' => 'Mid Semester Assessment',
                'description' => 'Draft shell',
                'duration_minutes' => 45,
                'question_source' => 'later',
                'randomize_questions' => '1',
                'randomize_options' => '1',
                'selected_question_types' => ['mcq', 'true_false', 'fill_blank', 'essay'],
            ]);

        $quiz = Quiz::query()->where('title', 'Mid Semester Assessment')->firstOrFail();

        $response->assertRedirect(route('examiner.quizzes.workspace', $quiz));
        $this->assertSame('draft', $quiz->status);
        $this->assertSame((int) $ctx['examiner']->id, (int) $quiz->created_by);
        $this->assertSame('mid', $quiz->assessment_type);
        $this->assertSame((int) $ctx['academic_year_id'], (int) $quiz->academic_year_id);
        $this->assertDatabaseHas('quiz_class', [
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classroom_id'],
        ]);
    }

    public function test_legacy_dashboard_exams_create_url_redirects_to_examiner_scoped_path(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->get('/dashboard/examiner/exams/create')
            ->assertRedirect('/dashboard/exams/create');
    }

    public function test_examiner_can_update_proctoring_options_from_workspace(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'quiz',
                'title' => 'Proctoring Patch Quiz',
                'duration_minutes' => 20,
                'question_source' => 'later',
                'randomize_questions' => '1',
                'randomize_options' => '1',
                'selected_question_types' => ['mcq', 'true_false', 'fill_blank', 'essay'],
            ])
            ->assertRedirect();

        $quiz = Quiz::query()->where('title', 'Proctoring Patch Quiz')->firstOrFail();

        $this->actingAs($ctx['examiner'])
            ->from(route('examiner.quizzes.workspace', $quiz))
            ->patch(route('examiner.exams.proctoring-options.update', $quiz), [
                'enable_phone' => '0',
                'enable_fullscreen' => '0',
                'enable_auto_submit' => '0',
            ])
            ->assertRedirect(route('examiner.quizzes.workspace', $quiz));

        $quiz->refresh();
        $this->assertFalse((bool) data_get($quiz->proctoring_settings, 'phone_detection_enabled'));
        $this->assertFalse((bool) data_get($quiz->proctoring_settings, 'fullscreen_enforced'));
        $this->assertFalse((bool) data_get($quiz->proctoring_settings, 'auto_submit_enabled'));
    }

    public function test_create_outline_suggest_topics_returns_topic_candidates(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $body = "Course overview line here\n".
            "- First learning objective written with enough length\n".
            "- Second learning objective also written long enough\n";

        $file = UploadedFile::fake()->createWithContent('outline.txt', $body);

        $payload = $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.outline-suggest-topics'), [
                'ai_outline_file' => $file,
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['topics', 'outline_text'])
            ->json();

        $this->assertIsArray($payload['topics']);
        $this->assertGreaterThan(0, count($payload['topics']));
        // Outline plain text is echoed back so the browser can re-use it for
        // batched AI generation without re-uploading the file each batch.
        $this->assertIsString($payload['outline_text']);
        $this->assertStringContainsString('First learning objective', $payload['outline_text']);
    }

    public function test_ai_generate_batch_returns_validated_sections_for_one_batch(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        // Mock the LLM caller — we never want to hit a real provider in tests.
        $this->mockAiGeneratorWith([
            'ok' => true,
            'sections' => [[
                'title' => 'Batch 1',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question_text' => 'Mocked MCQ 1?',
                        'marks' => 1,
                        'options' => ['A', 'B'],
                        'correct_answer' => 'A',
                    ],
                ],
            ]],
        ]);

        $payload = $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables, Loops',
                'selected_question_types' => ['mcq'],
                'ai_question_types' => ['mcq'],
                'ai_difficulty' => 'moderate',
                'ai_marks' => 1,
                'batch_count' => 1,
                'batch_index' => 0,
                'total_count' => 1,
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['sections'])
            ->json();

        $this->assertSame('Mocked MCQ 1?', data_get($payload, 'sections.0.questions.0.question_text'));
    }

    public function test_ai_generate_batch_invokes_generator_in_lenient_mode(): void
    {
        // Regression: a single duplicate question used to fail a whole batch
        // mid-prep. The batch endpoint must call the generator with lenient
        // mode so the validator drops duplicates instead of failing.
        $ctx = $this->seedExaminerWithCourse();

        $mock = \Mockery::mock(\App\Services\ExamAiQuestionGenerator::class);
        $mock->shouldReceive('generateFromPrompt')
            ->withArgs(function ($prompt, $allowedTypes, $existing, $lenient = false) {
                return $lenient === true;
            })
            ->andReturn(['ok' => true, 'sections' => [[
                'title' => 'OK',
                'questions' => [[
                    'type' => 'mcq',
                    'question_text' => 'Survived duplicate guard?',
                    'marks' => 1,
                    'options' => ['A', 'B'],
                    'correct_answer' => 'A',
                ]],
            ]]]);
        $this->app->instance(\App\Services\ExamAiQuestionGenerator::class, $mock);

        $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables',
                'selected_question_types' => ['mcq'],
                'batch_count' => 1,
                'batch_index' => 5,
                'total_count' => 50,
                'existing_question_texts' => ['previous question text'],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_ai_generate_batch_retries_transient_timeout_then_succeeds(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        // The Sleep facade is what the controller uses for retry backoff; fake
        // it so the test doesn't actually wait 1+2 seconds between attempts.
        \Illuminate\Support\Sleep::fake();

        $mock = \Mockery::mock(\App\Services\ExamAiQuestionGenerator::class);
        $mock->shouldReceive('generateFromPrompt')
            ->andReturnValues([
                ['ok' => false, 'errors' => ['AI request timed out or could not connect. Check internet/server access and try again.']],
                ['ok' => true, 'sections' => [[
                    'title' => 'Recovered batch',
                    'questions' => [[
                        'type' => 'mcq',
                        'question_text' => 'After-retry MCQ?',
                        'marks' => 1,
                        'options' => ['A', 'B'],
                        'correct_answer' => 'A',
                    ]],
                ]]],
            ]);
        $this->app->instance(\App\Services\ExamAiQuestionGenerator::class, $mock);

        $payload = $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables, Loops',
                'selected_question_types' => ['mcq'],
                'batch_count' => 1,
                'batch_index' => 0,
                'total_count' => 1,
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->json();

        $this->assertSame('After-retry MCQ?', data_get($payload, 'sections.0.questions.0.question_text'));
    }

    public function test_ai_generate_batch_does_not_retry_non_transient_failures(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        \Illuminate\Support\Sleep::fake();

        $mock = \Mockery::mock(\App\Services\ExamAiQuestionGenerator::class);
        // A config/auth/schema error should fail fast — no point retrying.
        $mock->shouldReceive('generateFromPrompt')
            ->once()
            ->andReturn(['ok' => false, 'errors' => ['AI API key is not configured.']]);
        $this->app->instance(\App\Services\ExamAiQuestionGenerator::class, $mock);

        $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables',
                'selected_question_types' => ['mcq'],
                'batch_count' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_ai_generate_batch_passes_per_type_counts_to_prompt_builder(): void
    {
        // When the examiner specifies "3 MCQ + 2 True/False" for a batch the
        // controller must hand a matching type_counts array to the prompt
        // builder so the prompt asks for that exact mix.
        $ctx = $this->seedExaminerWithCourse();

        $promptMock = \Mockery::mock(\App\Services\ExamAiPromptBuilder::class);
        $promptMock->shouldReceive('build')
            ->once()
            ->withArgs(function (array $params) {
                return is_array($params['type_counts'] ?? null)
                    && ($params['type_counts']['mcq'] ?? null) === 3
                    && ($params['type_counts']['true_false'] ?? null) === 2
                    && in_array('mcq', $params['types'], true)
                    && in_array('true_false', $params['types'], true)
                    && ! in_array('essay', $params['types'], true);
            })
            ->andReturn('PROMPT');
        $this->app->instance(\App\Services\ExamAiPromptBuilder::class, $promptMock);

        $this->mockAiGeneratorWith([
            'ok' => true,
            'sections' => [[
                'title' => 'Batch',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question_text' => 'Mix mcq 1?',
                        'marks' => 1,
                        'options' => ['A', 'B'],
                        'correct_answer' => 'A',
                    ],
                ],
            ]],
        ]);

        $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables',
                'selected_question_types' => ['mcq', 'true_false'],
                'ai_question_types' => ['mcq', 'true_false'],
                'batch_count' => 5,
                'ai_type_counts' => ['mcq' => 3, 'true_false' => 2],
            ])
            ->assertOk();
    }

    public function test_ai_generate_batch_rejects_per_type_counts_summing_off_batch_count(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables',
                'selected_question_types' => ['mcq', 'true_false'],
                'batch_count' => 5,
                // Sum = 4, batch_count = 5 → reject.
                'ai_type_counts' => ['mcq' => 3, 'true_false' => 1],
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_ai_generate_batch_rejects_when_only_essay_is_selected(): void
    {
        // AI bulk-generation is auto-grade only; an essay-only pool must
        // surface a clear error rather than producing a prompt that the LLM
        // ignores.
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables',
                'selected_question_types' => ['essay'],
                'ai_question_types' => ['essay'],
                'batch_count' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonStructure(['errors']);
    }

    public function test_ai_generate_batch_rejects_per_type_counts_for_disabled_type(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'ai_topics' => 'Variables',
                // Pool only has MCQ → asking the controller for a true_false
                // quota is invalid.
                'selected_question_types' => ['mcq'],
                'ai_question_types' => ['mcq'],
                'batch_count' => 2,
                'ai_type_counts' => ['mcq' => 1, 'true_false' => 1],
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_ai_generate_batch_rejects_without_topics_or_outline(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.ai.generate-batch'), [
                'selected_question_types' => ['mcq'],
                'batch_count' => 1,
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonStructure(['errors']);
    }

    public function test_store_uses_pregenerated_sections_and_skips_llm_call(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        // If the LLM is called at all, force a failure — the pregenerated
        // payload should bypass any provider hit.
        $this->mockAiGeneratorWith([
            'ok' => false,
            'errors' => ['Provider should not have been called.'],
        ]);

        $pregenerated = json_encode([
            'sections' => [[
                'title' => 'Imported via batches',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question_text' => 'Pregenerated Q1?',
                        'marks' => 1,
                        'options' => ['A', 'B'],
                        'correct_answer' => 'A',
                    ],
                    [
                        'type' => 'mcq',
                        'question_text' => 'Pregenerated Q2?',
                        'marks' => 1,
                        'options' => ['A', 'B'],
                        'correct_answer' => 'B',
                    ],
                ],
            ]],
        ]);

        $response = $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'quiz',
                'title' => 'Pregenerated AI Quiz',
                'duration_minutes' => 20,
                'question_source' => 'ai_generate',
                'selected_question_types' => ['mcq'],
                'ai_question_count' => 2,
                'questions_per_student' => 2,
                'ai_pregenerated_sections' => $pregenerated,
            ]);

        $quiz = Quiz::query()->where('title', 'Pregenerated AI Quiz')->firstOrFail();
        // After create, the examiner is now routed to the dedicated review-pool
        // page so they can approve generated questions before reaching the
        // full workspace.
        $response->assertRedirect(route('examiner.exams.review', $quiz));

        $this->assertSame(
            2,
            Question::query()->where('quiz_id', $quiz->id)->count(),
        );
        $texts = Question::query()->where('quiz_id', $quiz->id)->pluck('question_text')->all();
        $this->assertContains('Pregenerated Q1?', $texts);
        $this->assertContains('Pregenerated Q2?', $texts);
        // Questions land in the pool as drafts awaiting approval.
        $this->assertSame(
            2,
            Question::query()->where('quiz_id', $quiz->id)->where('pool_status', 'draft')->count(),
        );
    }

    public function test_review_page_renders_pending_questions_for_examiner(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $pregenerated = json_encode([
            'sections' => [[
                'title' => 'Section A',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question_text' => 'Approve me please?',
                        'marks' => 1,
                        'options' => ['Yes', 'No'],
                        'correct_answer' => 'Yes',
                    ],
                ],
            ]],
        ]);

        $this->mockAiGeneratorWith([
            'ok' => false,
            'errors' => ['Provider should not have been called.'],
        ]);

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'quiz',
                'title' => 'Review Flow Quiz',
                'duration_minutes' => 15,
                'question_source' => 'ai_generate',
                'selected_question_types' => ['mcq'],
                'ai_question_count' => 1,
                'questions_per_student' => 1,
                'ai_pregenerated_sections' => $pregenerated,
            ])
            ->assertRedirect();

        $quiz = Quiz::query()->where('title', 'Review Flow Quiz')->firstOrFail();

        $this->actingAs($ctx['examiner'])
            ->get(route('examiner.exams.review', $quiz))
            ->assertOk()
            ->assertSee('Review question pool', false)
            ->assertSee('Step 3 of 3', false)
            ->assertSee('Approve me please?', false)
            ->assertSee('Continue to workspace', false);
    }

    public function test_review_page_redirects_to_workspace_for_assignment(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $dueAt = now()->addDays(5)->format('Y-m-d\TH:i');

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'assignment',
                'title' => 'Essay Assignment',
                'description' => str_repeat('Detailed brief for the assignment. ', 3),
                'assignment_question' => 'Discuss normalisation in modern relational databases.',
                'assignment_marks' => 20,
                'due_at' => $dueAt,
                'selected_question_types' => ['essay'],
            ])
            ->assertRedirect();

        $quiz = Quiz::query()->where('title', 'Essay Assignment')->firstOrFail();

        // Assignments are essay-only so there is nothing to "approve in a pool".
        // The review URL should bounce straight to the workspace.
        $this->actingAs($ctx['examiner'])
            ->get(route('examiner.exams.review', $quiz))
            ->assertRedirect(route('examiner.quizzes.workspace', $quiz));
    }

    public function test_review_page_redirects_to_workspace_when_no_questions_yet(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'quiz',
                'title' => 'Empty Quiz',
                'duration_minutes' => 15,
                'question_source' => 'later',
                'selected_question_types' => ['mcq'],
            ])
            ->assertRedirect();

        $quiz = Quiz::query()->where('title', 'Empty Quiz')->firstOrFail();

        // No questions in the pool → nothing to review → bounce to workspace.
        $this->actingAs($ctx['examiner'])
            ->get(route('examiner.exams.review', $quiz))
            ->assertRedirect(route('examiner.quizzes.workspace', $quiz));
    }

    public function test_workspace_no_longer_shows_question_overview_or_draft_pool(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $pregenerated = json_encode([
            'sections' => [[
                'title' => 'Section A',
                'questions' => [
                    [
                        'type' => 'mcq',
                        'question_text' => 'Verbose pool draft question that should NOT appear on the workspace page itself.',
                        'marks' => 1,
                        'options' => ['One', 'Two'],
                        'correct_answer' => 'One',
                    ],
                ],
            ]],
        ]);

        $this->mockAiGeneratorWith([
            'ok' => false,
            'errors' => ['Provider should not have been called.'],
        ]);

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'quiz',
                'title' => 'Lean Workspace Quiz',
                'duration_minutes' => 15,
                'question_source' => 'ai_generate',
                'selected_question_types' => ['mcq'],
                'ai_question_count' => 1,
                'questions_per_student' => 1,
                'ai_pregenerated_sections' => $pregenerated,
            ])
            ->assertRedirect();

        $quiz = Quiz::query()->where('title', 'Lean Workspace Quiz')->firstOrFail();

        $response = $this->actingAs($ctx['examiner'])
            ->get(route('examiner.quizzes.workspace', $quiz));
        $response->assertOk();

        // The workspace exposes a CTA pointing at the dedicated review page…
        $response->assertSee(route('examiner.exams.review', $quiz), false);
        $response->assertSee('Review &amp; approve pool', false);
        // …but the heavy question overview list and draft approval grid are
        // no longer rendered inline on the workspace page itself.
        $response->assertDontSee('id="q-overview-heading"', false);
        $response->assertDontSee('id="pool-draft-heading"', false);
        $response->assertDontSee('Verbose pool draft question that should NOT appear', false);
    }

    private function mockAiGeneratorWith(array $payload): void
    {
        $mock = \Mockery::mock(\App\Services\ExamAiQuestionGenerator::class);
        $mock->shouldReceive('generateFromPrompt')->andReturn($payload);
        $this->app->instance(\App\Services\ExamAiQuestionGenerator::class, $mock);
    }

    public function test_validate_import_json_endpoint_returns_summary_with_breakdown_when_ok(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $payload = json_encode([
            'sections' => [[
                'title' => 'Pool',
                'questions' => [
                    ['type' => 'mcq', 'marks' => 1, 'question_text' => 'Q1?', 'options' => ['A', 'B'], 'correct_answer' => 'A'],
                    ['type' => 'mcq', 'marks' => 1, 'question_text' => 'Q2?', 'options' => ['A', 'B'], 'correct_answer' => 'B'],
                    ['type' => 'mcq', 'marks' => 1, 'question_text' => 'Q3?', 'options' => ['A', 'B'], 'correct_answer' => 'A'],
                ],
            ]],
        ]);

        $response = $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.validate-import-json'), [
                'import_json' => $payload,
                'selected_question_types' => ['mcq'],
                'questions_per_student' => 2,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('pool_count', 3);
        $response->assertJsonPath('type_breakdown.mcq', 3);
        $this->assertStringContainsString('3', (string) $response->json('message'));
        $this->assertStringContainsString('MCQ', (string) $response->json('message'));
    }

    public function test_validate_import_json_endpoint_rejects_when_pool_smaller_than_per_student(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $payload = json_encode([
            'sections' => [[
                'title' => 'Tiny pool',
                'questions' => [
                    ['type' => 'mcq', 'marks' => 1, 'question_text' => 'Only one Q?', 'options' => ['A', 'B'], 'correct_answer' => 'A'],
                ],
            ]],
        ]);

        $response = $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.validate-import-json'), [
                'import_json' => $payload,
                'selected_question_types' => ['mcq'],
                'questions_per_student' => 10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $errors = (array) $response->json('errors');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Pool too small', implode("\n", $errors));
    }

    public function test_validate_import_json_endpoint_rejects_when_question_types_not_in_pool_selection(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $payload = json_encode([
            'sections' => [[
                'title' => 'Mixed pool',
                'questions' => [
                    ['type' => 'mcq', 'marks' => 1, 'question_text' => 'Q1?', 'options' => ['A', 'B'], 'correct_answer' => 'A'],
                    ['type' => 'true_false', 'marks' => 1, 'question_text' => 'Q2?', 'correct_answer' => true],
                ],
            ]],
        ]);

        $response = $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.validate-import-json'), [
                'import_json' => $payload,
                // Only MCQ is selected — the true_false question should be flagged.
                'selected_question_types' => ['mcq'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
    }

    public function test_create_page_renders_modernized_form_chrome(): void
    {
        $ctx = $this->seedExaminerWithCourse();

        $response = $this->actingAs($ctx['examiner'])
            ->get(route('examiner.exams.create'));

        $response->assertOk();
        // Reactive type-in-pool checkbox state.
        $response->assertSee('x-model="selectedQuestionTypes"', false);
        // Pool-vs-per-student indicator.
        $response->assertSee('poolSizeAvailable', false);
        $response->assertSee('Questions per student', false);
        // Modernized radio-card source picker.
        $response->assertSee('Add later', false);
        $response->assertSee('Import JSON', false);
        // Live JSON parsed-count pill + clear button.
        $response->assertSee('importJsonClientCount', false);
    }
}
