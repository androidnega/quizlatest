<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExaminerQuizIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   examinerA: User,
     *   examinerB: User,
     *   admin: User,
     *   courseId: int,
     *   classroomId: int,
     *   quizA: Quiz,
     *   quizB: Quiz,
     *   sessionB: ExamSession,
     *   answerB: ExamSessionAnswer
     * }
     */
    private function seedTwoExaminerContext(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $admin = User::query()->where('email', 'admin')->firstOrFail();

        $examinerA = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner-a-'.Str::lower(Str::random(6)).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $examinerB = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner-b-'.Str::lower(Str::random(6)).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'ISO101',
            'title' => 'Isolation Course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$examinerA, $examinerB] as $examiner) {
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
        }

        $quizA = Quiz::query()->create([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examinerA->id,
            'title' => 'Exam A Ownership',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'total_marks' => 100,
        ]);
        $quizB = Quiz::query()->create([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examinerB->id,
            'title' => 'Exam B Ownership',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'total_marks' => 100,
        ]);

        $classroom = Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'ISO-CLASS',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
        ]);
        DB::table('class_course')->insert([
            'class_id' => $classroom->id,
            'course_id' => $courseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $student = User::query()->where('role', 'student')->firstOrFail();
        $student->update(['class_id' => $classroom->id]);

        $sessionB = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classroom->id,
            'exam_id' => $quizB->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
        ]);

        $section = ExamSection::query()->create([
            'exam_id' => $quizB->id,
            'title' => 'Section 1',
            'section_order' => 1,
        ]);

        $essay = Question::query()->create([
            'quiz_id' => $quizB->id,
            'section_id' => $section->id,
            'question_text' => 'Explain ACID properties.',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $answerB = ExamSessionAnswer::query()->create([
            'exam_session_id' => $sessionB->id,
            'question_id' => $essay->id,
            'answer_text' => 'Sample answer',
            'answer_payload' => null,
            'points_awarded' => 0,
            'evaluation_status' => 'pending_manual',
            'evaluation_detail' => null,
            'grader_feedback' => null,
            'saved_at' => now(),
            'client_revision' => 1,
        ]);

        DB::table('results')->updateOrInsert(
            [
                'user_id' => $student->id,
                'quiz_id' => $quizB->id,
            ],
            [
                'score' => 0,
                'status' => 'held',
                'time_taken' => 0,
                'exam_status' => 'submitted_held',
                'submitted_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return [
            'examinerA' => $examinerA,
            'examinerB' => $examinerB,
            'admin' => $admin,
            'courseId' => $courseId,
            'classroomId' => $classroom->id,
            'quizA' => $quizA,
            'quizB' => $quizB,
            'sessionB' => $sessionB,
            'answerB' => $answerB,
        ];
    }

    public function test_examiner_list_shows_only_owned_quizzes_even_on_same_course(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.exams.index'))
            ->assertOk()
            ->assertSee('Exam A Ownership', false)
            ->assertDontSee('Exam B Ownership', false);
    }

    public function test_examiner_dashboard_shows_assigned_course_and_linked_class_context_only(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.dashboard'))
            ->assertOk()
            ->assertDontSee('Exam B Ownership', false)
            ->assertSee(__('All assessments'), false)
            ->assertSee(__('Courses'), false)
            ->assertSee(__('Classes'), false)
            ->assertSee(__('Grading'), false);
    }

    public function test_examiner_cannot_builder_publish_or_archive_other_examiner_quiz(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.quizzes.workspace', $ctx['quizB']))
            ->assertForbidden();

        $this->actingAs($ctx['examinerA'])
            ->post(route('examiner.exams.publish', $ctx['quizB']))
            ->assertForbidden();

        $this->actingAs($ctx['examinerA'])
            ->post(route('examiner.exams.archive', $ctx['quizB']))
            ->assertForbidden();
    }

    public function test_examiner_cannot_grade_other_examiner_essay_answers(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.grading.show', $ctx['answerB']))
            ->assertForbidden();

        $this->actingAs($ctx['examinerA'])
            ->post(route('examiner.grading.grade', $ctx['answerB']), [
                'points_awarded' => 5,
                'grader_feedback' => 'No access',
            ])
            ->assertForbidden();
    }

    public function test_examiner_grading_queue_excludes_other_examiner_pending_essays(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.grading.pending'))
            ->assertOk()
            ->assertSee('No pending essay answers.', false)
            ->assertDontSee('Exam B Ownership', false);
    }

    public function test_examiner_cannot_review_other_examiner_sessions_or_evidence(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.exams.sessions.index', $ctx['quizB']))
            ->assertForbidden();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.exam-sessions.show', $ctx['sessionB']))
            ->assertForbidden();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.exam-sessions.evidence.verification', $ctx['sessionB']))
            ->assertForbidden();
    }

    public function test_examiner_dashboard_held_and_manual_counts_exclude_other_examiner_data(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.dashboard'))
            ->assertOk()
            ->assertSee('Held results', false)
            ->assertSee('Pending manual grading', false)
            ->assertDontSee('Exam B Ownership', false);
    }

    public function test_admin_policy_allows_exam_owner_overrides(): void
    {
        $ctx = $this->seedTwoExaminerContext();

        $this->assertTrue($ctx['admin']->can('view', $ctx['quizA']));
        $this->assertTrue($ctx['admin']->can('update', $ctx['quizB']));
        $this->assertTrue($ctx['admin']->can('manageResults', $ctx['quizB']));
    }
}
