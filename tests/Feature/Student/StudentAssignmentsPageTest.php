<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAssignmentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_assignments_index(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.assignments.index'))
            ->assertOk()
            ->assertSee('qs-std-page-head__title', false)
            ->assertSee(__('Assignments'), false);
    }
}
