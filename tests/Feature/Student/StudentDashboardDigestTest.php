<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentDashboardDigestTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    private function makeClassroom(User $coord): Classroom
    {
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');
        $uniId = (int) $coord->university_id;
        $year = AcademicYear::activeForUniversity($uniId);

        return Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'CS-Digest',
            'section' => 'A',
            'academic_year' => $year?->name,
            'academic_year_id' => $year?->id,
            'is_active' => true,
        ]);
    }

    public function test_student_dashboard_shows_new_course_materials_since_last_visit(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $classroom = $this->makeClassroom($coord);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');

        $course = Course::query()->create([
            'university_id' => $coord->university_id,
            'department_id' => $deptId,
            'code' => 'CS301',
            'title' => 'Data Structures',
            'credit_hours' => 3.0,
            'is_active' => true,
        ]);

        DB::table('class_course')->insert([
            'class_id' => $classroom->id,
            'course_id' => $course->id,
            'academic_year_id' => $classroom->academic_year_id,
            'assigned_by' => $coord->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::query()->where('role', 'student')->firstOrFail();
        $student->forceFill([
            'class_id' => $classroom->id,
            'student_last_dashboard_at' => now()->subDays(2),
        ])->save();

        CourseMaterial::query()->create([
            'course_id' => $course->id,
            'class_id' => null,
            'uploaded_by' => $coord->id,
            'title' => 'Week 5 notes',
            'material_kind' => CourseMaterial::KIND_SUPPLEMENTARY,
            'file_path' => 'courses/'.$course->id.'/week5.pdf',
            'file_type' => 'pdf',
            'status' => CourseMaterial::STATUS_READY,
        ]);

        $html = $this->actingAs($student)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Data Structures', $html);
        $this->assertStringContainsString('1 new file in', $html);
    }

    public function test_policy_notice_dismiss_updates_ack_version(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        config([
            'student-dashboard.policy.version' => 2,
            'student-dashboard.policy.message' => 'Fullscreen policy updated for your institution.',
            'student-dashboard.policy.faq_url' => 'https://example.test/faq',
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fullscreen policy updated', false)
            ->assertSee('https://example.test/faq', false);

        $this->actingAs($student)
            ->post(route('student.dashboard.policy-notice.dismiss'))
            ->assertRedirect(route('dashboard'));

        $student->refresh();
        $this->assertSame(2, (int) $student->policy_notice_ack_version);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Fullscreen policy updated', false);
    }

    public function test_student_dashboard_tip_includes_dismiss_control(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Dismiss tip'), false);
    }
}
