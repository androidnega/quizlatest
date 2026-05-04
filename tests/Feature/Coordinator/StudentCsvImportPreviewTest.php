<?php

namespace Tests\Feature\Coordinator;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StudentCsvImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        return User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
    }

    private function postPreview(string $csvBody): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('students.csv', $csvBody);

        return $this->actingAs($this->coordinator())
            ->post(route('coordinator.students.preview'), [
                'csv_file' => $file,
                'map_index_number' => 'index_number',
                'map_name' => 'name',
                'map_phone' => 'phone',
                'map_email' => 'email',
                'map_program' => 'program',
                'map_level' => 'level',
                'map_class_name' => 'class_name',
                'year' => (string) now()->year,
            ]);
    }

    public function test_index_only_row_import_preview_valid(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $csv = "index_number,name,phone,program,level,class_name\n,,,BCS,100,\n";

        $response = $this->postPreview($csv);
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->viewData('validCount'));
    }

    public function test_duplicate_index_in_file_is_flagged(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $csv = "index_number,name,phone,program,level,class_name\nX/2026/900,Test,,BCS,100,\nX/2026/900,Test2,,BCS,100,\n";

        $response = $this->postPreview($csv);
        $response->assertOk();
        $rows = $response->viewData('previewRows');
        $this->assertNotEmpty($rows[1]['errors']);
    }

    public function test_duplicate_phone_in_file_is_flagged(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $csv = "index_number,name,phone,program,level,class_name\nX/2026/901,A,+233241112233,BCS,100,\nX/2026/902,B,+233241112233,BCS,100,\n";

        $response = $this->postPreview($csv);
        $response->assertOk();
        $rows = $response->viewData('previewRows');
        $this->assertNotEmpty($rows[1]['errors']);
    }

    public function test_duplicate_existing_index_in_university_is_flagged(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $existing = User::query()->where('role', 'student')->value('index_number');
        $this->assertNotNull($existing);

        $csv = "index_number,name,phone,program,level,class_name\n{$existing},Someone,,BCS,100,\n";

        $response = $this->postPreview($csv);
        $response->assertOk();
        $rows = $response->viewData('previewRows');
        $this->assertNotEmpty($rows[0]['errors']);
    }
}
