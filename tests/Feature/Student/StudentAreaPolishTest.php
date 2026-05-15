<?php

namespace Tests\Feature\Student;

use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAreaPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_help_page_loads(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.help'))
            ->assertOk()
            ->assertSee(__('Starting an assessment'), false);
    }

    public function test_student_notifications_page_loads(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.notifications.index'))
            ->assertOk()
            ->assertSee(__('Notifications'), false);
    }

    public function test_student_profile_shows_read_only_academic_section(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(__('Academic placement'), false)
            ->assertSee(__('If any academic information is wrong'), false);
    }

    public function test_student_can_update_phone_only(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();
        $beforeName = $student->name;

        $this->actingAs($student)
            ->patch(route('profile.update'), [
                'phone' => '+15550001122',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $student->refresh();
        $this->assertSame($beforeName, $student->name);
        $this->assertSame('+15550001122', $student->phone);
    }

    public function test_student_patch_without_phone_validation_still_ok(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->patch(route('profile.update'), [
                'phone' => null,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_course_materials_index_forbidden_when_browse_disabled(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $system = app(SystemSettingsService::class);
        $system->set('enable_student_practice_quizzes', '0', $admin);
        $system->set('enable_course_material_uploads', '0', $admin);

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.practice.materials.index'))
            ->assertForbidden();
    }

    public function test_student_still_forbidden_from_examiner_analytics(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('examiner.dashboard'))
            ->assertForbidden();
    }
}
