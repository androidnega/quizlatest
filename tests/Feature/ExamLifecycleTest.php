<?php

namespace Tests\Feature;

use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, student: User, courseId: int, classId: int}
     */
    private function seedExaminerAndStudentContext(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.lifecycle.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-LIFE',
            'title' => 'Lifecycle Test Course',
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
            'name' => 'Lifecycle',
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

        $student = User::query()->where('role', 'student')->firstOrFail();
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        return ['examiner' => $examiner->fresh(), 'student' => $student->fresh(), 'courseId' => $courseId, 'classId' => $classId];
    }

    private function createDraftExam(User $examiner, int $courseId, float $totalMarks = 0): Quiz
    {
        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $examiner->university_id,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Lifecycle exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'draft',
            'published_at' => null,
            'duration_minutes' => 30,
            'total_marks' => $totalMarks,
            'proctoring_settings' => json_encode(new \stdClass),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Quiz::query()->findOrFail($quizId);
    }

    private function addSectionAndMcq(Quiz $exam): void
    {
        $section = ExamSection::query()->create([
            'exam_id' => $exam->id,
            'title' => 'A',
            'section_order' => 1,
        ]);

        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Pick A',
            'type' => 'mcq',
            'options' => ['x', 'y'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 5,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $exam->update(['total_marks' => 5]);
    }

    private function setDeliveryForExaminer(User $examiner, Quiz $exam, int $questionsPerStudent = 1): void
    {
        $this->actingAs($examiner);
        $this->patch(route('examiner.exams.delivery.update', $exam), [
            'questions_per_student' => $questionsPerStudent,
        ])->assertRedirect();
    }

    public function test_publish_rejects_when_questions_per_student_exceeds_approved_pool(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], 0);
        $this->addSectionAndMcq($exam);

        $this->actingAs($ctx['examiner']);
        $this->patch(route('examiner.exams.delivery.update', $exam->fresh()), [
            'questions_per_student' => 5,
            'randomize_questions' => false,
            'randomize_options' => false,
        ])->assertRedirect();

        $this->post(route('examiner.exams.publish', $exam->fresh()))
            ->assertSessionHasErrors('lifecycle');
    }

    public function test_publish_requires_sections_questions_and_positive_marks(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], 0);

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam))
            ->assertSessionHasErrors('lifecycle');

        $this->addSectionAndMcq($exam->fresh());

        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());

        $this->post(route('examiner.exams.publish', $exam->fresh()))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $exam->refresh();
        $this->assertSame('published', $exam->status);
        $this->assertNotNull($exam->published_at);
    }

    public function test_student_cannot_prepare_draft_or_archived_only_published_in_window(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);

        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.instructions', $exam))->assertForbidden();
        $this->get(route('student.exam.prepare', $exam))->assertForbidden();

        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.instructions', $exam))->assertOk();
        $this->get(route('student.exam.prepare', $exam))->assertOk();

        $this->actingAs($ctx['examiner']);
        $exam->update([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDays(2),
        ]);

        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.instructions', $exam->fresh()))->assertForbidden();
        $this->get(route('student.exam.prepare', $exam->fresh()))->assertForbidden();

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.unpublish', $exam->fresh()))->assertRedirect();
        $exam->refresh();
        $exam->update(['start_time' => null, 'end_time' => null]);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.archive', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $this->assertSame('archived', $exam->status);

        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.instructions', $exam))->assertForbidden();
        $this->get(route('student.exam.prepare', $exam))->assertForbidden();
    }

    public function test_published_exam_blocks_content_mutations_and_clone_creates_draft(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);

        $section = $exam->fresh()->sections()->firstOrFail();

        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());

        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $this->post(route('examiner.exams.sections.store', $exam), ['title' => 'B'])
            ->assertForbidden();

        $this->post(route('examiner.exams.questions.store', [$exam, $section]), [
            'type' => 'true_false',
            'question_text' => 'T?',
            'marks' => 1,
            'correct_true_false' => '1',
        ])->assertForbidden();

        $this->post(route('examiner.exams.clone', $exam))->assertRedirect();
        $copy = Quiz::query()->where('title', 'like', '%(copy)%')->orderByDesc('id')->first();
        $this->assertNotNull($copy);
        $this->assertSame('draft', $copy->status);
        $this->assertSame(1, $copy->sections()->count());
        $this->assertSame(1, $copy->questions()->count());
    }

    public function test_draft_can_update_schedule(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        $start = now()->addHour()->startOfMinute();
        $end = now()->addHours(3)->startOfMinute();

        $this->actingAs($ctx['examiner']);
        $this->patch(route('examiner.exams.schedule.update', $exam), [
            'start_time' => $start->format('Y-m-d\TH:i'),
            'end_time' => $end->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $exam->refresh();
        $this->assertNotNull($exam->start_time);
        $this->assertNotNull($exam->end_time);
        $this->assertTrue($exam->end_time->greaterThan($exam->start_time));
        $this->assertSame($start->format('Y-m-d H:i'), $exam->start_time->format('Y-m-d H:i'));
        $this->assertSame($end->format('Y-m-d H:i'), $exam->end_time->format('Y-m-d H:i'));
    }

    public function test_non_onboarded_student_is_redirected_from_exam_prepare(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        DB::table('users')->where('id', $ctx['student']->id)->update(['student_onboarded_at' => null]);

        $response = $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->get(route('student.exam.instructions', $exam));

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors('index_number');

        $responsePrepare = $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->get(route('student.exam.prepare', $exam));

        $responsePrepare->assertRedirect(route('login', absolute: false));
        $responsePrepare->assertSessionHasErrors('index_number');
    }

    public function test_inactive_student_is_redirected_from_exam_prepare(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        DB::table('users')->where('id', $ctx['student']->id)->update(['is_active' => false]);

        $response = $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->get(route('student.exam.instructions', $exam));

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHasErrors('index_number');

        $responsePrepare = $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->get(route('student.exam.prepare', $exam));

        $responsePrepare->assertRedirect(route('login', absolute: false));
        $responsePrepare->assertSessionHasErrors('index_number');
    }

    public function test_onboarded_active_student_can_prepare_published_exam(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.instructions', $exam))->assertOk();
        $this->get(route('student.exam.prepare', $exam))->assertOk();
    }

    public function test_exam_session_start_returns_422_for_non_onboarded_student(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        DB::table('users')->where('id', $ctx['student']->id)->update(['student_onboarded_at' => null]);

        $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->postJson(route('exam-sessions.start'), ['exam_id' => $exam->id])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('Please complete your student onboarding before starting an exam.'),
            ]);
    }

    public function test_exam_session_start_returns_422_for_inactive_student(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        DB::table('users')->where('id', $ctx['student']->id)->update(['is_active' => false]);

        $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->postJson(route('exam-sessions.start'), ['exam_id' => $exam->id])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('Your student account is not active. Please contact your coordinator.'),
            ]);
    }

    public function test_exam_session_verify_otp_returns_422_for_non_onboarded_student(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        DB::table('users')->where('id', $ctx['student']->id)->update(['student_onboarded_at' => null]);

        $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->postJson(route('exam-sessions.verify-otp'), [
                'exam_id' => 1,
                'otp_code' => '123456',
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => __('Please complete your student onboarding before starting an exam.'),
            ]);
    }

    public function test_exam_session_start_onboarded_active_student_passes_onboarding_gate(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $response = $this->actingAs($ctx['student'])
            ->postJson(route('exam-sessions.start'), ['exam_id' => $exam->id]);

        $onboardingMessage = __('Please complete your student onboarding before starting an exam.');
        $inactiveMessage = __('Your student account is not active. Please contact your coordinator.');

        $this->assertNotSame(
            $onboardingMessage,
            $response->json('message'),
            'Onboarded student should not be rejected for onboarding at API start.',
        );
        $this->assertNotSame(
            $inactiveMessage,
            $response->json('message'),
            'Active student should not be rejected for inactivity at API start.',
        );
    }

    public function test_student_redirected_to_results_when_exam_already_submitted(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $exam->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('student.exam.instructions', $exam))
            ->assertRedirect(route('student.results.show', $session, absolute: false))
            ->assertSessionHas('status');

        $this->actingAs($ctx['student'])
            ->get(route('student.exam.prepare', $exam))
            ->assertRedirect(route('student.results.show', $session, absolute: false));
    }

    public function test_exam_start_does_not_require_stored_face_template(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);
        $this->setDeliveryForExaminer($ctx['examiner'], $exam->fresh());
        $this->actingAs($ctx['examiner']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        DB::table('users')->where('id', $ctx['student']->id)->update([
            'face_embedding' => null,
            'face_image_path' => null,
        ]);
        app(SystemSettingsService::class)->set('enable_otp', '0', $ctx['examiner']);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2YfV0AAAAASUVORK5CYII=',
            true,
        );
        $tmp = tempnam(sys_get_temp_dir(), 'qsnap_');
        file_put_contents($tmp, $png ?: '');
        $upload = new UploadedFile($tmp, 'verification.png', 'image/png', null, true);

        $response = $this->actingAs(User::query()->findOrFail($ctx['student']->id))
            ->post(route('exam-sessions.start'), [
                'exam_id' => $exam->id,
                'verification_snapshot' => $upload,
            ]);

        $response->assertOk()->assertJsonStructure(['session_id']);

        $session = ExamSession::query()->where('session_id', $response->json('session_id'))->firstOrFail();
        $this->assertNotNull($session->verification_image_path);
    }
}
