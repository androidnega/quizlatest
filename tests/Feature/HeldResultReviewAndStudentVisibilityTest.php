<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HeldResultReviewAndStudentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{coord: User, exam: Quiz, session: ExamSession, student: User}
     */
    private function seedExamSessionContext(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
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
            'created_by' => $coord->id,
            'title' => 'Midterm',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'proctoring_settings' => json_encode(new \stdClass),
            'available_from' => null,
            'available_to' => null,
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
            'violation_count' => 1,
            'violation_score' => 40,
            'violation_events' => [],
            'risk_state' => 'locked',
            'exam_status' => 'submitted_held',
        ]);

        Result::query()->create([
            'user_id' => $student->id,
            'quiz_id' => $quizId,
            'score' => 72.5,
            'time_taken' => 120,
            'status' => 'held',
            'exam_status' => 'submitted_held',
            'submitted_at' => now(),
        ]);

        $exam = Quiz::query()->findOrFail($quizId);

        return ['coord' => $coord, 'exam' => $exam, 'session' => $session, 'student' => $student];
    }

    public function test_department_coordinator_without_examiner_assignment_cannot_release_held(): void
    {
        $ctx = $this->seedExamSessionContext();

        $this->actingAs($ctx['coord']);
        $this->postJson(route('exam-sessions.review.release', $ctx['session']))
            ->assertForbidden();
    }

    public function test_examiner_assigned_to_course_can_release_held(): void
    {
        $ctx = $this->seedExamSessionContext();

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $ctx['exam']->course_id,
            'examiner_user_id' => $ctx['coord']->id,
            'assigned_by' => null,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($ctx['coord']);
        $this->postJson(route('exam-sessions.review.release', $ctx['session']))
            ->assertOk()
            ->assertJson(['status' => 'released']);
    }

    public function test_student_state_hides_scores_when_result_held(): void
    {
        $ctx = $this->seedExamSessionContext();

        $this->actingAs($ctx['student']);
        $response = $this->getJson(route('exam-sessions.state', $ctx['session']));

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayNotHasKey('violation_score', $data);
        $this->assertFalse($data['result_visible']);
        $this->assertSame('Your result is under review. Please contact your lecturer.', $data['result_message']);
        $this->assertArrayNotHasKey('total_marks', $data['exam'] ?? []);
    }
}
