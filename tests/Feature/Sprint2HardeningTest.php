<?php

namespace Tests\Feature;

use App\Console\Commands\AutoSubmitStalePausedSessionsCommand;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Result;
use App\Models\User;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies the Sprint 2 / Architecture Review Phase 5 hardening:
 *
 *   - exam.max_pause_minutes config is honoured.
 *   - AutoSubmitStalePausedSessionsCommand force-submits a session
 *     whose pause_segment_started_at is older than the cutoff, and
 *     leaves a fresh-paused session alone.
 *   - saveAnswer's compare-and-swap rejects an out-of-order write
 *     (lower revision arriving after a higher one) without losing
 *     the higher revision.
 *   - saveAnswer's CAS still accepts a normal monotonic sequence.
 */
class Sprint2HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);
    }

    /**
     * @return array{student: User, session: ExamSession, questionId: int}
     */
    private function bootActiveExamWithQuestion(): array
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $uniId = (int) $student->university_id;
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'S2-CTX-'.Str::random(4),
            'title' => 'Sprint 2 ctx',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'S2Class-'.Str::random(4),
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
            'email' => 's2-ex.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Sprint 2 exam',
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

        DB::table('quiz_class')->insert([
            'quiz_id' => $quizId,
            'class_id' => $classId,
        ]);

        $sectionId = DB::table('exam_sections')->insertGetId([
            'exam_id' => $quizId,
            'title' => 'Section A',
            'section_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questionId = (int) DB::table('questions')->insertGetId([
            'quiz_id' => $quizId,
            'section_id' => $sectionId,
            'type' => 'essay',
            'question_text' => 'Trivial typed answer.',
            'marks' => 5,
            'question_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
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

        return ['student' => $student->fresh(), 'session' => $session, 'questionId' => $questionId];
    }

    public function test_max_pause_minutes_config_is_present_with_default_10(): void
    {
        $this->assertSame(10, (int) config('exam.max_pause_minutes'));
    }

    public function test_auto_submit_command_force_submits_a_long_paused_session(): void
    {
        ['session' => $session] = $this->bootActiveExamWithQuestion();

        $session->forceFill([
            'status' => 'paused',
            'pause_segment_started_at' => now()->subMinutes(15),
        ])->save();

        config(['exam.max_pause_minutes' => 10]);

        Artisan::call(AutoSubmitStalePausedSessionsCommand::class);

        $row = DB::table('exam_sessions')->where('id', $session->id)->first();
        $this->assertSame('submitted', $row->status);
        $this->assertSame('submitted_held', $row->exam_status);
        $this->assertSame('stale_paused', $row->auto_submit_reason_code);

        $resultCount = Result::query()
            ->where('user_id', $session->student_id)
            ->where('quiz_id', $session->exam_id)
            ->count();
        $this->assertSame(1, $resultCount, 'Auto-submit must finalise a Result row');
    }

    public function test_auto_submit_command_leaves_a_freshly_paused_session_alone(): void
    {
        ['session' => $session] = $this->bootActiveExamWithQuestion();

        $session->forceFill([
            'status' => 'paused',
            'pause_segment_started_at' => now()->subMinutes(2),
        ])->save();

        config(['exam.max_pause_minutes' => 10]);

        Artisan::call(AutoSubmitStalePausedSessionsCommand::class);

        $row = DB::table('exam_sessions')->where('id', $session->id)->first();
        $this->assertSame('paused', $row->status, 'Fresh-paused sessions must not be touched');
    }

    public function test_auto_submit_command_is_disabled_when_config_is_zero(): void
    {
        ['session' => $session] = $this->bootActiveExamWithQuestion();

        $session->forceFill([
            'status' => 'paused',
            'pause_segment_started_at' => now()->subHours(2),
        ])->save();

        config(['exam.max_pause_minutes' => 0]);

        Artisan::call(AutoSubmitStalePausedSessionsCommand::class);

        $row = DB::table('exam_sessions')->where('id', $session->id)->first();
        $this->assertSame('paused', $row->status, 'max_pause_minutes=0 must disable the auto-submit');
    }

    public function test_save_answer_accepts_monotonic_revisions(): void
    {
        ['student' => $student, 'session' => $session, 'questionId' => $questionId] = $this->bootActiveExamWithQuestion();

        $route = route('exam-sessions.answers.save', $session);

        $r1 = $this->actingAs($student)->postJson($route, [
            'question_id' => $questionId,
            'answer_payload' => ['type' => 'essay', 'text' => 'first'],
            'client_revision' => 1,
        ]);
        $r1->assertOk();
        $r1->assertJson(['status' => 'saved', 'client_revision' => 1]);

        $r2 = $this->actingAs($student)->postJson($route, [
            'question_id' => $questionId,
            'answer_payload' => ['type' => 'essay', 'text' => 'second'],
            'client_revision' => 2,
        ]);
        $r2->assertOk();
        $r2->assertJson(['status' => 'saved', 'client_revision' => 2]);

        $row = DB::table('exam_session_answers')
            ->where('exam_session_id', $session->id)
            ->where('question_id', $questionId)
            ->first();
        $this->assertSame(2, (int) $row->client_revision);
        $this->assertStringContainsString('second', (string) $row->answer_payload);
    }

    public function test_save_answer_rejects_a_lower_revision_arriving_late(): void
    {
        ['student' => $student, 'session' => $session, 'questionId' => $questionId] = $this->bootActiveExamWithQuestion();

        $route = route('exam-sessions.answers.save', $session);

        $this->actingAs($student)->postJson($route, [
            'question_id' => $questionId,
            'answer_payload' => ['type' => 'essay', 'text' => 'rev-7'],
            'client_revision' => 7,
        ])->assertOk()->assertJson(['status' => 'saved', 'client_revision' => 7]);

        // A delayed retry from an old tab arrives with revision 3.
        $late = $this->actingAs($student)->postJson($route, [
            'question_id' => $questionId,
            'answer_payload' => ['type' => 'essay', 'text' => 'rev-3-late'],
            'client_revision' => 3,
        ]);
        $late->assertOk();
        $late->assertJson([
            'status' => 'noop',
            'reason' => 'stale_revision',
            'client_revision' => 7,
        ]);

        $row = DB::table('exam_session_answers')
            ->where('exam_session_id', $session->id)
            ->where('question_id', $questionId)
            ->first();
        $this->assertSame(7, (int) $row->client_revision, 'Stored revision must NOT regress');
        $this->assertStringContainsString('rev-7', (string) $row->answer_payload, 'Stored payload must NOT regress');
        $this->assertStringNotContainsString('rev-3-late', (string) $row->answer_payload);
    }

    public function test_save_answer_returns_authoritative_revision_when_concurrent_writer_won(): void
    {
        ['student' => $student, 'session' => $session, 'questionId' => $questionId] = $this->bootActiveExamWithQuestion();

        // Simulate a concurrent winner that already wrote revision 9
        // between our SELECT-existing (none) and the controller's UPDATE.
        $now = now();
        DB::table('exam_session_answers')->insert([
            'exam_session_id' => $session->id,
            'question_id' => $questionId,
            'answer_text' => null,
            'answer_payload' => json_encode(['text' => 'winner-9']),
            'saved_at' => $now,
            'client_revision' => 9,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $route = route('exam-sessions.answers.save', $session);

        // Our request would have written revision 4 if the row hadn't
        // already advanced. The CAS must detect the conflict, refuse
        // to overwrite, and tell us the authoritative revision.
        $resp = $this->actingAs($student)->postJson($route, [
            'question_id' => $questionId,
            'answer_payload' => ['type' => 'essay', 'text' => 'late-4'],
            'client_revision' => 4,
        ]);
        $resp->assertOk();
        $resp->assertJson([
            'status' => 'noop',
            'reason' => 'stale_revision',
            'client_revision' => 9,
        ]);

        $row = DB::table('exam_session_answers')
            ->where('exam_session_id', $session->id)
            ->where('question_id', $questionId)
            ->first();
        $this->assertSame(9, (int) $row->client_revision);
        $this->assertStringContainsString('winner-9', (string) $row->answer_payload);
    }
}
