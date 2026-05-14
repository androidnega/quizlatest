<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentAnalyticsReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_examiner_can_view_own_assessment_analytics(): void
    {
        $ctx = $this->makeExaminerCourseExam();
        $this->actingAs($ctx['examiner']);

        $res = $this->get(route('examiner.exams.analytics.show', ['exam' => $ctx['exam']]));
        $res->assertOk();
        $res->assertSee(__('Assessment analytics'), false);
    }

    public function test_examiner_cannot_view_another_examiners_assessment_analytics(): void
    {
        $ctx = $this->makeExaminerCourseExam();
        $other = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $ctx['examiner']->university_id,
            'email' => 'other.examiner.'.Str::random(8).'@test.edu',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        DB::table('examiner_course_assignments')->insert([
            'course_id' => $ctx['course_id'],
            'examiner_user_id' => $other->id,
            'assigned_by' => null,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($other);
        $this->get(route('examiner.exams.analytics.show', ['exam' => $ctx['exam']]))->assertForbidden();
    }

    public function test_student_cannot_access_examiner_analytics_routes(): void
    {
        $ctx = $this->makeExaminerCourseExam();
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student);
        $this->get(route('examiner.exams.analytics.show', ['exam' => $ctx['exam']]))->assertForbidden();
    }

    public function test_question_performance_tab_loads(): void
    {
        $ctx = $this->makeExaminerCourseExam();
        $this->actingAs($ctx['examiner']);

        $this->get(route('examiner.exams.analytics.show', ['exam' => $ctx['exam'], 'tab' => 'questions']))
            ->assertOk()
            ->assertSee(__('Questions'), false);
    }

    public function test_student_performance_csv_export_is_authorized(): void
    {
        $ctx = $this->makeExaminerCourseExam();
        $this->actingAs($ctx['examiner']);

        $this->get(route('examiner.exams.analytics.export.students', ['exam' => $ctx['exam']]))
            ->assertOk();

        $other = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $ctx['examiner']->university_id,
            'email' => 'csv.block.'.Str::random(8).'@test.edu',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        DB::table('examiner_course_assignments')->insert([
            'course_id' => $ctx['course_id'],
            'examiner_user_id' => $other->id,
            'assigned_by' => null,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($other);
        $this->get(route('examiner.exams.analytics.export.students', ['exam' => $ctx['exam']]))->assertForbidden();
    }

    public function test_proctoring_analytics_tab_loads(): void
    {
        $ctx = $this->makeExaminerCourseExam();
        $this->actingAs($ctx['examiner']);

        $this->get(route('examiner.exams.analytics.show', ['exam' => $ctx['exam'], 'tab' => 'proctoring']))
            ->assertOk()
            ->assertSee(__('Proctoring'), false);
    }

    public function test_coordinator_reporting_is_scoped_and_loads(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $this->actingAs($coord);

        $this->get(route('coordinator.reporting.index'))->assertOk()->assertSee(__('Reporting'), false);
        $this->get(route('coordinator.reporting.export.class-completion'))->assertOk();
    }

    public function test_admin_reporting_loads_and_csv_export_works(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $admin = User::query()->where('email', 'admin')->firstOrFail();
        $this->actingAs($admin);

        $this->get(route('admin.system-reporting.index'))->assertOk()->assertSee(__('System reporting'), false);
        $this->get(route('admin.system-reporting.export.system-summary'))->assertOk();
    }

    /**
     * @return array{examiner: User, exam: Quiz, course_id: int}
     */
    private function makeExaminerCourseExam(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'analytics.owner.'.Str::random(8).'@test.edu',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-AN-'.Str::upper(Str::random(4)),
            'title' => 'Analytics Test Course',
            'credit_hours' => 3,
            'is_active' => true,
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

        $yearId = DB::table('academic_years')->value('id');

        $exam = Quiz::query()->create([
            'university_id' => $uniId,
            'academic_year_id' => $yearId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Analytics Quiz',
            'description' => null,
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 10,
            'proctoring_settings' => [],
            'start_time' => null,
            'end_time' => null,
        ]);

        return ['examiner' => $examiner, 'exam' => $exam, 'course_id' => $courseId];
    }
}
