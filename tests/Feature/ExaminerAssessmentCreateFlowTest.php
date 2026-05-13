<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
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
            ->assertSee('Save and continue', false);
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

        $topics = $this->actingAs($ctx['examiner'])
            ->postJson(route('examiner.exams.create.outline-suggest-topics'), [
                'ai_outline_file' => $file,
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['topics'])
            ->json('topics');

        $this->assertIsArray($topics);
        $this->assertGreaterThan(0, count($topics));
    }
}
