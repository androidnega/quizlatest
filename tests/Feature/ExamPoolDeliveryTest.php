<?php

namespace Tests\Feature;

use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\ExamSessionQuestionAssignmentService;
use App\Support\ExamRuntimeStateExtension;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamPoolDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, student: User, courseId: int, classId: int}
     */
    private function seedCoordinatorStudentCourseClass(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.pool.'.Str::random(8).'@test.edu',
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
            'code' => 'CS-POOL',
            'title' => 'Pool delivery course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'Pool',
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

    private function createDraftExam(User $examiner, int $courseId): Quiz
    {
        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $examiner->university_id,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Pool delivery exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'draft',
            'published_at' => null,
            'duration_minutes' => 30,
            'total_marks' => 0,
            'proctoring_settings' => json_encode(new \stdClass),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Quiz::query()->findOrFail($quizId);
    }

    private function addThreeApprovedMcqs(Quiz $exam): void
    {
        $section = ExamSection::query()->create([
            'exam_id' => $exam->id,
            'title' => 'A',
            'section_order' => 1,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            Question::query()->create([
                'quiz_id' => $exam->id,
                'section_id' => $section->id,
                'question_text' => 'Q'.$i,
                'type' => 'mcq',
                'options' => ['a', 'b'],
                'correct_answer' => [0],
                'answer_schema' => null,
                'marks' => 1,
                'question_order' => $i,
                'pool_status' => 'approved',
            ]);
        }

        $exam->update(['total_marks' => 3]);
    }

    public function test_assignment_respects_questions_per_student_and_is_idempotent(): void
    {
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);
        $this->addThreeApprovedMcqs($exam->fresh());

        $exam = $exam->fresh();
        $this->actingAs($ctx['examiner']);
        $this->patch(route('examiner.exams.delivery.update', $exam), [
            'questions_per_student' => 2,
            'randomize_questions' => false,
            'randomize_options' => false,
        ])->assertRedirect();

        $exam = $exam->fresh();
        $expectedQuestionIds = Question::query()
            ->where('quiz_id', $exam->id)
            ->where('pool_status', 'approved')
            ->orderBy('question_order')
            ->take(2)
            ->pluck('id')
            ->values()
            ->all();

        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $exam->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'active',
        ]);

        $svc = app(ExamSessionQuestionAssignmentService::class);
        $svc->assignForSession($session, $exam);

        $assignedIds = ExamSessionQuestion::query()
            ->where('exam_session_id', $session->id)
            ->orderBy('display_order')
            ->pluck('question_id')
            ->all();

        $this->assertCount(2, $assignedIds);
        $this->assertSame($expectedQuestionIds, $assignedIds);

        $svc->assignForSession($session->fresh(), $exam);
        $this->assertSame(2, ExamSessionQuestion::query()->where('exam_session_id', $session->id)->count());

        $payload = ExamRuntimeStateExtension::forSession($session->fresh());
        $questionCount = collect($payload['sections'])->sum(fn (array $s) => count($s['questions']));
        $this->assertSame(2, $questionCount);
        $this->assertSame(2.0, $payload['exam']['total_marks']);
    }

    public function test_entry_pipeline_seeds_per_session_question_subset_so_runtime_does_not_serve_all_questions(): void
    {
        // Regression for the case where the runtime fell back to showing
        // every approved question because nobody had ever called the
        // assignment service in production. The entry pipeline must now
        // materialize the per-student subset the moment a session is
        // created so the very first state fetch serves the right slice.
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        // Build a 5-question pool (every one approved) and tell the exam
        // each student should see ONLY 2 of them.
        $section = ExamSection::query()->create([
            'exam_id' => $exam->id,
            'title' => 'A',
            'section_order' => 1,
        ]);
        for ($i = 1; $i <= 5; $i++) {
            Question::query()->create([
                'quiz_id' => $exam->id,
                'section_id' => $section->id,
                'question_text' => 'Q'.$i,
                'type' => 'mcq',
                'options' => ['a', 'b', 'c', 'd'],
                'correct_answer' => [0],
                'answer_schema' => null,
                'marks' => 1,
                'question_order' => $i,
                'pool_status' => 'approved',
            ]);
        }
        $exam->update([
            'status' => 'published',
            'published_at' => now()->subHour(),
            'start_time' => now()->subHour(),
            'end_time' => now()->addWeek(),
            'questions_per_student' => 2,
            'randomize_questions' => true,
            'randomize_options' => true,
            'total_marks' => 5,
        ]);

        // We're driving the entry pipeline directly (no real browser →
        // no snapshot file), so disable the start-snapshot gate for this
        // test only. It's the same toggle examiners use when they want
        // to allow exams to start without a verification photo.
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => true,
            'university_id' => $ctx['examiner']->university_id,
            'email_verified_at' => now(),
        ]);
        app(\App\Services\SystemSettingsService::class)
            ->set('require_exam_start_snapshot', '0', $admin);
        app(\App\Services\SystemSettingsService::class)
            ->set('face_verification_required', '0', $admin);

        // Drive the entry pipeline the same way the controller does.
        $request = \Illuminate\Http\Request::create('/exam-sessions/start', 'POST');
        $request->setUserResolver(fn () => $ctx['student']);

        $pipeline = app(\App\Services\ExamEntryPipelineService::class);
        $result = $pipeline->execute($request, [
            'exam_id' => (int) $exam->id,
        ]);

        $session = ExamSession::query()->where('session_id', $result['session_id'])->firstOrFail();
        $assignedIds = ExamSessionQuestion::query()
            ->where('exam_session_id', $session->id)
            ->orderBy('display_order')
            ->pluck('question_id')
            ->all();

        $this->assertCount(
            2,
            $assignedIds,
            'questions_per_student=2 must serve exactly 2 questions per session, even from a 5-question pool.',
        );

        // And the state payload that the runtime actually consumes must
        // match — i.e. the runtime sees the same 2 questions, not all 5.
        $payload = ExamRuntimeStateExtension::forSession($session->fresh());
        $questionCount = collect($payload['sections'])->sum(fn (array $s) => count($s['questions']));
        $this->assertSame(
            2,
            $questionCount,
            'The runtime state payload must respect the per-session subset.',
        );

        // The serialized questions should also have shuffled options because
        // randomize_options=true → the assignment service writes a non-null
        // option_order on each ExamSessionQuestion. We can't assert a
        // specific permutation (it's random), but we can assert the option
        // metadata is present and well-formed.
        $links = ExamSessionQuestion::query()
            ->where('exam_session_id', $session->id)
            ->get();
        foreach ($links as $link) {
            $this->assertIsArray(
                $link->option_order,
                'Each MCQ link must store a per-session option shuffle so different students see different correct-answer positions.',
            );
            $this->assertCount(4, $link->option_order);
            $this->assertSame([0, 1, 2, 3], collect($link->option_order)->sort()->values()->all());
        }
    }
}
