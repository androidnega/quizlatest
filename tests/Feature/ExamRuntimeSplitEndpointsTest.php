<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\User;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Architecture Review Phase 1 + 4 — split-endpoint contract.
 *
 *   /state           → ONLY volatile fields. NO sections, NO saved_answers.
 *   /exam-structure  → invariant exam tree. ETag + 304 supported.
 *   /answers         → revision-aware answers map. ETag + 304 supported.
 */
class ExamRuntimeSplitEndpointsTest extends TestCase
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
            'code' => 'SPLIT-'.Str::random(4),
            'title' => 'Split endpoints ctx',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'SplitClass-'.Str::random(4),
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
            'email' => 'split-ex.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Split endpoints exam',
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
            'type' => 'mcq',
            'question_text' => 'Pick one option.',
            'options' => json_encode(['Alpha', 'Beta', 'Gamma', 'Delta']),
            'correct_answer' => json_encode([0]),
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

        DB::table('exam_session_questions')->insert([
            'exam_session_id' => $session->id,
            'question_id' => $questionId,
            'display_order' => 1,
            'option_order' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['student' => $student->fresh(), 'session' => $session, 'questionId' => $questionId];
    }

    public function test_state_endpoint_omits_heavy_sections_and_saved_answers(): void
    {
        ['student' => $student, 'session' => $session] = $this->bootActiveExamWithQuestion();

        $payload = $this->actingAs($student)
            ->getJson(route('exam-sessions.state', $session))
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('sections', $payload, '/state must not ship the heavy sections array');
        $this->assertArrayNotHasKey('saved_answers', $payload, '/state must not ship the saved_answers map');

        // Volatile fields must remain.
        $this->assertArrayHasKey('time_remaining_seconds', $payload);
        $this->assertArrayHasKey('server_time', $payload);
        $this->assertArrayHasKey('proctoring_overlay', $payload);
        $this->assertArrayHasKey('exam_ui_state', $payload);
    }

    public function test_exam_structure_endpoint_returns_sections_and_questions_with_etag(): void
    {
        ['student' => $student, 'session' => $session] = $this->bootActiveExamWithQuestion();

        $response = $this->actingAs($student)->getJson(
            route('exam-sessions.exam-structure', $session),
        );
        $response->assertOk();
        $response->assertHeader('ETag');

        $data = $response->json();
        $this->assertArrayHasKey('exam', $data);
        $this->assertArrayHasKey('sections', $data);
        $this->assertNotEmpty($data['sections']);
        $this->assertSame(
            'Pick one option.',
            data_get($data, 'sections.0.questions.0.question_text'),
        );
    }

    public function test_exam_structure_etag_revalidation_returns_304(): void
    {
        ['student' => $student, 'session' => $session] = $this->bootActiveExamWithQuestion();

        $first = $this->actingAs($student)->getJson(
            route('exam-sessions.exam-structure', $session),
        )->assertOk();
        $etag = (string) $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $revalidate = $this->actingAs($student)->getJson(
            route('exam-sessions.exam-structure', $session),
            ['If-None-Match' => $etag],
        );
        $revalidate->assertStatus(304);
        $this->assertSame($etag, $revalidate->headers->get('ETag'));
        $this->assertSame('', (string) $revalidate->getContent());
    }

    public function test_answers_endpoint_returns_map_with_etag(): void
    {
        ['student' => $student, 'session' => $session, 'questionId' => $questionId] = $this->bootActiveExamWithQuestion();

        DB::table('exam_session_answers')->insert([
            'exam_session_id' => $session->id,
            'question_id' => $questionId,
            'answer_text' => null,
            'answer_payload' => json_encode(['type' => 'mcq', 'selected' => [1]]),
            'saved_at' => now(),
            'client_revision' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student)->getJson(
            route('exam-sessions.answers', $session),
        );
        $response->assertOk();
        $response->assertHeader('ETag');

        $data = $response->json();
        $this->assertSame(3, (int) $data['revision']);
        $this->assertSame(1, (int) $data['answer_count']);
        $this->assertArrayHasKey((string) $questionId, $data['saved_answers']);
    }

    public function test_answers_etag_revalidation_returns_304_when_unchanged(): void
    {
        ['student' => $student, 'session' => $session, 'questionId' => $questionId] = $this->bootActiveExamWithQuestion();

        DB::table('exam_session_answers')->insert([
            'exam_session_id' => $session->id,
            'question_id' => $questionId,
            'answer_text' => null,
            'answer_payload' => json_encode(['type' => 'mcq', 'selected' => [1]]),
            'saved_at' => now(),
            'client_revision' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = $this->actingAs($student)->getJson(
            route('exam-sessions.answers', $session),
        )->assertOk();
        $etag = (string) $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $second = $this->actingAs($student)->getJson(
            route('exam-sessions.answers', $session),
            ['If-None-Match' => $etag],
        );
        $second->assertStatus(304);
    }

    public function test_answers_etag_changes_after_a_save(): void
    {
        ['student' => $student, 'session' => $session, 'questionId' => $questionId] = $this->bootActiveExamWithQuestion();

        $initial = $this->actingAs($student)->getJson(
            route('exam-sessions.answers', $session),
        )->assertOk();
        $etag1 = (string) $initial->headers->get('ETag');

        $this->actingAs($student)->postJson(
            route('exam-sessions.answers.save', $session),
            [
                'question_id' => $questionId,
                'answer_payload' => ['type' => 'mcq', 'selected' => [2]],
                'client_revision' => 1,
            ],
        )->assertOk();

        $afterSave = $this->actingAs($student)->getJson(
            route('exam-sessions.answers', $session),
        )->assertOk();
        $etag2 = (string) $afterSave->headers->get('ETag');

        $this->assertNotSame(
            $etag1,
            $etag2,
            'Answers ETag must advance after a saveAnswer write',
        );

        // The 304 path that the OLD etag would take must NOT short-circuit.
        $stale = $this->actingAs($student)->getJson(
            route('exam-sessions.answers', $session),
            ['If-None-Match' => $etag1],
        );
        $stale->assertOk();
    }

    public function test_other_students_cannot_read_split_endpoints_for_someone_elses_session(): void
    {
        ['session' => $session] = $this->bootActiveExamWithQuestion();

        $intruder = User::factory()->create([
            'role' => 'student',
            'university_id' => $session->classroom->university_id ?? 1,
            'email' => null,
            'index_number' => 'INT'.Str::upper(Str::random(6)),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->getJson(route('exam-sessions.exam-structure', $session))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->getJson(route('exam-sessions.answers', $session))
            ->assertForbidden();
    }
}
