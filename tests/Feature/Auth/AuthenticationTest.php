<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(__('Index number'), false);
    }

    public function test_students_can_complete_otp_login_flow(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/001',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'index_number' => 'BCS/2099/001',
        ])
            ->assertRedirect(route('login.otp', absolute: false));

        $this->post('/login/otp', ['otp' => '123456'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_index_number_is_rejected(): void
    {
        $this->post('/login', [
            'index_number' => 'DOES/NOT/EXIST',
        ])
            ->assertSessionHasErrors('index_number');

        $this->assertGuest();
    }

    public function test_invalid_otp_is_rejected(): void
    {
        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/002',
            'is_active' => true,
        ]);

        $this->post('/login', ['index_number' => 'BCS/2099/002']);
        $this->post('/login/otp', ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_staff_login_rejects_student_accounts(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'student@example.com',
            'is_active' => true,
        ]);

        $this->post('/staff/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_staff_can_authenticate_with_password(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
            'is_active' => true,
        ]);

        $this->post('/staff/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
