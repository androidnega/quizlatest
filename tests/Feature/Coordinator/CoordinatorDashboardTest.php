<?php

namespace Tests\Feature\Coordinator;

use App\Models\AcademicResetSnapshot;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoordinatorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    private function csDepartmentId(): int
    {
        return (int) DB::table('departments')->where('code', 'CS')->value('id');
    }

    private function programId(): int
    {
        return (int) DB::table('programs')->where('code', 'BCS')->value('id');
    }

    private function universityId(): int
    {
        return (int) $this->coordinator()->university_id;
    }

    private function level100Id(): int
    {
        return (int) DB::table('levels')->where('code', '100')->value('id');
    }

    private function metricText(string $html, string $metric): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $node = $xpath->query('//*[@data-metric="'.$metric.'"]')->item(0);
        $this->assertNotNull($node, 'Missing data-metric="'.$metric.'"');

        return trim($node->textContent);
    }

    public function test_coordinator_dashboard_loads(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $this->actingAs($this->coordinator())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Dashboard'), false)
            ->assertSee(__('Overview'), false);
    }

    public function test_student_counts_are_department_scoped(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $uniId = $this->universityId();
        $now = now();

        $otherFacultyId = DB::table('faculties')->insertGetId([
            'university_id' => $uniId,
            'name' => 'Other Faculty',
            'code' => 'OF',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $otherDeptId = DB::table('departments')->insertGetId([
            'university_id' => $uniId,
            'faculty_id' => $otherFacultyId,
            'name' => 'Other Department',
            'code' => 'OT',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $otherProgramId = DB::table('programs')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $otherDeptId,
            'name' => 'Other Program',
            'code' => 'OTH',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->insert([
            'university_id' => $uniId,
            'program_id' => $otherProgramId,
            'level_id' => $this->level100Id(),
            'class_id' => null,
            'name' => 'Out Of Scope Student',
            'email' => 'out.of.scope@university.edu',
            'index_number' => 'OTH/2026/001',
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => $now,
            'student_onboarded_at' => $now,
            'password' => $coord->password,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $html = $this->actingAs($coord)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame('5', $this->metricText($html, 'total-students'));
        $this->assertStringNotContainsString('out.of.scope@university.edu', $html);
    }

    public function test_students_without_class_count_matches_seed(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $html = $this->actingAs($this->coordinator())->get(route('dashboard'))->getContent();
        $this->assertSame('5', $this->metricText($html, 'students-without-class'));
    }

    public function test_active_programs_and_courses_counts(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $deptId = $this->csDepartmentId();
        $now = now();

        DB::table('courses')->insert([
            'university_id' => $this->universityId(),
            'department_id' => $deptId,
            'code' => 'CS101',
            'title' => 'Intro CS',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('courses')->insert([
            'university_id' => $this->universityId(),
            'department_id' => $deptId,
            'code' => 'CS102',
            'title' => 'Archived CS',
            'credit_hours' => 3,
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $html = $this->actingAs($coord)->get(route('dashboard'))->getContent();
        $this->assertSame('1', $this->metricText($html, 'active-courses'));
        $programsMetric = preg_replace('/\s+/u', ' ', trim($this->metricText($html, 'active-programs')));
        $this->assertStringContainsString('/ 2', $programsMetric, 'Program total in scope should be 2 for seeded CS department');
        $this->assertMatchesRegularExpression('/^\d+\s*\/\s*2$/', $programsMetric, 'Active programs shown as count / total');
    }

    public function test_active_classes_respects_academic_year(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $uniId = $this->universityId();
        $ayId = (int) AcademicYear::activeForUniversity($uniId)?->id;
        $this->assertGreaterThan(0, $ayId);

        $otherYearId = (int) DB::table('academic_years')->insertGetId([
            'university_id' => $uniId,
            'name' => '2099 Test Year',
            'start_date' => '2099-01-01',
            'end_date' => '2099-12-31',
            'status' => AcademicYear::STATUS_CLOSED,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertNotSame($ayId, $otherYearId);

        Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $this->programId(),
            'level_id' => $this->level100Id(),
            'name' => 'YearScopedClass',
            'section' => 'A',
            'academic_year' => '2099',
            'academic_year_id' => $otherYearId,
            'is_active' => true,
        ]);

        $inScope = Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $this->programId(),
            'level_id' => $this->level100Id(),
            'name' => 'InScopeClass',
            'section' => 'B',
            'academic_year' => '2026',
            'academic_year_id' => $ayId,
            'is_active' => true,
        ]);

        $html = $this->actingAs($coord)->get(route('dashboard'))->getContent();
        $this->assertSame('1', $this->metricText($html, 'active-classes'));
        $this->assertStringContainsString($inScope->name, $html);
    }

    public function test_assigned_examiners_distinct_count(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $deptId = $this->csDepartmentId();
        $now = now();

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $this->universityId(),
            'department_id' => $deptId,
            'code' => 'CS201',
            'title' => 'Data Structures',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $examinerA = DB::table('users')->insertGetId([
            'university_id' => $this->universityId(),
            'name' => 'Examiner A',
            'email' => 'examiner-a-dash@university.edu',
            'index_number' => null,
            'role' => 'examiner',
            'is_active' => true,
            'email_verified_at' => $now,
            'password' => $coord->password,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $examinerB = DB::table('users')->insertGetId([
            'university_id' => $this->universityId(),
            'name' => 'Examiner B',
            'email' => 'examiner-b-dash@university.edu',
            'index_number' => null,
            'role' => 'examiner',
            'is_active' => true,
            'email_verified_at' => $now,
            'password' => $coord->password,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('examiner_course_assignments')->insert([
            [
                'course_id' => $courseId,
                'examiner_user_id' => $examinerA,
                'assigned_by' => $coord->id,
                'is_active' => true,
                'permissions' => json_encode([]),
                'starts_at' => null,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'course_id' => $courseId,
                'examiner_user_id' => $examinerB,
                'assigned_by' => $coord->id,
                'is_active' => true,
                'permissions' => json_encode([]),
                'starts_at' => null,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $html = $this->actingAs($coord)->get(route('dashboard'))->getContent();
        $this->assertSame('2', $this->metricText($html, 'assigned-examiners'));
    }

    public function test_quick_action_links_render(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $this->actingAs($this->coordinator())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('coordinator.classes.index'), false)
            ->assertSee(route('coordinator.classes.create'), false)
            ->assertSee(route('coordinator.courses.index'), false)
            ->assertSee(route('coordinator.courses.assign.edit'), false)
            ->assertSee(route('coordinator.academic-reset.index'), false);
    }

    public function test_recent_academic_reset_snapshots_section(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $now = now();

        AcademicResetSnapshot::query()->create([
            'department_id' => $this->csDepartmentId(),
            'academic_year_id' => AcademicYear::activeForUniversity($this->universityId())?->id,
            'initiated_by' => $coord->id,
            'reset_type' => 'complete',
            'payload' => [],
            'summary' => null,
            'applied_at' => null,
        ]);

        $this->actingAs($coord)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Recent academic reset snapshots'), false)
            ->assertSee('complete', false);
    }
}
