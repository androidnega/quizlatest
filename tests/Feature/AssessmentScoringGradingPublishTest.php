<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use App\Services\AnswerEvaluationService;
use App\Services\ExamLifecycleService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentScoringGradingPublishTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, student: User, courseId: int, classId: int}
     */
    private function seedExaminerStudentCourse(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.score.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-SCORE',
            'title' => 'Scoring test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'ScoreClass',
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

        $student = User::query()->where('role', 'student')->firstOrFail();
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        return ['examiner' => $examiner->fresh(), 'student' => $student->fresh(), 'courseId' => $courseId, 'classId' => $classId];
    }

    private function createDraftExam(User $examiner, int $courseId, array $selectedTypes = ['mcq', 'true_false', 'fill_blank', 'essay']): Quiz
    {
        return Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Scoring exam '.Str::random(6),
            'description' => null,
            'assessment_type' => 'exam',
            'selected_question_types' => $selectedTypes,
            'status' => 'draft',
            'published_at' => null,
            'duration_minutes' => 30,
            'total_marks' => 0,
            'questions_per_student' => 10,
            'proctoring_settings' => [],
            'start_time' => null,
            'end_time' => null,
        ]);
    }

    private function makeSubmittedSession(User $student, int $classId, Quiz $exam): ExamSession
    {
        return ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $exam->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
        ]);
    }

    public function test_mcq_correct_scores_full_marks_wrong_scores_zero(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $q1 = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Pick',
            'type' => 'mcq',
            'options' => ['a', 'b'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 4,
            'question_order' => 1,
            'pool_status' => 'draft',
        ]);
        $q2 = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Pick2',
            'type' => 'mcq',
            'options' => ['a', 'b'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 4,
            'question_order' => 2,
            'pool_status' => 'draft',
        ]);
        $exam->update(['total_marks' => 8]);

        $session = $this->makeSubmittedSession($ctx['student'], $ctx['classId'], $exam->fresh());
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q1->id,
            'answer_payload' => ['type' => 'mcq', 'selected' => [0]],
            'saved_at' => now(),
        ]);
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q2->id,
            'answer_payload' => ['type' => 'mcq', 'selected' => [1]],
            'saved_at' => now(),
        ]);

        $exam->load('questions');
        $out = app(AnswerEvaluationService::class)->evaluateAndPersist($session->fresh(['answers', 'exam.questions']));
        $this->assertSame(4.0, $out['total_score']);
    }

    public function test_true_false_correct_and_wrong(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $q1 = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'T?',
            'type' => 'true_false',
            'options' => null,
            'correct_answer' => true,
            'answer_schema' => null,
            'marks' => 3,
            'question_order' => 1,
            'pool_status' => 'draft',
        ]);
        $q2 = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'F?',
            'type' => 'true_false',
            'options' => null,
            'correct_answer' => false,
            'answer_schema' => null,
            'marks' => 3,
            'question_order' => 2,
            'pool_status' => 'draft',
        ]);

        $session = $this->makeSubmittedSession($ctx['student'], $ctx['classId'], $exam->fresh());
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q1->id,
            'answer_payload' => ['type' => 'true_false', 'value' => true],
            'saved_at' => now(),
        ]);
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q2->id,
            'answer_payload' => ['type' => 'true_false', 'value' => true],
            'saved_at' => now(),
        ]);

        $out = app(AnswerEvaluationService::class)->evaluateAndPersist($session->fresh(['answers', 'exam.questions']));
        $this->assertSame(3.0, $out['total_score']);
    }

    public function test_fill_blank_case_insensitive_full_partial_and_alternatives(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $q1 = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Capital',
            'type' => 'fill_blank',
            'options' => null,
            'correct_answer' => ['Accra'],
            'answer_schema' => ['blank_count' => 1],
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'draft',
        ]);
        $q2 = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Two',
            'type' => 'fill_blank',
            'options' => null,
            'correct_answer' => ['one', 'two'],
            'answer_schema' => ['blank_count' => 2],
            'marks' => 10,
            'question_order' => 2,
            'pool_status' => 'draft',
        ]);
        $q3 = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Alt',
            'type' => 'fill_blank',
            'options' => null,
            'correct_answer' => [['Paris', 'paris france'], ['Berlin']],
            'answer_schema' => ['blank_count' => 2],
            'marks' => 10,
            'question_order' => 3,
            'pool_status' => 'draft',
        ]);

        $session = $this->makeSubmittedSession($ctx['student'], $ctx['classId'], $exam->fresh());
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q1->id,
            'answer_payload' => ['type' => 'fill_blank', 'blanks' => ['  ACCRA  ']],
            'saved_at' => now(),
        ]);
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q2->id,
            'answer_payload' => ['type' => 'fill_blank', 'blanks' => ['ONE', '']],
            'saved_at' => now(),
        ]);
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q3->id,
            'answer_payload' => ['type' => 'fill_blank', 'blanks' => ['Paris', 'Berlin']],
            'saved_at' => now(),
        ]);

        $out = app(AnswerEvaluationService::class)->evaluateAndPersist($session->fresh(['answers', 'exam.questions']));
        $this->assertSame(25.0, $out['total_score']);
    }

    public function test_essay_starts_pending_manual(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $q = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Write',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'draft',
        ]);

        $session = $this->makeSubmittedSession($ctx['student'], $ctx['classId'], $exam->fresh());
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'Long answer text here.'],
            'saved_at' => now(),
        ]);

        $out = app(AnswerEvaluationService::class)->evaluateAndPersist($session->fresh(['answers', 'exam.questions']));
        $this->assertSame(0.0, $out['total_score']);
        $ans = ExamSessionAnswer::query()->where('exam_session_id', $session->id)->firstOrFail();
        $this->assertSame('pending_manual', $ans->evaluation_status);
    }

    public function test_evaluate_preserves_manual_graded_essay(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $q = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Write',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'draft',
        ]);

        $session = $this->makeSubmittedSession($ctx['student'], $ctx['classId'], $exam->fresh());
        $answer = ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'x'],
            'points_awarded' => 7.5,
            'evaluation_status' => 'manual_graded',
            'evaluation_detail' => ['graded' => true, 'grading_history' => []],
            'saved_at' => now(),
        ]);

        app(AnswerEvaluationService::class)->evaluateAndPersist($session->fresh(['answers', 'exam.questions']));
        $answer->refresh();
        $this->assertSame('manual_graded', $answer->evaluation_status);
        $this->assertSame(7.5, (float) $answer->points_awarded);
    }

    public function test_publish_blocked_no_selected_question_types(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], []);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Q',
            'type' => 'mcq',
            'options' => ['a', 'b'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 2,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $exam->update(['total_marks' => 2, 'questions_per_student' => 1]);

        $errors = app(ExamLifecycleService::class)->publishValidationErrors($exam->fresh());
        $this->assertContains('This assessment has no selected question types.', $errors);
    }

    public function test_publish_blocked_approved_type_outside_selection(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], ['essay']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Q',
            'type' => 'mcq',
            'options' => ['a', 'b'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 2,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $exam->update(['total_marks' => 2, 'questions_per_student' => 1]);

        $errors = app(ExamLifecycleService::class)->publishValidationErrors($exam->fresh());
        $this->assertTrue(collect($errors)->contains(fn ($m) => str_contains((string) $m, 'Question type mcq is not enabled')));
    }

    public function test_publish_blocked_no_approved_questions(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);
        $exam->update(['questions_per_student' => 1]);

        $errors = app(ExamLifecycleService::class)->publishValidationErrors($exam->fresh());
        $this->assertContains('Only approved questions can be published.', $errors);
    }

    public function test_publish_blocked_fill_blank_blank_count_mismatch(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], ['fill_blank']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'x',
            'type' => 'fill_blank',
            'options' => null,
            'correct_answer' => ['a', 'b'],
            'answer_schema' => ['blank_count' => 1],
            'marks' => 2,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $exam->update(['total_marks' => 2, 'questions_per_student' => 1]);

        $errors = app(ExamLifecycleService::class)->publishValidationErrors($exam->fresh());
        $this->assertContains('Fill-in-the-Blank blank count does not match accepted answers.', $errors);
    }

    public function test_publish_blocked_invalid_mcq_and_essay_marking_guide(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], ['mcq', 'essay']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'bad mcq',
            'type' => 'mcq',
            'options' => ['only'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 2,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'essay',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 5,
            'question_order' => 2,
            'pool_status' => 'approved',
            'metadata' => null,
        ]);
        $exam->update(['total_marks' => 7, 'questions_per_student' => 2]);

        config(['exam.require_essay_marking_guide_on_publish' => true]);

        $errors = app(ExamLifecycleService::class)->publishValidationErrors($exam->fresh());
        $this->assertContains('Multiple-choice questions must have valid options and a correct answer.', $errors);
        $this->assertContains('Essay marking guide is required by this assessment setting.', $errors);
    }

    public function test_publish_succeeds_for_valid_mixed_approved_questions(): void
    {
        config(['exam.require_essay_marking_guide_on_publish' => false]);

        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], ['mcq', 'true_false', 'fill_blank', 'essay']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'mcq',
            'type' => 'mcq',
            'options' => ['a', 'b'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 2,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'tf',
            'type' => 'true_false',
            'options' => null,
            'correct_answer' => true,
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 2,
            'pool_status' => 'approved',
        ]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'fb',
            'type' => 'fill_blank',
            'options' => null,
            'correct_answer' => ['x'],
            'answer_schema' => ['blank_count' => 1],
            'marks' => 1,
            'question_order' => 3,
            'pool_status' => 'approved',
        ]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'es',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 4,
            'question_order' => 4,
            'pool_status' => 'approved',
            'metadata' => ['marking_guide' => 'Grade on clarity.'],
        ]);
        $exam->update(['total_marks' => 8, 'questions_per_student' => 4]);

        $errors = app(ExamLifecycleService::class)->publishValidationErrors($exam->fresh());
        $this->assertSame([], $errors);
    }

    public function test_essay_manual_grading_updates_result_and_audit_log(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], ['essay']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $essay = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Explain.',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
            'metadata' => ['marking_guide' => 'Use criteria A.'],
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $exam->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
        ]);

        $answer = ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $essay->id,
            'answer_text' => 'Body',
            'answer_payload' => ['type' => 'essay', 'text' => 'Body'],
            'points_awarded' => 0,
            'evaluation_status' => 'pending_manual',
            'saved_at' => now(),
        ]);

        Result::query()->updateOrInsert(
            ['user_id' => $ctx['student']->id, 'quiz_id' => $exam->id],
            [
                'score' => 0,
                'status' => 'pending_manual',
                'time_taken' => 60,
                'exam_status' => 'submitted',
                'review_note' => 'x',
                'submitted_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $before = ActivityLog::query()->count();

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.grading.grade', $answer), [
                'points_awarded' => 8,
                'grader_feedback' => 'Good structure.',
            ])
            ->assertRedirect(route('examiner.grading.pending'));

        $this->assertGreaterThan($before, ActivityLog::query()->count());
        $this->assertTrue(ActivityLog::query()->where('event_type', 'essay_manual_grade')->exists());

        $answer->refresh();
        $this->assertSame('manual_graded', $answer->evaluation_status);
        $this->assertSame(8.0, (float) $answer->points_awarded);

        $result = Result::query()->where('user_id', $ctx['student']->id)->where('quiz_id', $exam->id)->first();
        $this->assertNotNull($result);
        $this->assertSame(8.0, (float) $result->score);
        $this->assertSame('graded', $result->status);
    }

    public function test_essay_override_requires_reason_and_writes_override_audit(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId'], ['essay']);
        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $essay = Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'Explain.',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $exam->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
        ]);

        $answer = ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $essay->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'x'],
            'points_awarded' => 6,
            'evaluation_status' => 'manual_graded',
            'evaluation_detail' => ['graded' => true, 'grading_history' => [
                ['graded_at' => now()->toIso8601String(), 'grader_id' => $ctx['examiner']->id, 'points_awarded' => 6.0, 'action' => 'initial'],
            ]],
            'saved_at' => now(),
        ]);

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.grading.grade', $answer), [
                'points_awarded' => 9,
                'grader_feedback' => 'Adjusted',
            ])
            ->assertSessionHasErrors('override_reason');

        $this->actingAs($ctx['examiner'])
            ->post(route('examiner.grading.grade', $answer->fresh()), [
                'points_awarded' => 9,
                'grader_feedback' => 'Adjusted',
                'override_reason' => 'Rubric alignment after second read.',
            ])
            ->assertRedirect(route('examiner.grading.pending'));

        $this->assertTrue(ActivityLog::query()->where('event_type', 'essay_manual_grade_override')->exists());
        $answer->refresh();
        $this->assertSame(9.0, (float) $answer->points_awarded);
        $hist = $answer->evaluation_detail['grading_history'] ?? [];
        $this->assertGreaterThanOrEqual(2, count($hist));
        $this->assertSame('Rubric alignment after second read.', end($hist)['override_reason'] ?? null);
    }
}
