<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use App\Services\ExamLifecycleService;
use App\Services\ProctoringOrchestratorService;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssignmentCourseworkFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, student: User, courseId: int, classId: int}
     */
    protected function seedExaminerStudentCourse(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.assign.'.Str::random(8).'@test.edu',
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
            'code' => 'CS-ASSIGN',
            'title' => 'Assignment test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'AssignClass',
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

    private function makeReadyAssignment(User $examiner, int $courseId, int $classId, ?\DateTimeInterface $dueAt = null): Quiz
    {
        $dueAt ??= now()->addWeek();
        $settings = AssessmentProctoringDefaults::baselineForType('assignment', true, true, true);
        $settings['show_correct_answers_to_students'] = false;

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Unit 1 written assignment',
            'description' => 'Answer the prompt in full sentences. Minimum length enforced for publish tests.',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'draft',
            'published_at' => null,
            'duration_minutes' => 120,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => $settings,
            'start_time' => null,
            'end_time' => null,
            'due_at' => $dueAt,
            'grades_released_at' => null,
        ]);

        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $classId,
        ]);

        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'Main', 'section_order' => 1]);
        Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Discuss the topic.',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        return $quiz->fresh();
    }

    public function test_assignment_publish_requires_due_date_class_and_instructions(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $lifecycle = app(ExamLifecycleService::class);

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Incomplete assignment',
            'description' => str_repeat('a', 40),
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'draft',
            'duration_minutes' => 120,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'due_at' => null,
        ]);

        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'Main', 'section_order' => 1]);
        Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Write.',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        try {
            $lifecycle->publish($quiz->fresh());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $msgs = $e->errors()['lifecycle'] ?? [];
            $blob = strtolower(implode(' ', $msgs));
            $this->assertTrue(
                str_contains($blob, 'due') || str_contains($blob, 'class'),
                'Expected due date or class validation in: '.$blob,
            );
        }
    }

    public function test_assignment_publishes_with_coursework_safe_proctoring(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $quiz = $this->makeReadyAssignment($ctx['examiner'], $ctx['courseId'], $ctx['classId']);

        app(ExamLifecycleService::class)->publish($quiz->fresh());

        $quiz->refresh();
        $this->assertSame('published', $quiz->status);
        $normalized = ProctoringOrchestratorService::normalizeProctoringSettings($quiz->proctoring_settings, $quiz->id);
        $this->assertFalse($normalized['phone_detection_enabled']);
        $this->assertFalse($normalized['auto_submit_enabled']);
    }

    public function test_assignment_submit_marks_late_after_due_and_writes_audit_log(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $student = $ctx['student'];
        $quiz = $this->makeReadyAssignment($ctx['examiner'], $ctx['courseId'], $ctx['classId'], now()->subHour());
        $quiz->update(['status' => 'published', 'published_at' => now()]);

        $q = $quiz->questions()->firstOrFail();
        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $quiz->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now()->subMinutes(5),
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'in_progress',
            'submitted_late' => false,
        ]);
        ExamSessionQuestion::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'display_order' => 1,
            'option_order' => null,
        ]);
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'My typed response for the assignment.'],
            'saved_at' => now(),
        ]);

        $this->actingAs($student);
        $this->postJson(route('exam-sessions.submit', $session))->assertOk();

        $session->refresh();
        $this->assertSame('submitted', $session->status);
        $this->assertTrue($session->submitted_late);
        $this->assertTrue(ActivityLog::query()->where('event_type', 'assignment_submitted')->exists());
    }

    public function test_assignment_manual_grade_uses_assignment_audit_event(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);

        $q = $quiz->questions()->firstOrFail();
        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $quiz->id,
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
            'submitted_late' => false,
        ]);
        $answer = ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'Draft'],
            'saved_at' => now(),
            'evaluation_status' => 'pending_manual',
            'points_awarded' => null,
        ]);

        $this->actingAs($examiner);
        $this->post(route('examiner.grading.grade', $answer), [
            'points_awarded' => 7,
            'grader_feedback' => 'Good structure.',
        ])->assertRedirect();

        $this->assertTrue(ActivityLog::query()->where('event_type', 'assignment_manual_grade')->exists());
    }

    public function test_release_assignment_grades_writes_activity_log(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now(), 'grades_released_at' => null]);

        $this->actingAs($examiner);
        $this->post(route('examiner.exams.release-assignment-grades', $quiz))->assertRedirect();

        $quiz->refresh();
        $this->assertNotNull($quiz->grades_released_at);
        $this->assertTrue(ActivityLog::query()->where('event_type', 'assignment_grades_released')->exists());
    }

    public function test_student_cannot_download_pdf_before_assignment_grade_release(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $student = $ctx['student'];
        $quiz = $this->makeReadyAssignment($ctx['examiner'], $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $quiz->id,
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

        $yearId = (int) DB::table('academic_years')->value('id');

        Result::query()->create([
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'academic_year_id' => $yearId,
            'score' => 8,
            'status' => 'graded',
            'feedback' => ['note' => 'Well done'],
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        $this->actingAs($student);
        $this->get(route('student.results.pdf', $session))->assertForbidden();
    }

    public function test_student_assignments_page_only_lists_assignment_assessments(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $student = $ctx['student'];

        $assignment = $this->makeReadyAssignment($ctx['examiner'], $ctx['courseId'], $ctx['classId']);
        $assignment->update(['status' => 'published', 'published_at' => now()]);

        $examQuiz = Quiz::query()->create([
            'university_id' => $ctx['examiner']->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $ctx['examiner']->id,
            'title' => 'Final exam',
            'description' => 'Invigilated exam',
            'assessment_type' => 'exam',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('exam', true, true, true),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $examQuiz->id,
            'class_id' => $ctx['classId'],
        ]);

        $this->actingAs($student);
        $html = $this->get(route('student.assignments.index'))->assertOk()->getContent();
        $this->assertStringContainsString($assignment->title, (string) $html);
        $this->assertStringNotContainsString($examQuiz->title, (string) $html);
    }
}
