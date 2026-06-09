<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\User;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Live-Ops Phase 2 — coordinator Exam Command Center.
 */
class ExamCommandCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);
    }

    private function bootCoordinatorWithLiveExam(): array
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $uniId = (int) $student->university_id;
        $deptRow = DB::table('departments')->where('code', 'CS')->first(['id', 'faculty_id']);
        $deptId = (int) $deptRow->id;
        $facultyId = (int) $deptRow->faculty_id;
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'university_id' => $uniId,
            'email' => 'coord-cc.'.Str::random(6).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        DB::table('coordinator_assignments')->insert([
            'user_id' => $coordinator->id,
            'faculty_id' => $facultyId,
            'department_id' => $deptId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CC-'.Str::random(4),
            'title' => 'CC ctx',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'CC-'.Str::random(4),
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('class_course')->insert([
            'class_id' => $classId, 'course_id' => $courseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'cc-ex.'.Str::random(6).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = (int) DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'CC quiz',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'proctoring_settings' => json_encode(AssessmentProctoringDefaults::baselineForType('exam', true, true, true)),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('quiz_class')->insert(['quiz_id' => $quizId, 'class_id' => $classId]);

        $session = ExamSession::query()->create([
            'student_id' => $student->fresh()->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
            'writing_started_at' => now(),
            'end_time' => null,
            'violation_count' => 1,
            'violation_score' => 25,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'warning',
            'exam_status' => 'active',
            'last_seen_at' => now(),
            'accumulated_pause_seconds' => 0,
            'extra_seconds' => 0,
        ]);

        // One tab_switch event in the last hour so the violations
        // counter has something to surface.
        ProctoringEvent::query()->create([
            'user_id' => $student->id,
            'quiz_id' => $quizId,
            'exam_session_id' => $session->id,
            'session_id' => $session->session_id,
            'event_type' => 'tab_switch',
            'severity' => 1,
            'flagged' => false,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['coordinator' => $coordinator, 'session' => $session, 'quizId' => $quizId];
    }

    public function test_dashboard_index_renders(): void
    {
        $ctx = $this->bootCoordinatorWithLiveExam();

        $resp = $this->actingAs($ctx['coordinator'])->get(route('coordinator.command-center.index'));
        $resp->assertOk();
        $resp->assertSee('Exam Command Center');
        $resp->assertSee('command-center/metrics');
    }

    public function test_metrics_endpoint_returns_live_session_and_violation_counts(): void
    {
        $ctx = $this->bootCoordinatorWithLiveExam();

        $payload = $this->actingAs($ctx['coordinator'])
            ->getJson(route('coordinator.command-center.metrics'))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('sessions', $payload);
        $this->assertArrayHasKey('violations', $payload);
        $this->assertArrayHasKey('submissions', $payload);

        $this->assertSame(1, (int) $payload['sessions']['active']);
        $this->assertSame(1, (int) $payload['sessions']['students_writing']);
        $this->assertSame(1, (int) $payload['sessions']['exams_running']);

        $this->assertSame(1, (int) $payload['violations']['with_any_violation']);
        $this->assertSame(1, (int) $payload['violations']['tab_switch']);
    }

    public function test_metrics_endpoint_is_scoped_per_university(): void
    {
        // Coordinator at university A should not see university B's
        // session counters. We boot the live session at uni A, then
        // create a coordinator at uni B and confirm zeros come back.
        $this->bootCoordinatorWithLiveExam();

        $otherUni = DB::table('universities')->insertGetId([
            'name' => 'Other Uni',
            'code' => 'OUNI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherCoord = User::factory()->create([
            'role' => 'coordinator',
            'university_id' => $otherUni,
            'email' => 'other-coord.'.Str::random(5).'@x.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $payload = $this->actingAs($otherCoord)
            ->getJson(route('coordinator.command-center.metrics'))
            ->assertOk()
            ->json();

        $this->assertSame(0, (int) $payload['sessions']['active']);
        $this->assertSame(0, (int) $payload['sessions']['students_writing']);
        $this->assertSame(0, (int) $payload['violations']['tab_switch']);
    }

    public function test_metrics_endpoint_includes_snapshot_after_command_runs(): void
    {
        $ctx = $this->bootCoordinatorWithLiveExam();

        Artisan::call('qs:monitor:snapshot');

        $payload = $this->actingAs($ctx['coordinator'])
            ->getJson(route('coordinator.command-center.metrics'))
            ->assertOk()
            ->json();

        $this->assertNotNull($payload['snapshot']);
        $this->assertArrayHasKey('captured_at', $payload['snapshot']);
        $this->assertSame(1, (int) $payload['snapshot']['active_sessions']);
    }

    public function test_non_coordinator_users_cannot_reach_the_dashboard(): void
    {
        $this->bootCoordinatorWithLiveExam();
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('coordinator.command-center.index'))
            ->assertForbidden();
    }
}
