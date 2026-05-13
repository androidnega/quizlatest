<?php

namespace Tests\Feature\Auth;

use Database\Seeders\InitialSetupSeeder;
use Database\Seeders\OwnerAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_unknown_username_shows_helpful_message(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $this->post(route('staff.login'), [
            'email' => 'definitely-not-a-user',
            'password' => 'admin123',
        ])
            ->assertSessionHasErrors('email')
            ->assertSessionDoesntHaveErrors('password');
    }

    public function test_staff_login_wrong_password_shows_distinct_message(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $this->post(route('staff.login'), [
            'email' => 'admin',
            'password' => 'wrong-password-xyz',
        ])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Wrong password',
            session('errors')->get('email')[0],
        );
    }

    public function test_staff_login_succeeds_for_seeded_admin(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $this->post(route('staff.login'), [
            'email' => 'admin',
            'password' => 'admin123',
        ])
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
