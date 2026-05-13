<?php

namespace Tests\Feature;

use App\Console\Commands\PauseStaleExamSessionsCommand;
use App\Models\ExamSession;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamSessionDisconnectPauseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{student: User, classId: int, courseId: int, uniId: int}
     */
    private function attachStudentToSeededCourse(): array
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $uniId = (int) $student->university_id;
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'PAUSE-CTX',
            'title' => 'Pause test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'PauseTestClass',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('class_course')->insert([
            'class_id' => $classId,
            'course_id' => $courseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        return ['student' => $student->fresh(), 'classId' => $classId, 'courseId' => $courseId, 'uniId' => $uniId];
    }

    public function test_stale_active_session_is_paused_by_scheduled_command_and_can_resume(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $ctx = $this->attachStudentToSeededCourse();
        $student = $ctx['student'];
        $classId = $ctx['classId'];
        $courseId = $ctx['courseId'];
        $uniId = $ctx['uniId'];

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'ex.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Pause test exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'proctoring_settings' => json_encode(new \stdClass),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = now()->subMinutes(5);
        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => $start,
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'active',
            'last_seen_at' => now()->subMinutes(10),
            'pause_segment_started_at' => null,
            'accumulated_pause_seconds' => 0,
        ]);

        $this->artisan(PauseStaleExamSessionsCommand::class)->assertSuccessful();

        $session->refresh();
        $this->assertSame('paused', $session->status);
        $this->assertNotNull($session->pause_segment_started_at);

        $this->actingAs($student);
        $this->postJson(route('exam-sessions.resume', $session))
            ->assertOk()
            ->assertJsonFragment(['status' => 'resumed']);

        $session->refresh();
        $this->assertSame('active', $session->status);
        $this->assertNull($session->pause_segment_started_at);
        $this->assertGreaterThan(0, (int) $session->accumulated_pause_seconds);
    }

    public function test_time_remaining_respects_accumulated_pause(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $ctx = $this->attachStudentToSeededCourse();
        $student = $ctx['student'];
        $classId = $ctx['classId'];
        $courseId = $ctx['courseId'];
        $uniId = $ctx['uniId'];

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'ex2.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Timer pause exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'proctoring_settings' => json_encode(new \stdClass),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $start = now()->subMinutes(30);
        ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'paused',
            'start_time' => $start,
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'active',
            'last_seen_at' => now(),
            'pause_segment_started_at' => now()->subMinutes(20),
            'accumulated_pause_seconds' => 600,
        ]);

        $session = ExamSession::query()
            ->where('student_id', $student->id)
            ->where('exam_id', $quizId)
            ->firstOrFail();

        $this->actingAs($student);
        $json = $this->getJson(route('exam-sessions.state', $session))->assertOk()->json();
        $this->assertTrue($json['timer_paused'] ?? false);
        $this->assertGreaterThan(30 * 60, (int) ($json['time_remaining_seconds'] ?? 0));
    }
}
