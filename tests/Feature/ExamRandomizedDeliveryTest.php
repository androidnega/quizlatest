<?php

namespace Tests\Feature;

use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\AnswerEvaluationService;
use App\Services\ExamAnswerSynthesisService;
use App\Services\ExamSessionQuestionAssignmentService;
use App\Support\ExamRuntimeStateExtension;
use App\Support\StudentExamResultBreakdown;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamRandomizedDeliveryTest extends TestCase
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
            'email' => 'examiner.rand.'.Str::random(8).'@test.edu',
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
            'code' => 'CS-RAND',
            'title' => 'Randomized delivery course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'Rand',
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
            'title' => 'Randomized delivery exam',
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

    /**
     * @return array{exam: Quiz, session: ExamSession}
     */
    private function makeSessionWithDelivery(
        Quiz $exam,
        User $student,
        int $classId,
        int $questionsPerStudent,
        bool $randomizeQuestions,
        bool $randomizeOptions,
    ): array {
        $exam->refresh();
        $exam->update([
            'questions_per_student' => $questionsPerStudent,
            'randomize_questions' => $randomizeQuestions,
            'randomize_options' => $randomizeOptions,
        ]);
        $exam = $exam->fresh();

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
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

        app(ExamSessionQuestionAssignmentService::class)->assignForSession($session, $exam);
        app(ExamAnswerSynthesisService::class)->ensureEveryQuestionHasAnswer($session->fresh());

        return ['exam' => $exam, 'session' => $session->fresh()];
    }

    public function test_randomized_questions_runtime_is_flat_and_follows_display_order(): void
    {
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        $s1 = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'Alpha', 'section_order' => 1]);
        $s2 = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'Beta', 'section_order' => 2]);

        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $s1->id,
            'question_text' => 'MCQ',
            'type' => 'mcq',
            'options' => ['x', 'y'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $s2->id,
            'question_text' => 'TF',
            'type' => 'true_false',
            'options' => null,
            'correct_answer' => true,
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $exam->update(['total_marks' => 2]);

        ['session' => $session] = $this->makeSessionWithDelivery($exam->fresh(), $ctx['student'], $ctx['classId'], 2, true, false);

        $expectedOrder = ExamSessionQuestion::query()
            ->where('exam_session_id', $session->id)
            ->orderBy('display_order')
            ->pluck('question_id')
            ->values()
            ->all();

        $payload = ExamRuntimeStateExtension::forSession($session->fresh());
        $this->assertCount(1, $payload['sections']);
        $flatIds = collect($payload['sections'][0]['questions'])->pluck('id')->all();
        $this->assertSame($expectedOrder, $flatIds);
        $this->assertSame(2.0, $payload['exam']['total_marks']);
        $types = collect($payload['sections'][0]['questions'])->pluck('type')->all();
        $this->assertContains('mcq', $types);
        $this->assertContains('true_false', $types);
    }

    public function test_non_randomized_preserves_multiple_sections(): void
    {
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        $s1 = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'Alpha', 'section_order' => 1]);
        $s2 = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'Beta', 'section_order' => 2]);

        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $s1->id,
            'question_text' => 'Q1',
            'type' => 'mcq',
            'options' => ['a', 'b'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $s2->id,
            'question_text' => 'Q2',
            'type' => 'mcq',
            'options' => ['c', 'd'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $exam->update(['total_marks' => 2]);

        ['session' => $session] = $this->makeSessionWithDelivery($exam->fresh(), $ctx['student'], $ctx['classId'], 2, false, false);

        $payload = ExamRuntimeStateExtension::forSession($session->fresh());
        $this->assertGreaterThanOrEqual(2, count($payload['sections']));
        $titles = collect($payload['sections'])->pluck('title')->all();
        $this->assertContains('Alpha', $titles);
        $this->assertContains('Beta', $titles);
    }

    public function test_randomize_options_sets_option_order_only_for_mcq(): void
    {
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'MCQ',
            'type' => 'mcq',
            'options' => ['p', 'q', 'r'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        Question::query()->create([
            'quiz_id' => $exam->id,
            'section_id' => $section->id,
            'question_text' => 'TF',
            'type' => 'true_false',
            'options' => null,
            'correct_answer' => false,
            'answer_schema' => null,
            'marks' => 1,
            'question_order' => 2,
            'pool_status' => 'approved',
        ]);
        $exam->update(['total_marks' => 2]);

        ['session' => $session] = $this->makeSessionWithDelivery($exam->fresh(), $ctx['student'], $ctx['classId'], 2, false, true);

        $links = ExamSessionQuestion::query()
            ->where('exam_session_id', $session->id)
            ->with('question')
            ->orderBy('display_order')
            ->get();

        $this->assertCount(2, $links);
        foreach ($links as $link) {
            $type = (string) $link->question?->type;
            if ($type === 'mcq') {
                $this->assertIsArray($link->option_order);
                $this->assertCount(3, $link->option_order);
            } else {
                $this->assertNull($link->option_order);
            }
        }
    }

    public function test_all_supported_types_can_be_selected_when_randomized(): void
    {
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'Mixed', 'section_order' => 1]);

        $defs = [
            ['type' => 'mcq', 'options' => ['a', 'b'], 'correct_answer' => [0], 'text' => 'M'],
            ['type' => 'true_false', 'options' => null, 'correct_answer' => true, 'text' => 'T'],
            ['type' => 'fill_blank', 'options' => null, 'correct_answer' => ['cat'], 'text' => 'The ___ sat.', 'schema' => ['blank_count' => 1]],
            ['type' => 'essay', 'options' => null, 'correct_answer' => null, 'text' => 'Write.', 'schema' => null],
        ];

        $order = 0;
        foreach ($defs as $def) {
            $order++;
            Question::query()->create([
                'quiz_id' => $exam->id,
                'section_id' => $section->id,
                'question_text' => $def['text'],
                'type' => $def['type'],
                'options' => $def['options'],
                'correct_answer' => $def['correct_answer'],
                'answer_schema' => $def['schema'] ?? null,
                'marks' => 1,
                'question_order' => $order,
                'pool_status' => 'approved',
            ]);
        }
        $exam->update(['total_marks' => 4]);

        ['session' => $session] = $this->makeSessionWithDelivery($exam->fresh(), $ctx['student'], $ctx['classId'], 4, true, false);

        $assignedTypes = Question::query()
            ->whereIn('id', ExamSessionQuestion::query()->where('exam_session_id', $session->id)->pluck('question_id'))
            ->pluck('type')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['essay', 'fill_blank', 'mcq', 'true_false'], $assignedTypes);

        $payload = ExamRuntimeStateExtension::forSession($session->fresh());
        $essaySeen = collect($payload['sections'][0]['questions'])->contains(fn ($q) => ($q['type'] ?? '') === 'essay');
        $this->assertTrue($essaySeen, 'Essay remains available for student runtime (essay anti-copy attaches client-side).');
    }

    public function test_evaluation_and_breakdown_ignore_unassigned_answers(): void
    {
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);

        $qIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $qIds[] = Question::query()->create([
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
            ])->id;
        }
        $exam->update(['total_marks' => 3]);

        ['session' => $session] = $this->makeSessionWithDelivery($exam->fresh(), $ctx['student'], $ctx['classId'], 2, false, false);

        $assignedIds = ExamSessionQuestion::query()
            ->where('exam_session_id', $session->id)
            ->pluck('question_id')
            ->all();

        $extraId = collect($qIds)->first(fn ($id) => ! in_array($id, $assignedIds, true));
        $this->assertNotNull($extraId);

        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $extraId,
            'answer_text' => null,
            'answer_payload' => ['type' => 'mcq', 'selected' => [1]],
            'saved_at' => now(),
        ]);

        foreach ($assignedIds as $qid) {
            ExamSessionAnswer::query()->where('exam_session_id', $session->id)->where('question_id', $qid)->update([
                'answer_payload' => ['type' => 'mcq', 'selected' => [0]],
            ]);
        }

        $graded = $session->fresh(['answers', 'exam.questions']);
        $out = app(AnswerEvaluationService::class)->evaluateAndPersist($graded);
        $this->assertSame(2.0, $out['total_score']);

        $sessionForBreakdown = $session->fresh();
        $sessionForBreakdown->load([
            'sessionQuestions',
            'answers.question',
        ]);
        $rows = StudentExamResultBreakdown::rows($sessionForBreakdown, false);
        $this->assertCount(2, $rows);
    }

    public function test_assignment_is_stable_on_repeat_call(): void
    {
        $ctx = $this->seedCoordinatorStudentCourseClass();
        $exam = $this->createDraftExam($ctx['examiner'], $ctx['courseId']);

        $section = ExamSection::query()->create(['exam_id' => $exam->id, 'title' => 'A', 'section_order' => 1]);
        for ($i = 1; $i <= 3; $i++) {
            Question::query()->create([
                'quiz_id' => $exam->id,
                'section_id' => $section->id,
                'question_text' => 'Q'.$i,
                'type' => 'fill_blank',
                'options' => null,
                'correct_answer' => ['ok'],
                'answer_schema' => ['blank_count' => 1],
                'marks' => 1,
                'question_order' => $i,
                'pool_status' => 'approved',
            ]);
        }
        $exam->update(['total_marks' => 3]);
        $exam->refresh()->update([
            'questions_per_student' => 2,
            'randomize_questions' => true,
            'randomize_options' => false,
        ]);

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
        $svc->assignForSession($session, $exam->fresh());
        $first = ExamSessionQuestion::query()->where('exam_session_id', $session->id)->orderBy('display_order')->pluck('question_id')->all();

        $svc->assignForSession($session->fresh(), $exam->fresh());
        $second = ExamSessionQuestion::query()->where('exam_session_id', $session->id)->orderBy('display_order')->pluck('question_id')->all();

        $this->assertSame($first, $second);
        $this->assertCount(2, $first);
    }
}
