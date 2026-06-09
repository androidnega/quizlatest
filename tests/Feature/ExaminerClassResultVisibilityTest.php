<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExaminerClassResultVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     coordinator: User,
     *     examinerA: User,
     *     examinerB: User,
     *     courseId: int,
     *     class: Classroom,
     *     quizA: Quiz,
     *     quizB: Quiz,
     *     student: User
     * }
     */
    private function seedContext(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');
        $coordinator = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $admin = User::query()->where('email', 'admin')->firstOrFail();

        $examinerA = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner-class-a-'.Str::lower(Str::random(8)).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $examinerB = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner-class-b-'.Str::lower(Str::random(8)).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CRS401',
            'title' => 'Class Results Visibility',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$examinerA, $examinerB] as $examiner) {
            DB::table('examiner_course_assignments')->insert([
                'course_id' => $courseId,
                'examiner_user_id' => $examiner->id,
                'assigned_by' => $admin->id,
                'is_active' => true,
                'permissions' => null,
                'starts_at' => null,
                'ends_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $classroom = Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'RESULT-CLASS',
            'section' => null,
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
        DB::table('class_course')->insert([
            'class_id' => $classroom->id,
            'course_id' => $courseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::query()->where('role', 'student')->firstOrFail();
        $student->update(['class_id' => $classroom->id]);

        $quizA = Quiz::query()->create([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examinerA->id,
            'title' => 'Exam A',
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
        ]);
        $quizB = Quiz::query()->create([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examinerB->id,
            'title' => 'Exam B',
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
        ]);

        Result::query()->create([
            'user_id' => $student->id,
            'quiz_id' => $quizA->id,
            'score' => 78,
            'time_taken' => 1200,
            'status' => 'graded',
            'exam_status' => 'submitted',
            'submitted_at' => now(),
        ]);
        Result::query()->create([
            'user_id' => $student->id,
            'quiz_id' => $quizB->id,
            'score' => 55,
            'time_taken' => 1300,
            'status' => 'held',
            'exam_status' => 'submitted_held',
            'submitted_at' => now(),
        ]);

        return [
            'coordinator' => $coordinator,
            'examinerA' => $examinerA,
            'examinerB' => $examinerB,
            'courseId' => $courseId,
            'class' => $classroom,
            'quizA' => $quizA,
            'quizB' => $quizB,
            'student' => $student,
        ];
    }

    public function test_examiner_course_page_shows_course_only_and_nav_links(): void
    {
        $ctx = $this->seedContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.courses.show', $ctx['courseId']))
            ->assertOk()
            ->assertSee('Class Results Visibility', false)
            ->assertSee('CRS401', false)
            ->assertSee('Assessments for this course', false)
            ->assertSee('Class groups', false)
            ->assertDontSee('Linked classes', false);
    }

    public function test_examiner_can_open_linked_class_card_and_see_only_own_quiz_records(): void
    {
        $ctx = $this->seedContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.courses.classes.show', [$ctx['courseId'], $ctx['class']]))
            ->assertOk()
            ->assertSee('Exam A', false)
            ->assertDontSee('Exam B', false)
            ->assertSee('Open sessions', false);
    }

    public function test_examiner_cannot_edit_class_and_coordinator_management_remains_separate(): void
    {
        $ctx = $this->seedContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('coordinator.classes.edit', $ctx['class']))
            ->assertForbidden();

        $this->actingAs($ctx['coordinator'])
            ->get(route('coordinator.classes.edit', $ctx['class']))
            ->assertOk();
    }

    public function test_examiner_cannot_see_other_examiner_results_on_same_course(): void
    {
        $ctx = $this->seedContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.exams.classes.summary', $ctx['quizB']))
            ->assertForbidden();
    }

    public function test_examiner_can_view_class_result_summary_for_own_exam(): void
    {
        $ctx = $this->seedContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.exams.classes.summary', $ctx['quizA']))
            ->assertOk()
            ->assertSee('RESULT-CLASS', false)
            ->assertSee('1', false);
    }

    public function test_examiner_classes_hub_lists_linked_classes(): void
    {
        $ctx = $this->seedContext();

        $this->assertTrue(Gate::forUser($ctx['examinerA'])->allows('view', $ctx['class']));
        $this->assertFalse(Gate::forUser($ctx['examinerA'])->allows('update', $ctx['class']));
        $this->assertFalse(Gate::forUser($ctx['examinerA'])->allows('create', Classroom::class));

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.teaching-classes.index'))
            ->assertOk()
            ->assertSee('RESULT-CLASS', false);
    }

    public function test_examiner_teaching_class_show_includes_create_assessment_and_roster_template(): void
    {
        $ctx = $this->seedContext();

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.teaching-classes.show', $ctx['class']))
            ->assertOk()
            ->assertSee('Group actions', false)
            ->assertSee('Create assessment', false)
            ->assertSee('Your courses', false)
            ->assertSee('Assessments', false)
            ->assertSee('Student index list', false);

        $this->actingAs($ctx['examinerA'])
            ->get(route('examiner.teaching-classes.students.template', $ctx['class']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_examiner_can_clear_attempt_to_allow_retake(): void
    {
        $ctx = $this->seedContext();

        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['class']->id,
            'exam_id' => $ctx['quizA']->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
        ]);

        $this->actingAs($ctx['examinerA'])
            ->from(route('examiner.exam-sessions.show', [
                'exam' => $session->exam,
                'examSession' => $session,
            ]))
            ->post(route('examiner.exam-sessions.invalidate-for-retake', $session))
            ->assertRedirect();

        $this->assertDatabaseMissing('exam_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('results', [
            'user_id' => $ctx['student']->id,
            'quiz_id' => $ctx['quizA']->id,
        ]);
    }
}
