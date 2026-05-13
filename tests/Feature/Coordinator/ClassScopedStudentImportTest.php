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

class ClassScopedStudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    public function test_class_show_and_upload_pages_load(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $classroom = $this->makeTestClassroom($coord);

        $this->actingAs($coord)
            ->get(route('coordinator.classes.show', $classroom))
            ->assertOk()
            ->assertSee('CS-Alpha', false);

        $this->actingAs($coord)
            ->get(route('coordinator.classes.students.upload', $classroom))
            ->assertOk();
    }

    public function test_class_scoped_csv_import_assigns_students_to_class_and_redirects_back(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $classroom = $this->makeTestClassroom($coord);

        $csv = "index_number,name,phone\nCS-CLASS-TEST-001,Class Upload User,+233241112200\n";
        $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

        $this->actingAs($coord)
            ->post(route('coordinator.classes.students.preview', $classroom), [
                'csv_file' => $file,
                'map_index_number' => 'index_number',
                'map_name' => 'name',
                'map_phone' => 'phone',
                'year' => (string) now()->year,
            ])
            ->assertOk()
            ->assertViewHas('validCount', 1)
            ->assertViewHas('lockedClassroom');

        $this->actingAs($coord)
            ->post(route('coordinator.students.import'))
            ->assertRedirect(route('coordinator.classes.show', $classroom));

        $student = User::query()->where('index_number', 'CS-CLASS-TEST-001')->first();
        $this->assertNotNull($student);
        $this->assertSame('student', $student->role);
        $this->assertSame($classroom->id, $student->class_id);
        $this->assertTrue($student->is_active);
    }

    public function test_class_csv_template_downloads(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = $this->coordinator();
        $classroom = $this->makeTestClassroom($coord);

        $this->actingAs($coord)
            ->get(route('coordinator.classes.students.template', $classroom))
            ->assertOk();
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
            'name' => 'CS-Alpha',
            'section' => 'A',
            'academic_year' => $year?->name,
            'academic_year_id' => $year?->id,
            'is_active' => true,
        ]);
    }
}
