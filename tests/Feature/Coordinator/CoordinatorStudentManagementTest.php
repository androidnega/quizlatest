<?php

namespace Tests\Feature\Coordinator;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoordinatorStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    private function makeTestClassroom(User $coord): Classroom
    {
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');
        $uniId = (int) $coord->university_id;
        $year = AcademicYear::activeForUniversity($uniId);

        return Classroom::query()->create([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'CS-Manage',
            'section' => 'B',
            'academic_year' => $year?->name,
            'academic_year_id' => $year?->id,
            'is_active' => true,
        ]);
    }

    public function test_legacy_assign_class_url_redirects_to_student_edit(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($coord)
            ->get(route('coordinator.students.assign-class.edit', $student))
            ->assertRedirect(route('coordinator.students.edit', $student));
    }

    public function test_coordinator_can_update_student_in_scope(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $classroom = $this->makeTestClassroom($coord);
        $student = User::query()->where('role', 'student')->orderBy('id')->firstOrFail();

        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $this->actingAs($coord)
            ->put(route('coordinator.students.update', $student), [
                'name' => 'Managed Student Name',
                'index_number' => (string) $student->index_number,
                'phone' => '+233241112233',
                'program_id' => $programId,
                'level_id' => $levelId,
                'class_id' => $classroom->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('coordinator.students.edit', $student));

        $student->refresh();
        $this->assertSame('Managed Student Name', $student->name);
        $this->assertSame('233241112233', $student->phone);
        $this->assertSame($classroom->id, $student->class_id);
        $this->assertTrue($student->is_active);
    }

    public function test_coordinator_can_set_student_password(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $classroom = $this->makeTestClassroom($coord);
        $student = User::query()->where('role', 'student')->orderBy('id')->firstOrFail();

        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $oldHash = (string) $student->password;

        $this->actingAs($coord)
            ->put(route('coordinator.students.update', $student), [
                'name' => $student->name,
                'index_number' => (string) $student->index_number,
                'phone' => '',
                'program_id' => $programId,
                'level_id' => $levelId,
                'class_id' => $classroom->id,
                'is_active' => '1',
                'generate_password' => '1',
            ])
            ->assertRedirect(route('coordinator.students.edit', $student))
            ->assertSessionHas('generated_password');

        $student->refresh();
        $this->assertNotSame($oldHash, (string) $student->password);
        $this->assertNotNull($student->last_student_password_reset_at);
    }

    public function test_coordinator_cannot_edit_student_outside_assigned_departments(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $uniId = (int) $coord->university_id;

        $facultyId = DB::table('faculties')->insertGetId([
            'university_id' => $uniId,
            'name' => 'Faculty of Science',
            'code' => 'FS',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherDeptId = DB::table('departments')->insertGetId([
            'university_id' => $uniId,
            'faculty_id' => $facultyId,
            'name' => 'Department of Physics',
            'code' => 'PHY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $physicsProgramId = DB::table('programs')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $otherDeptId,
            'name' => 'BSc Physics',
            'code' => 'PHY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = (int) DB::table('levels')->where('university_id', $uniId)->where('code', '100')->value('id');

        $outsider = User::factory()->create([
            'university_id' => $uniId,
            'program_id' => $physicsProgramId,
            'level_id' => $levelId,
            'class_id' => null,
            'role' => 'student',
            'is_active' => false,
            'index_number' => 'PHY/2099/001',
        ]);

        $this->actingAs($coord)
            ->get(route('coordinator.students.edit', $outsider))
            ->assertForbidden();
    }

    public function test_coordinator_can_delete_student_with_no_assignment_or_activity_data(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $student = User::query()->where('role', 'student')->orderBy('id')->firstOrFail();
        $student->update(['class_id' => null, 'is_active' => false]);

        $this->actingAs($coord)
            ->delete(route('coordinator.students.destroy', $student))
            ->assertRedirect(route('coordinator.students.index'));

        $this->assertNull(User::query()->find($student->id));
    }

    public function test_coordinator_cannot_delete_student_with_class_assignment(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $student = User::query()->where('role', 'student')->orderBy('id')->firstOrFail();
        $classroom = $this->makeTestClassroom($coord);
        $student->update(['class_id' => $classroom->id, 'is_active' => true]);

        $this->actingAs($coord)
            ->delete(route('coordinator.students.destroy', $student))
            ->assertSessionHasErrors('student_delete');

        $this->assertNotNull(User::query()->find($student->id));
    }

    public function test_coordinator_can_export_and_import_students_json(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();

        $export = $this->actingAs($coord)->get(route('coordinator.students.export-json'));
        $export->assertOk();
        $export->assertHeader('content-type', 'application/json; charset=UTF-8');

        $json = json_encode([
            'students' => [[
                'name' => 'Json Import Student',
                'index_number' => 'BCS/2099/777',
                'phone' => '+233241234567',
                'program_code' => 'BCS',
                'level_code' => '100',
                'class_name' => null,
                'is_active' => false,
            ]],
        ], JSON_PRETTY_PRINT);

        $file = UploadedFile::fake()->createWithContent('students.json', (string) $json);

        $this->actingAs($coord)
            ->post(route('coordinator.students.import-json'), ['json_file' => $file])
            ->assertRedirect(route('coordinator.students.index'));

        $created = User::query()->where('role', 'student')->where('index_number', 'BCS/2099/777')->first();
        $this->assertNotNull($created);
        $this->assertSame('Json Import Student', $created->name);
        $this->assertNull($created->email);
    }

    public function test_bulk_delete_removes_only_safe_students(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $classroom = $this->makeTestClassroom($coord);

        $safe = User::query()->where('role', 'student')->orderBy('id')->firstOrFail();
        $blocked = User::query()->where('role', 'student')->whereKeyNot($safe->id)->orderBy('id')->firstOrFail();

        $safe->update(['class_id' => null, 'is_active' => false]);
        $blocked->update(['class_id' => $classroom->id, 'is_active' => true]);

        $this->actingAs($coord)
            ->post(route('coordinator.students.bulk-status'), [
                'student_ids' => [$safe->id, $blocked->id],
                'action' => 'delete',
            ])
            ->assertRedirect(route('coordinator.students.index'));

        $this->assertNull(User::query()->find($safe->id));
        $this->assertNotNull(User::query()->find($blocked->id));
    }
}
