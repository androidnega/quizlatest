<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_includes_profile_menu_and_post_logout(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $admin = User::query()->where('email', 'admin')->firstOrFail();

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
        $this->assertStringContainsString(route('admin.settings.index'), $html);
        $this->assertStringContainsString('method="POST" action="'.e(route('logout')).'"', $html);
    }

    public function test_admin_legacy_dashboard_admin_path_redirects_to_main_dashboard(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $admin = User::query()->where('email', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/dashboard/admin')
            ->assertRedirect(route('dashboard'));
    }

    public function test_coordinator_dashboard_profile_menu_omits_admin_settings(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();

        $html = $this->actingAs($coord)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
        $this->assertStringNotContainsString(route('admin.settings.index'), $html);
    }

    public function test_student_dashboard_has_profile_menu_without_sidebar_logout_form(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $student = User::query()->where('role', 'student')->firstOrFail();

        $html = $this->actingAs($student)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertSame(1, substr_count($html, 'method="POST" action="'.e(route('logout')).'"'), 'Logout should appear once (profile menu POST form only).');
    }
}
