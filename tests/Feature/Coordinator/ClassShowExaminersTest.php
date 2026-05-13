<?php

namespace Tests\Feature\Coordinator;

use App\Models\AcademicYear;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassShowExaminersTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    public function test_class_show_lists_examiners_for_linked_courses_only(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $admin = User::query()->where('email', 'admin')->firstOrFail();
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');
        $year = AcademicYear::activeForUniversity((int) $coord->university_id);

        $classroomId = (int) DB::table('classes')->insertGetId([
            'university_id' => $coord->university_id,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'Examiners Roster Demo',
            'section' => null,
            'academic_year' => $year?->name,
            'academic_year_id' => $year?->id,
            'is_active' => true,
            'accent_color' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $examinerName = 'Dr. Augustine Yeboah';
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'name' => $examinerName,
            'university_id' => $coord->university_id,
            'email' => 'aug.yeboah-'.Str::lower(Str::random(8)).'@examiner.test',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $linkedCourseId = DB::table('courses')->insertGetId([
            'university_id' => $coord->university_id,
            'department_id' => $deptId,
            'code' => 'CSLINK',
            'title' => 'Linked to class',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('courses')->insertGetId([
            'university_id' => $coord->university_id,
            'department_id' => $deptId,
            'code' => 'CSOTHER',
            'title' => 'Not linked',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('class_course')->insert([
            'class_id' => $classroomId,
            'course_id' => $linkedCourseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $linkedCourseId,
            'examiner_user_id' => $examiner->id,
            'assigned_by' => $admin->id,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coord)
            ->get(route('coordinator.classes.show', $classroomId))
            ->assertOk()
            ->assertDontSee(__('Assign matching students'), false)
            ->assertSee(__('Examiners for this class'), false)
            ->assertSee($examinerName, false)
            ->assertSee('CSLINK', false)
            ->assertSee('Linked to class', false)
            ->assertDontSee('CSOTHER', false);
    }
}
