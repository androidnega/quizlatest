<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
