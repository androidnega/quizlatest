<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\ExaminerCourseAssignment;
use App\Models\User;
use App\Services\ExaminerEmergencyAuditService;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Live-Ops Phase 5 — examiner emergency tools.
 */
class ExaminerEmergencyToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);
    }

    /**
     * @return array{examiner: User, student: User, session: ExamSession, quizId: int}
     */
    private function bootContext(int $durationMinutes = 60): array
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $uniId = (int) $student->university_id;
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'EM-'.Str::random(4),
            'title' => 'Emergency tools ctx',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'EmergencyClass-'.Str::random(4),
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
            'email' => 'em-ex.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        ExaminerCourseAssignment::query()->create([
            'examiner_user_id' => $examiner->id,
            'course_id' => $courseId,
            'is_active' => true,
        ]);

        $quizId = (int) DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Emergency tools quiz',
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
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'active',
            'last_seen_at' => now(),
            'accumulated_pause_seconds' => 0,
            'extra_seconds' => 0,
        ]);

        return [
            'examiner' => $examiner,
            'student' => $student->fresh(),
            'session' => $session,
            'quizId' => $quizId,
        ];
    }

    public function test_extend_time_adds_minutes_and_writes_audit_row(): void
    {
        $ctx = $this->bootContext();

        $resp = $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.extend-time', $ctx['session']),
            ['minutes' => 15, 'reason' => 'Power outage in lab 2.'],
        );

        $resp->assertOk()->assertJson([
            'status' => 'ok',
            'extra_seconds' => 15 * 60,
            'extra_minutes' => 15,
        ]);

        $this->assertSame(900, (int) $ctx['session']->fresh()->extra_seconds);

        $audit = DB::table('activity_logs')
            ->where('event_type', ExaminerEmergencyAuditService::EVENT_EXTEND_TIME)
            ->where('quiz_id', $ctx['quizId'])
            ->first();
        $this->assertNotNull($audit);
        $payload = json_decode((string) $audit->event_data, true);
        $this->assertSame(15, (int) $payload['minutes_delta']);
        $this->assertSame($ctx['session']->id, (int) $payload['exam_session_id']);
    }

    public function test_extend_time_can_remove_time_but_clamps_at_zero(): void
    {
        $ctx = $this->bootContext();
        $ctx['session']->forceFill(['extra_seconds' => 600])->save();

        // Remove 30 minutes — would go negative, must clamp at zero.
        $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.extend-time', $ctx['session']),
            ['minutes' => -30],
        )->assertOk()->assertJson(['extra_seconds' => 0]);
    }

    public function test_extend_time_grants_extra_remaining_time_in_state_payload(): void
    {
        $ctx = $this->bootContext(durationMinutes: 30);

        $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.extend-time', $ctx['session']),
            ['minutes' => 10],
        )->assertOk();

        $statePayload = $this->actingAs($ctx['student'])
            ->getJson(route('exam-sessions.state', $ctx['session']))
            ->assertOk()
            ->json();

        $this->assertGreaterThanOrEqual(
            // Original 30m minus a few startup seconds + 10m extra = >= 39m50s.
            (30 + 10) * 60 - 30,
            (int) $statePayload['time_remaining_seconds'],
        );
    }

    public function test_unlock_moves_paused_session_back_to_active_and_preserves_pause_budget(): void
    {
        $ctx = $this->bootContext();

        $ctx['session']->forceFill([
            'status' => 'paused',
            'pause_segment_started_at' => now()->subMinutes(3),
            'accumulated_pause_seconds' => 0,
        ])->save();

        $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.unlock', $ctx['session']),
            ['reason' => 'Resumed after Wi-Fi recovery'],
        )->assertOk()->assertJson(['status' => 'unlocked']);

        $row = $ctx['session']->fresh();
        $this->assertSame('active', $row->status);
        $this->assertNull($row->pause_segment_started_at);
        $this->assertGreaterThanOrEqual(60 * 3 - 5, (int) $row->accumulated_pause_seconds);
        $this->assertNotNull($row->examiner_unlocked_at);
        $this->assertSame((int) $ctx['examiner']->id, (int) $row->examiner_unlocked_by);

        $audit = DB::table('activity_logs')
            ->where('event_type', ExaminerEmergencyAuditService::EVENT_UNLOCK_SESSION)
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_unlock_refuses_to_revive_a_submitted_session(): void
    {
        $ctx = $this->bootContext();
        $ctx['session']->forceFill(['status' => 'submitted', 'exam_status' => 'graded'])->save();

        $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.unlock', $ctx['session']),
            ['reason' => 'oops'],
        )->assertStatus(422);
    }

    public function test_override_decision_changes_exam_status_with_audit(): void
    {
        $ctx = $this->bootContext();
        $ctx['session']->forceFill(['status' => 'submitted', 'exam_status' => 'submitted_held'])->save();

        $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.override', $ctx['session']),
            ['exam_status' => 'graded', 'reason' => 'Verified power outage genuine.'],
        )->assertOk();

        $this->assertSame('graded', (string) $ctx['session']->fresh()->exam_status);

        $audit = DB::table('activity_logs')
            ->where('event_type', ExaminerEmergencyAuditService::EVENT_OVERRIDE_DECISION)
            ->first();
        $payload = json_decode((string) $audit->event_data, true);
        $this->assertSame('submitted_held', $payload['previous_exam_status']);
        $this->assertSame('graded', $payload['new_exam_status']);
    }

    public function test_override_requires_a_meaningful_reason(): void
    {
        $ctx = $this->bootContext();

        $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.override', $ctx['session']),
            ['exam_status' => 'graded', 'reason' => 'short'],
        )->assertStatus(422);
    }

    public function test_audit_trail_endpoint_returns_recorded_actions_for_the_session(): void
    {
        $ctx = $this->bootContext();

        $this->actingAs($ctx['examiner'])->postJson(
            route('examiner.exam-sessions.emergency.extend-time', $ctx['session']),
            ['minutes' => 5],
        )->assertOk();

        $resp = $this->actingAs($ctx['examiner'])->getJson(
            route('examiner.exam-sessions.emergency.audit-trail', $ctx['session']),
        )->assertOk()->json();

        $this->assertSame($ctx['session']->id, (int) $resp['session_id']);
        $this->assertNotEmpty($resp['events']);
        $this->assertSame(
            ExaminerEmergencyAuditService::EVENT_EXTEND_TIME,
            $resp['events'][0]['event_type'],
        );
    }

    public function test_a_random_examiner_cannot_extend_time_on_another_courses_session(): void
    {
        $ctx = $this->bootContext();

        $intruder = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $ctx['examiner']->university_id,
            'email' => 'intruder-'.Str::random(6).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($intruder)->postJson(
            route('examiner.exam-sessions.emergency.extend-time', $ctx['session']),
            ['minutes' => 30],
        )->assertForbidden();
    }
}
