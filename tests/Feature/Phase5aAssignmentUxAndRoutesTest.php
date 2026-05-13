<?php

namespace Tests\Feature;

use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Phase5aAssignmentUxAndRoutesTest extends AssignmentCourseworkFlowTest
{
    public function test_quiz_hero_demo_route_removed_and_unnamed(): void
    {
        $this->get('/quiz-hero-demo')->assertNotFound();
        $this->assertFalse(Route::has('quiz-hero-demo'));
    }

    public function test_web_routes_file_has_no_quiz_hero_demo_reference(): void
    {
        $web = file_get_contents(base_path('routes/web.php')) ?: '';
        $this->assertStringNotContainsString('quiz-hero-demo', $web);
    }

    public function test_assignment_take_page_shows_coursework_context_and_hides_live_proctoring_aside(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Phase 5A assignment UI',
            'description' => 'Read carefully before you answer.',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 120,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'start_time' => now()->subDay(),
            'end_time' => now()->addWeek(),
            'due_at' => now()->addDays(3),
        ]);

        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);

        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'Main', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Write here.',
            'type' => 'essay',
            'options' => null,
            'correct_answer' => null,
            'answer_schema' => null,
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $quiz->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
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

        $this->actingAs($student);
        $html = $this->get(route('student.exam.take', $session))->assertOk()->getContent();

        $this->assertStringContainsString('id="assignment-coursework-panel"', (string) $html);
        $this->assertStringContainsString('Phase 5A assignment UI', (string) $html);
        $this->assertStringContainsString('Read carefully before you answer.', (string) $html);
        $this->assertStringContainsString('name="qs-assignment-clipboard-block" content="1"', (string) $html);
        $this->assertStringContainsString('Copy and paste is disabled', (string) $html);
        $this->assertStringContainsString('id="proctoring-live-aside"', (string) $html);
        $this->assertMatchesRegularExpression('/id="proctoring-live-aside"[^>]*\bhidden\b/', (string) $html);
        $this->assertStringNotContainsString('id="exam-timer"', (string) $html);
    }

    public function test_assignment_state_includes_student_view_and_released_grade(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $ctx = $this->seedExaminerStudentCourse();
        $student = $ctx['student'];
        $examiner = $ctx['examiner'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'API assignment',
            'description' => str_repeat('b', 50),
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'due_at' => now()->subHour(),
            'grades_released_at' => now(),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);
        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'Main', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Essay',
            'type' => 'essay',
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

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
            'submitted_late' => true,
        ]);
        ExamSessionQuestion::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'display_order' => 1,
        ]);

        $yearId = (int) DB::table('academic_years')->value('id');
        Result::query()->create([
            'user_id' => $student->id,
            'quiz_id' => $quiz->id,
            'academic_year_id' => $yearId,
            'score' => 8,
            'status' => 'graded',
            'feedback' => ['note' => 'Nice work.'],
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        $this->actingAs($student);
        $json = $this->getJson(route('exam-sessions.state', $session))->assertOk()->json();
        $this->assertArrayHasKey('assignment_student_view', $json);
        $this->assertTrue($json['assignment_student_view']['grades_visible_to_student']);
        $this->assertEquals(8.0, (float) $json['assignment_student_view']['score']);
        $this->assertStringContainsString('Nice work.', (string) $json['assignment_student_view']['examiner_feedback']);
        $this->assertTrue($json['assignment_student_view']['session_submitted_late']);
    }

    public function test_regular_exam_take_page_still_exposes_timer_and_fullscreen_controls(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Phase 5A timed quiz',
            'description' => 'Exam style',
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);
        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'S', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Pick one',
            'type' => 'mcq',
            'options' => ['A', 'B'],
            'correct_answer' => [0],
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $quiz->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'in_progress',
        ]);
        ExamSessionQuestion::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'display_order' => 1,
        ]);

        $this->actingAs($student);
        $html = $this->get(route('student.exam.take', $session))->assertOk()->getContent();
        $this->assertStringContainsString('id="exam-timer"', (string) $html);
        $this->assertStringContainsString('id="btn-fullscreen"', (string) $html);
        $this->assertStringNotContainsString('id="assignment-coursework-panel"', (string) $html);
        $this->assertStringContainsString('name="qs-assignment-mode" content="0"', (string) $html);
    }

    public function test_examiner_builder_shows_assignment_submission_stats(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Stats assignment',
            'description' => str_repeat('c', 50),
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'due_at' => now()->addDay(),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);
        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'Main', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Write',
            'type' => 'essay',
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        ExamSession::query()->create([
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
            'submitted_late' => true,
        ]);

        $this->actingAs($examiner);
        $html = $this->get(route('examiner.quizzes.workspace', ['exam' => $quiz]))->assertOk()->getContent();
        $this->assertStringContainsString('Late submissions', (string) $html);
        $this->assertStringContainsString('Release grades to students', (string) $html);
    }

    public function test_examiner_assignment_session_review_shows_coursework_context(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Session review assignment',
            'description' => str_repeat('d', 60),
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'due_at' => now()->addDay(),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);
        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'Main', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Write',
            'type' => 'essay',
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

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
        ExamSessionQuestion::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'display_order' => 1,
        ]);

        $this->actingAs($examiner);
        $html = $this->get(route('examiner.exam-sessions.show', $session))->assertOk()->getContent();
        $this->assertStringContainsString('Coursework assignment', (string) $html);
        $this->assertStringContainsString('Grading queue', (string) $html);
    }
}
