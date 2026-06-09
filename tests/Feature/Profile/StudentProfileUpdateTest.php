<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_update_ignores_locked_fields(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $student = User::query()->where('role', 'student')->firstOrFail();
        $originalIndex = $student->index_number;

        $this->actingAs($student)
            ->patch(route('profile.update'), [
                'name' => 'Should Not Change Name',
                'email' => null,
                'phone' => '+233248887766',
                'index_number' => 'SHOULD-NOT-CHANGE',
                'program_id' => 999999,
                'class_id' => 999999,
                'university_id' => 999999,
                'role' => 'admin',
            ])
            ->assertRedirect(route('profile.edit'));

        $student->refresh();
        $this->assertSame('Akua Serwaa', $student->name);
        $this->assertSame('+233248887766', $student->phone);
        $this->assertSame($originalIndex, $student->index_number);
        $this->assertSame('student', $student->role);
    }

    public function test_student_can_upload_profile_photo(): void
    {
        $this->seed(InitialSetupSeeder::class);
        Storage::fake('local');

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->patch(route('profile.update'), [
                'phone' => $student->phone,
                'profile_photo' => UploadedFile::fake()->image('portrait.jpg', 320, 320)->size(200),
            ])
            ->assertRedirect(route('profile.edit'));

        $student->refresh();
        $this->assertNotNull($student->face_image_path);
        $this->assertStringEndsWith('profile.jpg', (string) $student->face_image_path);
        Storage::disk('local')->assertExists((string) $student->face_image_path);

        $this->actingAs($student)
            ->get(route('profile.face-image'))
            ->assertOk();
    }

    public function test_student_profile_page_includes_crop_ui(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('profile.edit', absolute: false))
            ->assertOk()
            ->assertSee('id="student-profile-photo-crop"', false)
            ->assertSee('data-crop-modal', false)
            ->assertSee('Adjust your photo', false);
    }

    public function test_student_profile_photo_rejects_files_over_250_kb(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->patch(route('profile.update'), [
                'phone' => $student->phone,
                'profile_photo' => UploadedFile::fake()->image('large.jpg', 800, 800)->size(260),
            ])
            ->assertSessionHasErrors('profile_photo');
    }
}
