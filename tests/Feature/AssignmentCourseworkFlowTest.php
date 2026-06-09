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
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        $html = (string) $this->get(route('student.assignments.index'))->assertOk()->getContent();
        // Exclude global shell (notification dropdown lists all published assessments).
        $html = (string) preg_replace('/<div class="sticky top-0 z-30 shrink-0">[\s\S]*?<\/div>\s*(?=<main)/', '', $html);
        $this->assertStringContainsString($assignment->title, $html);
        $this->assertStringNotContainsString($examQuiz->title, $html);
    }

    public function test_assignment_ai_assist_grades_pending_submissions(): void
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
            'answer_payload' => ['type' => 'essay', 'text' => 'A thoughtful essay response.'],
            'saved_at' => now(),
            'evaluation_status' => 'pending_manual',
            'points_awarded' => null,
        ]);

        $settings = app(SystemSettingsService::class);
        $settings->set('enable_ai', 'true', $examiner);
        $settings->set('deepseek_api_key', 'test-key', $examiner);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'points_awarded' => 8,
                    'feedback' => 'Strong analysis.',
                ])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
                'model' => 'deepseek-chat',
            ], 200),
        ]);

        $this->actingAs($examiner);
        $this->post(route('examiner.exams.assignment-grade-ai', $quiz))
            ->assertRedirect(route('examiner.quizzes.workspace', ['exam' => $quiz, 'tab' => 'overview']));

        $answer->refresh();
        $this->assertSame('manual_graded', $answer->evaluation_status);
        $this->assertEquals(8.0, (float) $answer->points_awarded);
        $this->assertTrue(ActivityLog::query()->where('event_type', 'assignment_ai_grade')->exists());
    }

    public function test_ai_assist_from_grading_queue_redirects_back_to_pending_and_surfaces_drafts(): void
    {
        // Bug repro: clicking "AI assist" on the grading queue used to redirect
        // the examiner to the assignment workspace overview AND remove the just-
        // graded rows from the pending table (they move to manual_graded). That
        // made the AI run feel like it did nothing. The fix:
        //   1. honor return_to=pending so we stay on the grading queue, and
        //   2. surface the just-AI-drafted answers in a "review & release" panel.
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];
        $courseId = $ctx['courseId'];
        $classId = $ctx['classId'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'title' => 'Essay assignment for AI assist queue redirect',
            'description' => 'A short assignment to drive the AI redirect coverage.',
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'duration_minutes' => 30,
            'total_marks' => 10,
            'status' => 'published',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
        ]);
        $section = ExamSection::query()->create([
            'exam_id' => $quiz->id,
            'title' => 'Essays',
            'section_order' => 1,
        ]);
        $question = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Explain the role of indexes in DBMS.',
            'type' => 'essay',
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
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
            'question_id' => $question->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'A thoughtful essay response.'],
            'saved_at' => now(),
            'evaluation_status' => 'pending_manual',
            'points_awarded' => null,
        ]);

        $settings = app(SystemSettingsService::class);
        $settings->set('enable_ai', 'true', $examiner);
        $settings->set('deepseek_api_key', 'test-key', $examiner);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'points_awarded' => 8,
                    'feedback' => 'Solid coverage of clustered vs non-clustered indexes.',
                    'strengths' => 'Clear examples for B-tree usage.',
                    'improvements' => 'Could mention hash indexes and trade-offs.',
                ])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
                'model' => 'deepseek-chat',
            ], 200),
        ]);

        $this->actingAs($examiner);

        // With return_to=pending, we stay on the grading queue (filtered)
        // AND the flash carries the IDs of the answers we just AI-graded.
        $post = $this->from(route('examiner.grading.pending'))
            ->post(route('examiner.exams.assignment-grade-ai', $quiz), ['return_to' => 'pending']);

        $post->assertRedirect(route('examiner.grading.pending', ['exam' => $quiz->id]))
            ->assertSessionHas('status')
            ->assertSessionHas('ai_grade_just_completed', function ($payload) use ($quiz, $answer) {
                return is_array($payload)
                    && (int) ($payload['exam_id'] ?? 0) === (int) $quiz->id
                    && (string) ($payload['exam_title'] ?? '') === $quiz->title
                    && in_array((int) $answer->id, array_map('intval', (array) ($payload['answer_ids'] ?? [])), true);
            });

        $answer->refresh();
        $this->assertSame('manual_graded', $answer->evaluation_status);
        $this->assertEquals(8.0, (float) $answer->points_awarded);

        // Follow the redirect to render the grading queue with the flash
        // still alive. The "Recently AI-drafted" panel MUST appear, name
        // the student, quote the AI feedback snippet, and link straight
        // to the per-answer review page — otherwise the rows simply move
        // from "pending" to "manual_graded" and disappear from view.
        $followUp = $this->followRedirects($post);
        $followUp->assertOk();
        $body = $followUp->getContent();

        $this->assertStringContainsString('Recently AI-drafted', $body, 'Drafted panel must appear after AI assist from the queue.');
        $this->assertStringContainsString('Review &amp; release', $body, 'Each drafted row must offer a review & release CTA.');
        $this->assertStringContainsString($student->name, $body, 'Drafted row must name the student whose answer was AI-graded.');
        $this->assertStringContainsString('Solid coverage', $body, 'Drafted row must surface the AI feedback snippet.');
        $this->assertStringContainsString(
            route('examiner.grading.show', $answer),
            $body,
            'Each drafted row must link directly to the grade-show page.',
        );
    }

    public function test_grading_queue_forms_send_return_to_pending_so_clicks_stay_on_queue(): void
    {
        // Compile-time guarantee that the two AI-assist forms on the
        // grading queue both include the hidden return_to=pending input
        // — otherwise the user gets bounced to the workspace and never
        // sees the drafts panel.
        $blade = file_get_contents(resource_path('views/examiner/grading/index.blade.php'));
        $this->assertIsString($blade);

        // Both the filter banner form AND the per-assignment list form must
        // carry the hint. Counting "value=\"pending\"" is the cleanest signal.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($blade, 'name="return_to" value="pending"'),
            'Both AI-assist forms on the grading queue must post return_to=pending.',
        );
    }

    /**
     * Build N essay questions on a quiz and create one submitted session per supplied student,
     * each with one pending essay answer per question. Returns the list of created answer ids.
     *
     * @param  list<\App\Models\User>  $students
     * @return list<int>
     */
    private function seedAssignmentSubmissions(Quiz $quiz, int $classId, array $students, int $extraEssayQuestions = 0): array
    {
        $section = $quiz->sections()->firstOrFail();
        for ($i = 1; $i <= $extraEssayQuestions; $i++) {
            Question::query()->create([
                'quiz_id' => $quiz->id,
                'section_id' => $section->id,
                'question_text' => 'Discuss sub-topic '.$i.'.',
                'type' => 'essay',
                'marks' => 5,
                'question_order' => 1 + $i,
                'pool_status' => 'approved',
            ]);
        }

        $allQuestions = $quiz->fresh()->questions()->where('type', 'essay')->get();
        $answerIds = [];
        foreach ($students as $student) {
            $session = ExamSession::query()->create([
                'student_id' => $student->id,
                'class_id' => $classId,
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
            foreach ($allQuestions as $q) {
                $row = ExamSessionAnswer::query()->create([
                    'exam_session_id' => $session->id,
                    'question_id' => $q->id,
                    'answer_payload' => ['type' => 'essay', 'text' => 'Student response for '.$q->id.'.'],
                    'saved_at' => now(),
                    'evaluation_status' => 'pending_manual',
                    'points_awarded' => null,
                ]);
                $answerIds[] = (int) $row->id;
            }
        }

        return $answerIds;
    }

    public function test_examiner_dashboard_grading_pill_counts_submissions_not_essay_answer_rows(): void
    {
        // Bug repro: 1 assignment + 1 student + several essay sub-questions
        // used to make the "Grading" pill on the examiner dashboard show the
        // number of essay-answer rows (e.g. "17") instead of "1" pending
        // submission. The pill should be a submission count.
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);

        $this->seedAssignmentSubmissions($quiz, $ctx['classId'], [$ctx['student']], extraEssayQuestions: 16);

        // 17 essay answer rows total (1 base question + 16 extra) for 1 submission.
        $this->assertSame(
            17,
            ExamSessionAnswer::query()
                ->where('evaluation_status', 'pending_manual')
                ->whereHas('question', fn ($q) => $q->where('quiz_id', $quiz->id))
                ->count(),
            'Sanity: 1 submission × 17 essay questions should produce 17 answer rows.',
        );

        $this->actingAs($examiner);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // The "Needs grading" card renders the count inside a <p class="...">{n}</p>.
        // Assert "1" appears in that context and "17" does NOT (would indicate the
        // old inflated answer-row count had regressed).
        $this->assertMatchesRegularExpression(
            '/Needs grading[\s\S]{0,400}>\s*1\s*</',
            $html,
            'The "Needs grading" card must show 1 submission, not the inflated answer-row count.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/Needs grading[\s\S]{0,400}>\s*17\s*</',
            $html,
            'The "Needs grading" card must NOT show the raw essay-answer-row count (17).',
        );
    }

    public function test_examiner_dashboard_grading_pill_ignores_essays_on_non_assignment_quizzes(): void
    {
        // The grading QUEUE (ManualGradingController::pendingEssayQuery) is
        // assignment-only, so the dashboard pill must use the same scope.
        // Legacy essay rows on non-assignment quizzes must not be counted.
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $student = $ctx['student'];

        $assignment = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $assignment->update(['status' => 'published', 'published_at' => now()]);
        $this->seedAssignmentSubmissions($assignment, $ctx['classId'], [$student]);

        // Legacy non-assignment exam essay (must NOT bump the grading pill).
        $legacyExam = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Legacy timed exam with essay',
            'description' => 'should not count',
            'assessment_type' => 'exam',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('exam', true, true, true),
        ]);
        DB::table('quiz_class')->insert(['quiz_id' => $legacyExam->id, 'class_id' => $ctx['classId']]);
        $legacySection = ExamSection::query()->create(['exam_id' => $legacyExam->id, 'title' => 'M', 'section_order' => 1]);
        $legacyQ = Question::query()->create([
            'quiz_id' => $legacyExam->id,
            'section_id' => $legacySection->id,
            'question_text' => 'Legacy essay.',
            'type' => 'essay',
            'marks' => 10,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $legacySession = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $legacyExam->id,
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
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $legacySession->id,
            'question_id' => $legacyQ->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'Legacy answer text.'],
            'saved_at' => now(),
            'evaluation_status' => 'pending_manual',
            'points_awarded' => null,
        ]);

        $this->actingAs($examiner);
        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        // Exactly 1 pending submission (from the assignment); legacy exam ignored.
        $this->assertMatchesRegularExpression(
            '/Needs grading[\s\S]{0,400}>\s*1\s*</',
            $html,
            'Dashboard pill must count only assignment submissions, not legacy exam essays.',
        );
    }

    public function test_grading_queue_surfaces_ai_assist_panel_when_unfiltered(): void
    {
        // Previously the queue page only showed the AI assist button after the
        // user had filtered by exam. That left users (correctly) confused that
        // "AI grading for assignments" wasn't anywhere. The unfiltered queue
        // must now list every assignment with pending submissions and expose
        // an AI assist form per assignment.
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);
        $this->seedAssignmentSubmissions($quiz, $ctx['classId'], [$ctx['student']]);

        $settings = app(SystemSettingsService::class);
        $settings->set('enable_ai', 'true', $examiner);

        $this->actingAs($examiner);
        $html = (string) $this->get(route('examiner.grading.pending'))->assertOk()->getContent();

        $this->assertStringContainsString('AI assist by assignment', $html);
        $this->assertStringContainsString($quiz->title, $html);
        $this->assertStringContainsString(
            route('examiner.exams.assignment-grade-ai', $quiz->id),
            $html,
            'Unfiltered queue must include the per-assignment AI assist form action.',
        );
    }

    public function test_grading_queue_warns_when_ai_disabled_so_examiner_knows_why_no_button(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);
        $this->seedAssignmentSubmissions($quiz, $ctx['classId'], [$ctx['student']]);

        $settings = app(SystemSettingsService::class);
        $settings->set('enable_ai', 'false', $examiner);

        $this->actingAs($examiner);
        $html = (string) $this->get(route('examiner.grading.pending'))->assertOk()->getContent();

        $this->assertStringContainsString('AI grading is currently disabled', $html);
        $this->assertStringNotContainsString('AI assist by assignment', $html);
    }

    public function test_grading_show_page_renders_per_answer_ai_suggest_button(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);
        $ids = $this->seedAssignmentSubmissions($quiz, $ctx['classId'], [$ctx['student']]);
        $answerId = $ids[0];

        $settings = app(SystemSettingsService::class);
        $settings->set('enable_ai', 'true', $examiner);

        $this->actingAs($examiner);
        $html = (string) $this->get(route('examiner.grading.show', $answerId))->assertOk()->getContent();

        $this->assertStringContainsString('AI suggest grade', $html);
        $this->assertStringContainsString(
            route('examiner.grading.ai-suggest', $answerId),
            $html,
            'Show page must include the per-answer AI suggest form action.',
        );
    }

    public function test_per_answer_ai_suggest_endpoint_commits_grade_and_redirects_back_to_show(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);
        $ids = $this->seedAssignmentSubmissions($quiz, $ctx['classId'], [$ctx['student']]);
        $answerId = $ids[0];

        $settings = app(SystemSettingsService::class);
        $settings->set('enable_ai', 'true', $examiner);
        $settings->set('deepseek_api_key', 'test-key', $examiner);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'points_awarded' => 7,
                    'feedback' => 'Solid coverage of the prompt.',
                    'strengths' => 'Clear thesis.',
                    'improvements' => 'Cite more sources.',
                ])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
                'model' => 'deepseek-chat',
            ], 200),
        ]);

        $this->actingAs($examiner)
            ->post(route('examiner.grading.ai-suggest', $answerId))
            ->assertRedirect(route('examiner.grading.show', $answerId));

        $row = ExamSessionAnswer::query()->find($answerId);
        $this->assertSame('manual_graded', $row->evaluation_status);
        $this->assertEquals(7.0, (float) $row->points_awarded);
        $detail = is_array($row->evaluation_detail) ? $row->evaluation_detail : [];
        $this->assertIsArray($detail['ai_assist'] ?? null, 'AI assist metadata must be persisted on the answer.');
        $this->assertTrue(ActivityLog::query()->where('event_type', 'assignment_ai_grade')->exists());
    }

    public function test_per_answer_ai_suggest_endpoint_rejects_when_ai_disabled(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];
        $quiz = $this->makeReadyAssignment($examiner, $ctx['courseId'], $ctx['classId']);
        $quiz->update(['status' => 'published', 'published_at' => now()]);
        $ids = $this->seedAssignmentSubmissions($quiz, $ctx['classId'], [$ctx['student']]);
        $answerId = $ids[0];

        $settings = app(SystemSettingsService::class);
        $settings->set('enable_ai', 'false', $examiner);

        $this->actingAs($examiner)
            ->post(route('examiner.grading.ai-suggest', $answerId))
            ->assertRedirect()
            ->assertSessionHasErrors('ai');

        $this->assertSame('pending_manual', ExamSessionAnswer::query()->find($answerId)?->evaluation_status);
    }

    public function test_grading_queue_can_filter_by_assignment(): void
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
        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $q->id,
            'answer_payload' => ['type' => 'essay', 'text' => 'Pending'],
            'saved_at' => now(),
            'evaluation_status' => 'pending_manual',
        ]);

        $this->actingAs($examiner);
        $html = $this->get(route('examiner.grading.pending', ['exam' => $quiz->id]))->assertOk()->getContent();
        $this->assertStringContainsString($quiz->title, (string) $html);
        $this->assertStringContainsString('Filtering', (string) $html);
    }

    public function test_submitted_success_page_shows_after_assignment_submit(): void
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
            'submitted_late' => false,
        ]);

        $this->actingAs($student);
        $this->get(route('student.exam.submitted', $session))
            ->assertOk()
            ->assertSee('Assignment submitted', false)
            ->assertSee('Back to assignments', false);

        $session->update(['status' => 'active', 'end_time' => null]);
        $this->get(route('student.exam.submitted', $session))
            ->assertRedirect(route('student.exam.take', $session));
    }
}
