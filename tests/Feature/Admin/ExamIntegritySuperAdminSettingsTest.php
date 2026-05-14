<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamIntegritySuperAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function limitedAdmin(): User
    {
        $uni = (int) DB::table('universities')->value('id');

        return User::factory()->create([
            'name' => 'Limited Admin',
            'email' => 'limited-admin-'.uniqid('', true).'@test.local',
            'role' => 'admin',
            'is_super_admin' => false,
            'university_id' => $uni,
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_super_admin_settings_page_shows_exam_integrity_toggles(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $super = User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => true,
            'university_id' => (int) DB::table('universities')->value('id'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($super)
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->assertSee('name="exam_clipboard_lock"', false)
            ->assertSee('Exam surface integrity', false);
    }

    public function test_non_super_admin_settings_page_hides_exam_integrity_checkboxes(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $limited = $this->limitedAdmin();

        $this->actingAs($limited)
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->assertDontSee('name="exam_clipboard_lock"', false);
    }
}
