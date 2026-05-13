<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, exam: Quiz}
     */
    private function seedDraftExam(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.import.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS999',
            'title' => 'Import Test Course',
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
            'title' => 'Draft import exam',
            'description' => null,
            'assessment_type' => 'exam',
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

    public function test_preview_rejects_malformed_json(): void
    {
        $ctx = $this->seedDraftExam();

        $this->actingAs($ctx['examiner']);
        $response = $this->from(route('examiner.quizzes.workspace', $ctx['exam']))
            ->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
                'import_json' => '{',
            ]);

        $response->assertSessionHasErrors('import_json');
    }

    public function test_preview_then_commit_imports_questions(): void
    {
        $ctx = $this->seedDraftExam();

        $payload = [
            'sections' => [
                [
                    'title' => 'Imported block',
                    'questions' => [
                        [
                            'type' => 'mcq',
                            'question_text' => '2+2?',
                            'marks' => 1,
                            'options' => ['3', '4', '5'],
                            'correct_answer' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($ctx['examiner']);

        $this->post(route('examiner.exams.questions.import.preview', $ctx['exam']), [
            'import_json' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $this->assertNotNull(session('exam_question_import_'.$ctx['exam']->id));

        $this->post(route('examiner.exams.questions.import.commit', $ctx['exam']))
            ->assertRedirect();

        $this->assertNull(session('exam_question_import_'.$ctx['exam']->id));

        $ctx['exam']->refresh();
        $this->assertGreaterThan(0, (float) $ctx['exam']->total_marks);

        $this->assertDatabaseHas('exam_sections', [
            'exam_id' => $ctx['exam']->id,
            'title' => 'Imported block',
        ]);

        $this->assertDatabaseHas('questions', [
            'quiz_id' => $ctx['exam']->id,
            'type' => 'mcq',
            'pool_status' => 'draft',
        ]);
    }

    public function test_ai_panel_hidden_when_disabled(): void
    {
        $ctx = $this->seedDraftExam();

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_ai', 'false', $admin);

        $this->actingAs($ctx['examiner']);
        $html = $this->get(route('examiner.quizzes.workspace', $ctx['exam']))->assertOk()->getContent();
        $this->assertStringNotContainsString('Generate with AI (internal)', $html);
    }
}
