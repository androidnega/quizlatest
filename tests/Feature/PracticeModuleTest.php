<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_practice_routes_forbidden_when_disabled(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)->get(route('student.practice.index'))->assertForbidden();
        $this->actingAs($student)->get(route('student.practice.revision'))->assertOk();
    }

    public function test_student_can_view_practice_hub_when_master_toggle_enabled(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_student_practice_quizzes', '1', $admin);

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)->get(route('student.practice.index'))
            ->assertRedirect(route('student.practice.revision'));

        $this->actingAs($student)->get(route('student.practice.revision'))->assertOk();
    }

    public function test_examiner_material_routes_forbidden_when_uploads_disabled(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $dept = Department::query()->where('code', 'CS')->firstOrFail();
        $course = Course::query()->create([
            'university_id' => $coord->university_id,
            'department_id' => $dept->id,
            'code' => 'PRAC101',
            'title' => 'Practice test course',
            'credit_hours' => 3,
            'is_active' => true,
        ]);

        app(SystemSettingsService::class)->set('enable_student_practice_quizzes', '1', User::query()->where('role', 'admin')->firstOrFail());

        $this->actingAs($coord)->get(route('examiner.courses.materials.index', $course))->assertForbidden();
    }
}
