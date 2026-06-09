<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoordinatorExamSessionReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, coord: User, exam: Quiz, session: ExamSession}
     */
    private function seedScopedExamContext(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.sessions.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS101',
            'title' => 'Intro CS',
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

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'A',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Midterm',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'questions_per_student' => 1,
            'randomize_questions' => false,
            'randomize_options' => false,
            'proctoring_settings' => json_encode(new \stdClass),
            'published_at' => null,
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::query()->where('role', 'student')->firstOrFail();

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
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

        $exam = Quiz::query()->findOrFail($quizId);

        return ['examiner' => $examiner->fresh(), 'coord' => $coord, 'exam' => $exam, 'session' => $session];
    }

    public function test_guest_redirected_from_sessions_index(): void
    {
        $ctx = $this->seedScopedExamContext();

        $this->get(route('examiner.exams.sessions.index', $ctx['exam']))
            ->assertRedirect();
    }

    public function test_examiner_can_view_sessions_index(): void
    {
        $ctx = $this->seedScopedExamContext();

        $this->actingAs($ctx['examiner']);
        $this->get(route('examiner.quizzes.workspace', ['exam' => $ctx['exam'], 'tab' => 'sessions']))
            ->assertOk()
            ->assertSeeText('Question analytics')
            ->assertSeeText('Student results')
            ->assertSeeText($ctx['session']->student->name);
    }

    public function test_examiner_can_view_session_detail(): void
    {
        $ctx = $this->seedScopedExamContext();

        $this->actingAs($ctx['examiner']);
        $this->get(route('examiner.exam-sessions.show', [
            'exam' => $ctx['session']->exam,
            'examSession' => $ctx['session'],
        ]))
            ->assertOk()
            ->assertSeeText('Violation log');
    }

    public function test_coordinator_cannot_view_examiner_sessions_index(): void
    {
        $ctx = $this->seedScopedExamContext();

        $this->actingAs($ctx['coord']);
        $this->get(route('examiner.exams.sessions.index', $ctx['exam']))
            ->assertForbidden();
    }

    public function test_examiner_cannot_view_exam_sessions_without_course_assignment(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.orphan.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => null,
            'code' => 'ORPHAN',
            'title' => 'No dept',
            'credit_hours' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'X',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Other',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'questions_per_student' => 1,
            'randomize_questions' => false,
            'randomize_options' => false,
            'proctoring_settings' => json_encode(new \stdClass),
            'published_at' => null,
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exam = Quiz::query()->findOrFail($quizId);

        ExamSession::query()->create([
            'student_id' => User::query()->where('role', 'student')->value('id'),
            'class_id' => $classId,
            'exam_id' => $quizId,
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

        $this->actingAs($examiner);
        $this->get(route('examiner.exams.sessions.index', $exam))
            ->assertForbidden();
    }
}
