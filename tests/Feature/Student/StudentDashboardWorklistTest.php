<?php

namespace Tests\Feature\Student;

use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use App\Support\AssessmentProctoringDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AssignmentCourseworkFlowTest;

class StudentDashboardWorklistTest extends AssignmentCourseworkFlowTest
{
    private function linkQuizToClass(int $quizId, int $classId): void
    {
        DB::table('quiz_class')->insert([
            'quiz_id' => $quizId,
            'class_id' => $classId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedQuiz(User $examiner, int $courseId, int $classId, array $overrides = []): Quiz
    {
        $defaults = [
            'university_id' => $examiner->university_id,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Test assessment',
            'description' => null,
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->subHour(),
            'end_time' => now()->addWeek(),
        ];

        $quiz = Quiz::query()->create(array_merge($defaults, $overrides));
        $this->linkQuizToClass((int) $quiz->id, $classId);

        return $quiz->fresh();
    }

    public function test_student_dashboard_loads_and_shows_worklist_headings(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $this->makePublishedQuiz($ctx['examiner'], $ctx['courseId'], $ctx['classId'], ['title' => 'Dash Smoke Quiz']);

        $this->actingAs($ctx['student'])
            ->get(route('student.work.index'))
            ->assertOk()
            ->assertSee(__('Assessments'), false)
            ->assertSee(__('LIVE'), false)
            ->assertSee(__('Dash Smoke Quiz'), false);
    }

    public function test_upcoming_quiz_shows_in_upcoming_section(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $this->makePublishedQuiz($ctx['examiner'], $ctx['courseId'], $ctx['classId'], [
            'title' => 'Future Window Quiz',
            'start_time' => now()->addDay(),
            'end_time' => now()->addWeek(),
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('student.work.index'))
            ->assertOk()
            ->assertSee(__('SOON'), false)
            ->assertSee('Future Window Quiz', false)
            ->assertSee(__('Preparation'), false);
    }

    public function test_continue_section_shows_in_progress_session(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $quiz = $this->makePublishedQuiz($ctx['examiner'], $ctx['courseId'], $ctx['classId'], [
            'title' => 'Continue Me Quiz',
        ]);

        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'S', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Pick',
            'type' => 'mcq',
            'options' => ['A', 'B'],
            'correct_answer' => [0],
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
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

        $this->actingAs($ctx['student'])
            ->get(route('student.work.index'))
            ->assertOk()
            ->assertSee(__('ONGOING'), false)
            ->assertSee('Continue Me Quiz', false)
            ->assertSee(__('In progress'), false);
    }

    public function test_assignment_submission_format_badges(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];

        $typedOptional = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Typed Optional File',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'start_time' => now()->subDay(),
            'end_time' => now()->addWeek(),
            'due_at' => now()->addDays(2),
            'assignment_allows_text' => true,
            'assignment_allows_files' => true,
            'assignment_attachment_required' => false,
        ]);
        $this->linkQuizToClass((int) $typedOptional->id, $ctx['classId']);

        $typedRequired = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Typed Required File',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'start_time' => now()->subDay(),
            'end_time' => now()->addWeek(),
            'due_at' => now()->addDays(2),
            'assignment_allows_text' => true,
            'assignment_allows_files' => true,
            'assignment_attachment_required' => true,
        ]);
        $this->linkQuizToClass((int) $typedRequired->id, $ctx['classId']);

        $typedOnly = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Typed Response Only',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'start_time' => now()->subDay(),
            'end_time' => now()->addWeek(),
            'due_at' => now()->addDays(2),
            'assignment_allows_text' => true,
            'assignment_allows_files' => false,
            'assignment_attachment_required' => false,
        ]);
        $this->linkQuizToClass((int) $typedOnly->id, $ctx['classId']);

        $html = $this->actingAs($ctx['student'])
            ->get(route('student.work.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString((string) __('Typed response · optional file'), (string) $html);
        $this->assertStringContainsString((string) __('Typed response · file required'), (string) $html);
        $this->assertStringContainsString((string) __('Typed response'), (string) $html);
    }

    public function test_submitted_assignment_awaiting_grading_appears_under_submitted_work(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Submitted Pending Assignment',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'start_time' => now()->subDay(),
            'end_time' => now()->addWeek(),
            'due_at' => now()->addDay(),
        ]);
        $this->linkQuizToClass((int) $quiz->id, $ctx['classId']);

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
            'score' => 0,
            'status' => 'pending_manual',
            'feedback' => null,
            'submitted_at' => now(),
            'graded_at' => null,
        ]);

        // The worklist is the "what to do" view — submitted items now live on
        // the dedicated Results page, not the worklist. Scope the check to
        // the worklist section so the header notification dropdown (which
        // may mention the title) doesn't trip the assertion.
        $workHtml = (string) $this->actingAs($student)
            ->get(route('student.work.index'))
            ->assertOk()
            ->getContent();
        $worklistSection = $this->isolateWorklistSection($workHtml);
        $this->assertStringNotContainsString('Submitted Pending Assignment', $worklistSection);
        $this->assertStringNotContainsString('qs-wl-item--submitted_work', $worklistSection);

        $this->actingAs($student)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertSee('Submitted Pending Assignment', false)
            ->assertSee(__('Awaiting grading'), false);
    }

    private function isolateWorklistSection(string $html): string
    {
        $start = strpos($html, 'id="student-work"');
        if ($start === false) {
            return '';
        }
        $end = strpos($html, '</section>', $start);
        if ($end === false) {
            return substr($html, $start);
        }

        return substr($html, $start, $end - $start);
    }

    /**
     * Extract the results-page listing markup (the qs-wl-list ul), so we can
     * make assertions that ignore the shared notification dropdown chrome.
     */
    private function isolateResultsList(string $html): string
    {
        $start = strpos($html, '<ul class="qs-wl-list qs-wl-list--shimmer');
        if ($start === false) {
            return '';
        }
        $end = strpos($html, '</ul>', $start);
        if ($end === false) {
            return substr($html, $start);
        }

        return substr($html, $start, $end - $start);
    }

    public function test_released_result_lists_under_results_released(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Released Marks Quiz',
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->subDay(),
            'end_time' => now()->addWeek(),
        ]);
        $this->linkQuizToClass((int) $quiz->id, $ctx['classId']);

        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'S', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Pick',
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
            'score' => 7,
            'status' => 'graded',
            'feedback' => null,
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        // Released results now live exclusively on the dedicated Results page;
        // the worklist focuses on actionable / upcoming work.
        $workHtml = (string) $this->actingAs($student)
            ->get(route('student.work.index'))
            ->assertOk()
            ->getContent();
        $worklistSection = $this->isolateWorklistSection($workHtml);
        $this->assertStringNotContainsString('Released Marks Quiz', $worklistSection);
        $this->assertStringNotContainsString('qs-wl-item--results_released', $worklistSection);

        $html = $this->actingAs($student)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertSee(__('GRADED'), false)
            ->assertSee('Released Marks Quiz', false)
            ->assertSee(__('View result'), false)
            ->getContent();

        $listing = $this->isolateResultsList((string) $html);
        $titlePos = strpos($listing, 'Released Marks Quiz');
        $this->assertNotFalse($titlePos, 'Released item title should appear in the results listing.');
        $itemStart = strrpos(substr($listing, 0, $titlePos), '<li class="qs-wl-item');
        $this->assertNotFalse($itemStart, 'Released item should render as a results card.');
        $itemHtml = substr($listing, $itemStart, $titlePos - $itemStart + 200);
        $this->assertStringContainsString('qs-wl-item--results_released', $itemHtml);
    }

    public function test_graded_assignment_without_release_stays_out_of_results_released(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'UNRELEASED ASSIGN MARKER',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'start_time' => now()->subDay(),
            'end_time' => now()->addWeek(),
            'due_at' => now()->addDay(),
            'grades_released_at' => null,
        ]);
        $this->linkQuizToClass((int) $quiz->id, $ctx['classId']);

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
            'score' => 9,
            'status' => 'graded',
            'feedback' => ['note' => 'ok'],
            'submitted_at' => now(),
            'graded_at' => now(),
        ]);

        // Worklist focuses on "what to do" — submitted assignments
        // (released or not) no longer appear here.
        $workHtml = (string) $this->actingAs($student)
            ->get(route('student.work.index'))
            ->assertOk()
            ->getContent();
        $worklistSection = $this->isolateWorklistSection($workHtml);
        $this->assertStringNotContainsString('UNRELEASED ASSIGN MARKER', $worklistSection);

        $html = $this->actingAs($student)
            ->get(route('student.results.index'))
            ->assertOk()
            ->assertSee('UNRELEASED ASSIGN MARKER', false)
            ->assertSee(__('Awaiting release'), false)
            ->getContent();

        $this->assertStringNotContainsString(
            'qs-wl-item--results_released',
            (string) $html,
            'Graded-but-unreleased assignments must not render under the results-released bucket.',
        );
    }

    public function test_closed_unsubmitted_quiz_lists_under_closed_or_missed(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $this->makePublishedQuiz($ctx['examiner'], $ctx['courseId'], $ctx['classId'], [
            'title' => 'Past Closed Quiz X',
            'start_time' => now()->subWeek(),
            'end_time' => now()->subHour(),
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('student.work.index'))
            ->assertOk()
            ->assertSee(__('MISSED'), false)
            ->assertSee('Past Closed Quiz X', false);
    }

    public function test_student_does_not_see_quiz_for_unassigned_course(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $uniId = (int) $examiner->university_id;
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');

        $otherCourseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-OTHER-DASH',
            'title' => 'Other course',
            'credit_hours' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherClassId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => (int) DB::table('programs')->where('code', 'BCS')->value('id'),
            'level_id' => (int) DB::table('levels')->where('code', '100')->value('id'),
            'name' => 'OtherClassDash',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('class_course')->insert([
            'class_id' => $otherClassId,
            'course_id' => $otherCourseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secret = Quiz::query()->create([
            'university_id' => $uniId,
            'course_id' => $otherCourseId,
            'created_by' => $examiner->id,
            'title' => 'SECRET OTHER CLASS QUIZ',
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 20,
            'total_marks' => 5,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->subHour(),
            'end_time' => now()->addWeek(),
        ]);
        $this->linkQuizToClass((int) $secret->id, $otherClassId);

        $this->actingAs($ctx['student'])
            ->get(route('student.work.index'))
            ->assertOk()
            ->assertDontSee('SECRET OTHER CLASS QUIZ', false);
    }

    public function test_student_cannot_view_peer_exam_session_result(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $quiz = $this->makePublishedQuiz($ctx['examiner'], $ctx['courseId'], $ctx['classId'], ['title' => 'Peer Result Quiz']);

        $section = ExamSection::query()->create(['exam_id' => $quiz->id, 'title' => 'S', 'section_order' => 1]);
        $q = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Pick',
            'type' => 'mcq',
            'options' => ['A', 'B'],
            'correct_answer' => [0],
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $peer = User::query()->create([
            'university_id' => $ctx['student']->university_id,
            'program_id' => $ctx['student']->program_id,
            'level_id' => $ctx['student']->level_id,
            'class_id' => $ctx['classId'],
            'name' => 'Peer Student',
            'email' => 'peer.'.Str::random(10).'@student-dashboard.test',
            'index_number' => 'PEER/DASH/'.Str::upper(Str::random(4)),
            'role' => 'student',
            'is_active' => true,
            'password' => 'password',
            'student_onboarded_at' => now(),
            'email_verified_at' => now(),
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $peer->id,
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
        ExamSessionQuestion::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'display_order' => 1,
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('student.results.show', $session))
            ->assertForbidden();
    }
}
