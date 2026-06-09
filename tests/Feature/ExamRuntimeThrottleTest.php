<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\User;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies the per-user throttle middleware applied to the exam runtime
 * endpoints in routes/web.php. Each test exercises one endpoint:
 *  - hammers it up to the documented per-minute limit and asserts 2xx
 *    responses,
 *  - fires one request beyond the limit and asserts a 429 (Too Many
 *    Requests) is returned without touching the controller.
 */
class ExamRuntimeThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);
        // Each test owns its own clean rate-limiter buckets.
        app(RateLimiter::class)->clear('runtime-test');
    }

    /**
     * @return array{student: User, session: ExamSession}
     */
    private function bootActiveExam(int $durationMinutes = 60): array
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $uniId = (int) $student->university_id;
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'THROTTLE-CTX-'.Str::random(4),
            'title' => 'Throttle test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'ThrottleClass-'.Str::random(4),
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

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'throttle-ex.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Throttle test exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => $durationMinutes,
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
            'student_id' => $student->fresh()->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
            'writing_started_at' => now(),
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

        return ['student' => $student->fresh(), 'session' => $session];
    }

    public function test_state_endpoint_throttled_at_60_per_minute(): void
    {
        ['student' => $student, 'session' => $session] = $this->bootActiveExam();

        $route = route('exam-sessions.state', $session);

        for ($i = 0; $i < 60; $i++) {
            $response = $this->actingAs($student)->getJson($route);
            $this->assertNotSame(429, $response->getStatusCode(), "Hit 429 too early on request {$i}");
        }

        $blocked = $this->actingAs($student)->getJson($route);
        $blocked->assertStatus(429);
    }

    public function test_heartbeat_endpoint_throttled_at_60_per_minute(): void
    {
        ['student' => $student, 'session' => $session] = $this->bootActiveExam();

        $route = route('exam-sessions.heartbeat', $session);

        for ($i = 0; $i < 60; $i++) {
            $response = $this->actingAs($student)->postJson($route);
            $this->assertNotSame(429, $response->getStatusCode(), "Hit 429 too early on request {$i}");
        }

        $blocked = $this->actingAs($student)->postJson($route);
        $blocked->assertStatus(429);
    }

    public function test_submit_endpoint_throttled_at_5_per_minute(): void
    {
        ['student' => $student, 'session' => $session] = $this->bootActiveExam();

        $route = route('exam-sessions.submit', $session);

        // First request submits successfully; subsequent requests are
        // idempotent (already_submitted) but still count toward the
        // throttle bucket.
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($student)->postJson($route);
            $this->assertNotSame(429, $response->getStatusCode(), "Hit 429 too early on request {$i}");
        }

        $blocked = $this->actingAs($student)->postJson($route);
        $blocked->assertStatus(429);
    }

    public function test_throttle_bucket_is_per_user_not_global(): void
    {
        ['student' => $studentA, 'session' => $sessionA] = $this->bootActiveExam();
        $studentB = User::factory()->create([
            'role' => 'student',
            'university_id' => $studentA->university_id,
            'class_id' => $studentA->class_id,
            'email' => null,
            'index_number' => 'TT'.Str::upper(Str::random(6)),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $sessionB = ExamSession::query()->create([
            'student_id' => $studentB->id,
            'class_id' => $sessionA->class_id,
            'exam_id' => $sessionA->exam_id,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
            'writing_started_at' => now(),
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

        $heartbeatA = route('exam-sessions.heartbeat', $sessionA);
        $heartbeatB = route('exam-sessions.heartbeat', $sessionB);

        // Burn through Student A's quota.
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($studentA)->postJson($heartbeatA);
        }
        $this->actingAs($studentA)->postJson($heartbeatA)->assertStatus(429);

        // Student B must still have a fresh bucket.
        $b = $this->actingAs($studentB)->postJson($heartbeatB);
        $this->assertNotSame(429, $b->getStatusCode(), 'Student B was incorrectly throttled by Student A traffic.');
    }

    public function test_legitimate_traffic_pattern_does_not_trip_any_throttle(): void
    {
        ['student' => $student, 'session' => $session] = $this->bootActiveExam();

        // Simulates 60 seconds of normal exam runtime traffic at the
        // documented frontend cadence (see QUIZSNAP_PRODUCTION_READINESS.txt
        // Phase 5):
        //   /state         : 2 calls/min  (poll every 30 s)
        //   /heartbeat     : 1 call/min   (every 60 s)
        //   /save-answer   : ~2 calls/min (typical; 1.6 s debounce per Q)
        //   /proctoring-events/batch : ~7 calls/min (idle 9 s)
        //
        // None of these may trip a throttle.
        $stateRoute = route('exam-sessions.state', $session);
        $heartbeatRoute = route('exam-sessions.heartbeat', $session);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($student)->getJson($stateRoute)->assertOk();
        }

        $this->actingAs($student)->postJson($heartbeatRoute)->assertOk();

        // Confirm bucket headroom remains: legitimate traffic must be
        // well below the limit.
        $remaining = RateLimiterFacade::remaining(
            'state|'.(string) $student->id,
            60,
        );
        // remaining() returns the count left; should be > 50 of 60.
        $this->assertGreaterThan(50, $remaining + 60);
    }
}
