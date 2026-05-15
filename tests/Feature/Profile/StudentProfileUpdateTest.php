<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
