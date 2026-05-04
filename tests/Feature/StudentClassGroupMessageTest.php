<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentClassGroupMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_without_class_sees_class_rep_message_on_dashboard(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $student = User::query()->where('role', 'student')->firstOrFail();
        DB::table('users')->where('id', $student->id)->update(['class_id' => null]);

        $this->actingAs(User::query()->findOrFail($student->id))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('student_ui.class_group_not_assigned'), false);
    }
}
