<?php

namespace Tests\Feature;

use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{coord: User, student: User, courseId: int, classId: int}
     */
    private function seedExaminerAndStudentContext(): array
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
            'code' => 'CS-LIFE',
            'title' => 'Lifecycle Test Course',
            'credit_hours' => 3,
            'is_active' => true,
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

        return ['coord' => $coord, 'student' => $student->fresh(), 'courseId' => $courseId, 'classId' => $classId];
    }

    private function createDraftExam(User $coord, int $courseId, float $totalMarks = 0): Quiz
    {
        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $coord->university_id,
            'course_id' => $courseId,
            'created_by' => $coord->id,
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

    private function setDeliveryForCoordinator(User $coord, Quiz $exam, int $questionsPerStudent = 1): void
    {
        $this->actingAs($coord);
        $this->patch(route('examiner.exams.delivery.update', $exam), [
            'questions_per_student' => $questionsPerStudent,
        ])->assertRedirect();
    }

    public function test_publish_rejects_when_questions_per_student_exceeds_approved_pool(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['coord'], $ctx['courseId'], 0);
        $this->addSectionAndMcq($exam);

        $this->actingAs($ctx['coord']);
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
        $exam = $this->createDraftExam($ctx['coord'], $ctx['courseId'], 0);

        $this->actingAs($ctx['coord']);
        $this->post(route('examiner.exams.publish', $exam))
            ->assertSessionHasErrors('lifecycle');

        $this->addSectionAndMcq($exam->fresh());

        $this->setDeliveryForCoordinator($ctx['coord'], $exam->fresh());

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
        $exam = $this->createDraftExam($ctx['coord'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);

        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.prepare', $exam))->assertForbidden();

        $this->setDeliveryForCoordinator($ctx['coord'], $exam->fresh());

        $this->actingAs($ctx['coord']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.prepare', $exam))->assertOk();

        $this->actingAs($ctx['coord']);
        $exam->update([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDays(2),
        ]);

        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.prepare', $exam->fresh()))->assertForbidden();

        $this->actingAs($ctx['coord']);
        $this->post(route('examiner.exams.unpublish', $exam->fresh()))->assertRedirect();
        $exam->refresh();
        $exam->update(['start_time' => null, 'end_time' => null]);
        $this->setDeliveryForCoordinator($ctx['coord'], $exam->fresh());
        $this->actingAs($ctx['coord']);
        $this->post(route('examiner.exams.publish', $exam->fresh()))->assertRedirect();

        $this->actingAs($ctx['coord']);
        $this->post(route('examiner.exams.archive', $exam->fresh()))->assertRedirect();

        $exam->refresh();
        $this->assertSame('archived', $exam->status);

        $this->actingAs($ctx['student']);
        $this->get(route('student.exam.prepare', $exam))->assertForbidden();
    }

    public function test_published_exam_blocks_content_mutations_and_clone_creates_draft(): void
    {
        $ctx = $this->seedExaminerAndStudentContext();
        $exam = $this->createDraftExam($ctx['coord'], $ctx['courseId']);
        $this->addSectionAndMcq($exam);

        $section = $exam->fresh()->sections()->firstOrFail();

        $this->setDeliveryForCoordinator($ctx['coord'], $exam->fresh());

        $this->actingAs($ctx['coord']);
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
        $exam = $this->createDraftExam($ctx['coord'], $ctx['courseId']);

        $start = now()->addHour()->startOfMinute();
        $end = now()->addHours(3)->startOfMinute();

        $this->actingAs($ctx['coord']);
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
}
