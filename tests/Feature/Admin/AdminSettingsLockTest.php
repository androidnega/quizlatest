<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSettingsLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_lock_redirects_to_settings_index_with_scroll_flash(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.lock', absolute: false), ['key' => 'enable_otp'])
            ->assertRedirect(route('admin.settings.index', absolute: false))
            ->assertSessionHas('scroll_to_setting_lock', 'enable_otp')
            ->assertSessionHas('status', __('Setting locked.'));
    }

    public function test_unlock_redirects_to_settings_index_with_scroll_flash(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.lock', absolute: false), ['key' => 'enable_otp']);

        $this->actingAs($admin)
            ->post(route('admin.settings.unlock', absolute: false), ['key' => 'enable_otp'])
            ->assertRedirect(route('admin.settings.index', absolute: false))
            ->assertSessionHas('scroll_to_setting_lock', 'enable_otp')
            ->assertSessionHas('status', __('Setting unlocked.'));
    }

    public function test_lock_rejects_unknown_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.lock', absolute: false), ['key' => 'not_a_real_setting_key'])
            ->assertSessionHasErrors('key');
    }

    public function test_non_super_admin_cannot_lock_exam_integrity_only_keys(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $uni = (int) DB::table('universities')->value('id');
        $limited = User::factory()->create([
            'role' => 'admin',
            'is_super_admin' => false,
            'university_id' => $uni,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($limited)
            ->post(route('admin.settings.lock', absolute: false), ['key' => 'exam_clipboard_lock'])
            ->assertForbidden();
    }
}
