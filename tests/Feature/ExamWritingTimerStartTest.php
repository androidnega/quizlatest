<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\User;
use App\Support\AssessmentProctoringDefaults;
use App\Support\ExamSessionTimer;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamWritingTimerStartTest extends TestCase
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
            'code' => 'TIMER-CTX',
            'title' => 'Timer test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'TimerTestClass',
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

    public function test_writing_timer_starts_on_first_state_not_at_session_create(): void
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
            'email' => 'timer-ex.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Timer anchor exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'proctoring_settings' => json_encode(AssessmentProctoringDefaults::baselineForType('exam', true, true, true)),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('quiz_class')->insert([
            'quiz_id' => $quizId,
            'class_id' => $classId,
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now()->subMinutes(5),
            'writing_started_at' => null,
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'active',
            'last_seen_at' => now(),
            'accumulated_pause_seconds' => 0,
        ]);

        $session->load('exam');
        $remainingBefore = ExamSessionTimer::timeRemainingSeconds($session, $session->exam, now());
        $this->assertLessThan(30 * 60, $remainingBefore);
        $this->assertGreaterThan(24 * 60, $remainingBefore);

        $json = $this->actingAs($student)
            ->getJson(route('exam-sessions.state', $session))
            ->assertOk()
            ->json();

        $session->refresh();
        $this->assertNotNull($session->writing_started_at);
        $this->assertGreaterThanOrEqual(29 * 60 + 50, (int) ($json['time_remaining_seconds'] ?? 0));
        $this->assertLessThanOrEqual(30 * 60, (int) ($json['time_remaining_seconds'] ?? 0));
    }
}
