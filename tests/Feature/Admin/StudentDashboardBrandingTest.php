<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\StudentDashboardBrandingService;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StudentDashboardBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for image optimization tests.');
        }
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => true,
            'university_id' => (int) DB::table('universities')->value('id'),
            'email_verified_at' => now(),
        ]);
    }

    private function limitedAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => false,
            'university_id' => (int) DB::table('universities')->value('id'),
            'email_verified_at' => now(),
        ]);
    }

    public function test_super_admin_can_upload_student_dashboard_banner(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $super = $this->superAdmin();
        $customPath = public_path(StudentDashboardBrandingService::CUSTOM_RELATIVE_PATH);

        if (File::isFile($customPath)) {
            File::delete($customPath);
        }

        $this->actingAs($super)
            ->post(route('admin.settings.student-dashboard-banner.update', absolute: false), [
                'banner_image' => UploadedFile::fake()->image('banner-source.png', 1600, 900),
            ])
            ->assertRedirect(route('admin.settings.index', absolute: false))
            ->assertSessionHas('status');

        $this->assertFileExists($customPath);
        $this->assertLessThan(200000, filesize($customPath));

        $branding = app(StudentDashboardBrandingService::class);
        $this->assertTrue($branding->hasCustomBanner());
        $this->assertStringContainsString('images/branding/quizsnap-student-dashboard-profile-banner.jpg', $branding->bannerUrl());

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->get(route('dashboard', absolute: false))
            ->assertOk()
            ->assertSee('images/branding/quizsnap-student-dashboard-profile-banner.jpg', false);
    }

    public function test_limited_admin_cannot_upload_student_dashboard_banner(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $this->actingAs($this->limitedAdmin())
            ->post(route('admin.settings.student-dashboard-banner.update', absolute: false), [
                'banner_image' => UploadedFile::fake()->image('banner.png', 800, 450),
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_reset_banner_to_default(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $super = $this->superAdmin();
        $branding = app(StudentDashboardBrandingService::class);
        $branding->storeCustomBanner(UploadedFile::fake()->image('banner.jpg', 1200, 675), $super);

        $this->actingAs($super)
            ->post(route('admin.settings.student-dashboard-banner.update', absolute: false), [
                'remove_banner' => '1',
            ])
            ->assertRedirect(route('admin.settings.index', absolute: false));

        $this->assertFalse($branding->hasCustomBanner());
        $this->assertSame('', app(SystemSettingsService::class)->get(StudentDashboardBrandingService::SETTING_KEY));
    }

    public function test_settings_page_shows_banner_controls_for_super_admin_only(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->assertSee('Student dashboard branding', false)
            ->assertSee('name="banner_image"', false);

        $this->actingAs($this->limitedAdmin())
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->assertDontSee('Student dashboard branding', false);
    }
}
