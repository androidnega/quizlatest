<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ExamLifecycleService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentQuestionTypeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, course_id: int, classroom_id: int}
     */
    private function seedExaminerWithCourse(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $admin = User::query()->where('email', 'admin')->firstOrFail();
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.qtypes.'.Str::lower(Str::random(8)).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'QTYPE101',
            'title' => 'Question Type Lab',
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
            'name' => 'QType Class',
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
            'classroom_id' => (int) $classroom->id,
        ];
    }

    /**
     * @param  list<string>  $types
     * @param  list<array<string, mixed>>  $questions
     */
    private function importPayload(array $questions): string
    {
        return json_encode([
            'sections' => [
                [
                    'title' => 'Block',
                    'questions' => $questions,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $selectedTypes
     */
    private function storeWithImport(User $examiner, int $courseId, int $classroomId, array $selectedTypes, string $importJson, string $title): void
    {
        $this->actingAs($examiner)
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $courseId,
                'classroom_ids' => [$classroomId],
                'assessment_type' => 'quiz',
                'title' => $title,
                'duration_minutes' => 30,
                'question_source' => 'paste_json',
                'import_json' => $importJson,
                'questions_per_student' => 1,
                'randomize_questions' => '1',
                'randomize_options' => '1',
                'selected_question_types' => $selectedTypes,
            ])
            ->assertRedirect();
    }

    public function test_create_mcq_only_assessment(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'mcq',
                'question_text' => 'Pick',
                'marks' => 2,
                'options' => ['a', 'b'],
                'correct_answer' => [0],
            ],
        ]);

        $this->storeWithImport($ctx['examiner'], $ctx['course_id'], $ctx['classroom_id'], ['mcq'], $json, 'MCQ only');

        $quiz = Quiz::query()->where('title', 'MCQ only')->firstOrFail();
        $this->assertSame(['mcq'], $quiz->selected_question_types);
        $this->assertDatabaseHas('questions', [
            'quiz_id' => $quiz->id,
            'type' => 'mcq',
            'pool_status' => 'draft',
        ]);
    }

    public function test_create_true_false_only_assessment(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'true_false',
                'question_text' => 'Sky is blue?',
                'marks' => 1,
                'correct_answer' => true,
            ],
        ]);

        $this->storeWithImport($ctx['examiner'], $ctx['course_id'], $ctx['classroom_id'], ['true_false'], $json, 'TF only');

        $quiz = Quiz::query()->where('title', 'TF only')->firstOrFail();
        $this->assertSame(['true_false'], $quiz->selected_question_types);
        $this->assertDatabaseHas('questions', ['quiz_id' => $quiz->id, 'type' => 'true_false', 'pool_status' => 'draft']);
    }

    public function test_create_fill_blank_only_assessment(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'fill_blank',
                'question_text' => 'Capital of Ghana is ___',
                'marks' => 1,
                'correct_answer' => ['Accra'],
            ],
        ]);

        $this->storeWithImport($ctx['examiner'], $ctx['course_id'], $ctx['classroom_id'], ['fill_blank'], $json, 'Fill only');

        $quiz = Quiz::query()->where('title', 'Fill only')->firstOrFail();
        $this->assertSame(['fill_blank'], $quiz->selected_question_types);
        $this->assertDatabaseHas('questions', ['quiz_id' => $quiz->id, 'type' => 'fill_blank', 'pool_status' => 'draft']);
    }

    public function test_create_essay_only_assessment(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'essay',
                'question_text' => 'Discuss thermodynamics.',
                'marks' => 5,
            ],
        ]);

        $this->storeWithImport($ctx['examiner'], $ctx['course_id'], $ctx['classroom_id'], ['essay'], $json, 'Essay only');

        $quiz = Quiz::query()->where('title', 'Essay only')->firstOrFail();
        $this->assertSame(['essay'], $quiz->selected_question_types);
        $this->assertDatabaseHas('questions', ['quiz_id' => $quiz->id, 'type' => 'essay', 'pool_status' => 'draft']);
    }

    public function test_create_mixed_question_types(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'mcq',
                'question_text' => '2+2?',
                'marks' => 1,
                'options' => ['3', '4'],
                'correct_answer' => [1],
            ],
            [
                'type' => 'essay',
                'question_text' => 'Explain.',
                'marks' => 2,
            ],
        ]);

        $this->storeWithImport(
            $ctx['examiner'],
            $ctx['course_id'],
            $ctx['classroom_id'],
            ['mcq', 'essay'],
            $json,
            'Mixed types'
        );

        $quiz = Quiz::query()->where('title', 'Mixed types')->firstOrFail();
        $this->assertEqualsCanonicalizing(['mcq', 'essay'], $quiz->selected_question_types);
        $this->assertSame(2, Question::query()->where('quiz_id', $quiz->id)->count());
    }

    public function test_create_rejects_import_when_type_not_selected(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'true_false',
                'question_text' => 'Test?',
                'marks' => 1,
                'correct_answer' => false,
            ],
        ]);

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.exams.store'), [
                'wizard_step' => 2,
                'course_id' => $ctx['course_id'],
                'classroom_ids' => [$ctx['classroom_id']],
                'assessment_type' => 'quiz',
                'title' => 'Bad import',
                'duration_minutes' => 30,
                'question_source' => 'paste_json',
                'import_json' => $json,
                'questions_per_student' => 1,
                'randomize_questions' => '1',
                'randomize_options' => '1',
                'selected_question_types' => ['mcq'],
            ])
            ->assertSessionHasErrors('import_json');

        $this->assertNull(Quiz::query()->where('title', 'Bad import')->first());
    }

    public function test_preview_import_rejects_type_not_enabled_on_existing_exam(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.prev.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'PREV1',
            'title' => 'Prev',
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
            'title' => 'Draft',
            'description' => null,
            'assessment_type' => 'exam',
            'selected_question_types' => json_encode(['mcq']),
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
        $exam = Quiz::query()->findOrFail($quizId);

        $payload = $this->importPayload([
            [
                'type' => 'essay',
                'question_text' => 'Long',
                'marks' => 1,
            ],
        ]);

        $this->actingAs($examiner)
            ->from(route('examiner.quizzes.workspace', $exam))
            ->post(route('examiner.exams.questions.import.preview', $exam), [
                'import_json' => $payload,
            ])
            ->assertSessionHasErrors('import_json');
    }

    public function test_publish_blocked_without_approved_questions(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'mcq',
                'question_text' => 'Q',
                'marks' => 1,
                'options' => ['0', '1'],
                'correct_answer' => [0],
            ],
        ]);
        $this->storeWithImport($ctx['examiner'], $ctx['course_id'], $ctx['classroom_id'], ['mcq'], $json, 'Draft pool');

        $exam = Quiz::query()->where('title', 'Draft pool')->firstOrFail();
        $this->assertDatabaseHas('questions', ['quiz_id' => $exam->id, 'pool_status' => 'draft']);

        $this->actingAs($ctx['examiner']);
        $exam->update(['questions_per_student' => 1]);
        $this->post(route('examiner.exams.publish', $exam->fresh()))
            ->assertSessionHasErrors('lifecycle');
    }

    public function test_publish_allowed_when_approved_pool_matches_types(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'mcq',
                'question_text' => 'Q',
                'marks' => 2,
                'options' => ['0', '1'],
                'correct_answer' => [0],
            ],
        ]);
        $this->storeWithImport($ctx['examiner'], $ctx['course_id'], $ctx['classroom_id'], ['mcq'], $json, 'Publish ok');

        $exam = Quiz::query()->where('title', 'Publish ok')->firstOrFail();
        $exam->update(['questions_per_student' => 1]);

        $this->actingAs($ctx['examiner'])
            ->from(route('examiner.quizzes.workspace', $exam))
            ->patch(route('examiner.exams.questions.pool-status.bulk', $exam), [
                'pool_status' => 'approved',
                'mode' => 'all',
            ])
            ->assertRedirect();

        $this->post(route('examiner.exams.publish', $exam->fresh()))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $this->assertSame('published', $exam->fresh()->status);
    }

    public function test_publish_validation_rejects_approved_question_type_outside_selection(): void
    {
        $ctx = $this->seedExaminerWithCourse();
        $json = $this->importPayload([
            [
                'type' => 'mcq',
                'question_text' => 'Q',
                'marks' => 2,
                'options' => ['0', '1'],
                'correct_answer' => [0],
            ],
        ]);
        $this->storeWithImport($ctx['examiner'], $ctx['course_id'], $ctx['classroom_id'], ['mcq', 'essay'], $json, 'Mismatch types');

        $exam = Quiz::query()->where('title', 'Mismatch types')->firstOrFail();
        $exam->update(['questions_per_student' => 1]);
        $q = Question::query()->where('quiz_id', $exam->id)->firstOrFail();
        $q->update(['pool_status' => 'approved']);

        DB::table('quizzes')->where('id', $exam->id)->update([
            'selected_question_types' => json_encode(['essay']),
        ]);

        $errors = app(ExamLifecycleService::class)->publishValidationErrors($exam->fresh());
        $this->assertNotEmpty($errors);
        $this->assertTrue(collect($errors)->contains(fn ($m) => str_contains((string) $m, 'not enabled')));
    }
}
