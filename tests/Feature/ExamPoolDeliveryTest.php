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
     * @return array{coord: User, student: User, courseId: int, classId: int}
     */
    private function seedCoordinatorStudentCourseClass(): array
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

        $student = User::query()->where('role', 'student')->firstOrFail();
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        return ['coord' => $coord, 'student' => $student->fresh(), 'courseId' => $courseId, 'classId' => $classId];
    }

    private function createDraftExam(User $coord, int $courseId): Quiz
    {
        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $coord->university_id,
            'course_id' => $courseId,
            'created_by' => $coord->id,
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
        $exam = $this->createDraftExam($ctx['coord'], $ctx['courseId']);
        $this->addThreeApprovedMcqs($exam->fresh());

        $exam = $exam->fresh();
        $this->actingAs($ctx['coord']);
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
}
