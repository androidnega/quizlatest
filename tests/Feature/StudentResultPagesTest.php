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

class StudentResultPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $sessionOverrides
     * @param  array<string, mixed>  $resultOverrides
     * @return array{student: User, exam: Quiz, session: ExamSession, coord: User, quizId: int}
     */
    private function seedSubmittedExam(array $sessionOverrides = [], array $resultOverrides = []): array
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

        $sessionDefaults = [
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
        ];

        $session = ExamSession::query()->create(array_merge($sessionDefaults, $sessionOverrides));

        $resultDefaults = [
            'user_id' => $student->id,
            'quiz_id' => $quizId,
            'score' => 72.5,
            'time_taken' => 120,
            'status' => 'graded',
            'exam_status' => 'submitted',
            'submitted_at' => now(),
        ];

        Result::query()->create(array_merge($resultDefaults, $resultOverrides));

        $exam = Quiz::query()->findOrFail($quizId);

        return [
            'student' => $student,
            'exam' => $exam,
            'session' => $session,
            'coord' => $coord,
            'quizId' => $quizId,
        ];
    }

    public function test_coordinator_is_blocked_from_student_results_routes(): void
    {
        $ctx = $this->seedSubmittedExam([
            'exam_status' => 'submitted_held',
            'risk_state' => 'locked',
        ], [
            'status' => 'held',
            'exam_status' => 'submitted_held',
        ]);

        $this->actingAs($ctx['coord']);
        $this->get(route('student.results.index'))->assertForbidden();
        $this->get(route('student.results.show', $ctx['session']))->assertForbidden();
        $this->get(route('student.results.pdf', $ctx['session']))->assertForbidden();
    }

    public function test_student_sees_results_index(): void
    {
        $ctx = $this->seedSubmittedExam();

        $this->actingAs($ctx['student']);
        $this->get(route('student.results.index'))
            ->assertOk()
            ->assertSeeText('Midterm');
    }

    public function test_student_held_detail_shows_review_only(): void
    {
        $ctx = $this->seedSubmittedExam([
            'exam_status' => 'submitted_held',
            'risk_state' => 'locked',
            'violation_score' => 40,
        ], [
            'status' => 'held',
            'exam_status' => 'submitted_held',
            'score' => 91,
        ]);

        $this->actingAs($ctx['student']);
        $html = $this->get(route('student.results.show', $ctx['session']))->assertOk()->getContent();
        $this->assertStringContainsString('Your result is under review. Contact your examiner.', $html);
        $this->assertStringNotContainsString('Score</dt>', $html);
        $this->assertStringNotContainsString('Percentage</dt>', $html);
        $this->assertStringNotContainsString('Download PDF', $html);
    }

    public function test_student_pending_manual_detail_shows_pending_only(): void
    {
        $ctx = $this->seedSubmittedExam([], [
            'status' => 'pending_manual',
            'score' => 40,
        ]);

        $this->actingAs($ctx['student']);
        $html = $this->get(route('student.results.show', $ctx['session']))->assertOk()->getContent();
        $this->assertStringContainsString('Your result is pending manual grading.', $html);
        $this->assertStringNotContainsString('Score</dt>', $html);
        $this->assertStringNotContainsString('Download PDF', $html);
    }

    public function test_student_graded_shows_score_breakdown_and_pdf_link(): void
    {
        $ctx = $this->seedSubmittedExam();

        $sectionId = DB::table('exam_sections')->insertGetId([
            'exam_id' => $ctx['quizId'],
            'title' => 'Section A',
            'section_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questionId = DB::table('questions')->insertGetId([
            'quiz_id' => $ctx['quizId'],
            'section_id' => $sectionId,
            'question_text' => 'Pick one',
            'type' => 'mcq',
            'options' => json_encode(['Alpha', 'Beta']),
            'correct_answer' => json_encode([0]),
            'answer_schema' => null,
            'marks' => 100,
            'question_order' => 1,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('exam_session_answers')->insert([
            'exam_session_id' => $ctx['session']->id,
            'question_id' => $questionId,
            'answer_text' => null,
            'answer_payload' => json_encode(['choice' => 0]),
            'points_awarded' => 88,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => json_encode(['correct' => true]),
            'grader_feedback' => 'Nice work.',
            'saved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'client_revision' => 1,
        ]);

        $this->actingAs($ctx['student']);
        $this->get(route('student.results.show', $ctx['session']))
            ->assertOk()
            ->assertSeeText('72.5')
            ->assertSeeText('Question breakdown')
            ->assertSeeText('Nice work.')
            ->assertSeeText('Download PDF')
            ->assertDontSeeText('evaluation_detail');
    }

    public function test_student_graded_pdf_returns_pdf(): void
    {
        $ctx = $this->seedSubmittedExam();

        $sectionId = DB::table('exam_sections')->insertGetId([
            'exam_id' => $ctx['quizId'],
            'title' => 'Section A',
            'section_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questionId = DB::table('questions')->insertGetId([
            'quiz_id' => $ctx['quizId'],
            'section_id' => $sectionId,
            'question_text' => 'Pick one',
            'type' => 'mcq',
            'options' => json_encode(['Alpha', 'Beta']),
            'correct_answer' => json_encode([0]),
            'answer_schema' => null,
            'marks' => 100,
            'question_order' => 1,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('exam_session_answers')->insert([
            'exam_session_id' => $ctx['session']->id,
            'question_id' => $questionId,
            'answer_text' => null,
            'answer_payload' => json_encode(['choice' => 0]),
            'points_awarded' => 50,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => json_encode(['correct' => true]),
            'grader_feedback' => null,
            'saved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'client_revision' => 1,
        ]);

        $this->actingAs($ctx['student']);
        $response = $this->get(route('student.results.pdf', $ctx['session']));
        $response->assertOk();
        $this->assertStringContainsString('%PDF', $response->getContent() ?: '');
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    public function test_held_pdf_forbidden(): void
    {
        $ctx = $this->seedSubmittedExam([
            'exam_status' => 'submitted_held',
            'risk_state' => 'locked',
        ], [
            'status' => 'held',
            'exam_status' => 'submitted_held',
        ]);

        $this->actingAs($ctx['student']);
        $this->get(route('student.results.pdf', $ctx['session']))->assertForbidden();
    }

    public function test_pending_manual_pdf_forbidden(): void
    {
        $ctx = $this->seedSubmittedExam([], [
            'status' => 'pending_manual',
        ]);

        $this->actingAs($ctx['student']);
        $this->get(route('student.results.pdf', $ctx['session']))->assertForbidden();
    }

    public function test_student_cannot_open_peer_result(): void
    {
        $ctx = $this->seedSubmittedExam();

        $peer = User::query()->where('role', 'student')->where('id', '!=', $ctx['student']->id)->firstOrFail();

        $this->actingAs($peer);
        $this->get(route('student.results.show', $ctx['session']))->assertForbidden();
    }

    public function test_correct_column_hidden_when_exam_setting_disabled(): void
    {
        $ctx = $this->seedSubmittedExam();

        DB::table('quizzes')->where('id', $ctx['quizId'])->update([
            'proctoring_settings' => json_encode(['show_correct_answers_to_students' => false]),
        ]);

        $ctx['session']->unsetRelation('exam');

        $sectionId = DB::table('exam_sections')->insertGetId([
            'exam_id' => $ctx['quizId'],
            'title' => 'Section A',
            'section_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questionId = DB::table('questions')->insertGetId([
            'quiz_id' => $ctx['quizId'],
            'section_id' => $sectionId,
            'question_text' => 'Pick one',
            'type' => 'mcq',
            'options' => json_encode(['Alpha', 'Beta']),
            'correct_answer' => json_encode([0]),
            'answer_schema' => null,
            'marks' => 100,
            'question_order' => 1,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('exam_session_answers')->insert([
            'exam_session_id' => $ctx['session']->id,
            'question_id' => $questionId,
            'answer_text' => null,
            'answer_payload' => json_encode(['choice' => 0]),
            'points_awarded' => 10,
            'evaluation_status' => 'auto_scored',
            'evaluation_detail' => json_encode(['correct' => true]),
            'grader_feedback' => null,
            'saved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'client_revision' => 1,
        ]);

        $this->actingAs($ctx['student']);
        $html = $this->get(route('student.results.show', $ctx['session']))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Correct<', $html);
    }
}
