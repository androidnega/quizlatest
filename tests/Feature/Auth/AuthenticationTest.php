<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(__('Index number or phone'), false);
        $response->assertSee(__('Password'), false);
        $response->assertSee(__('First-time sign-in'), false);
    }

    public function test_first_time_flow_sends_otp_and_redirects_to_onboarding_after_verify(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/001',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => null,
            'email_verified_at' => null,
        ]);

        $this->post('/login/first-time', [
            'index_number' => 'BCS/2099/001',
        ])
            ->assertRedirect(route('login.otp', absolute: false));

        $this->post('/login/otp', ['otp' => '123456'])
            ->assertRedirect(route('student.onboarding', absolute: false));

        $this->assertGuest();
        $this->assertTrue(session()->has('student_onboarding_user_id'));
        $this->assertSame($user->id, session('student_onboarding_user_id'));
    }

    public function test_first_time_without_saved_phone_redirects_to_phone_step(): void
    {
        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/099',
            'phone' => null,
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->post('/login/first-time', [
            'index_number' => 'BCS/2099/099',
        ])
            ->assertRedirect(route('login.first-time.phone', absolute: false));
    }

    public function test_first_time_phone_step_then_otp_attaches_phone(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/088',
            'phone' => null,
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->post('/login/first-time', ['index_number' => 'BCS/2099/088']);
        $this->post('/login/first-time/phone', ['phone' => '+233241112233'])
            ->assertRedirect(route('login.otp', absolute: false));

        $this->post('/login/otp', ['otp' => '123456'])
            ->assertRedirect(route('student.onboarding', absolute: false));

        $user->refresh();
        $this->assertSame('233241112233', $user->phone);
    }

    public function test_returning_students_can_sign_in_with_password(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/010',
            'email' => 'returning@example.com',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'identifier' => 'BCS/2099/010',
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_returning_students_can_sign_in_with_phone(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/011',
            'phone' => '233241112233',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'identifier' => '+233241112233',
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_index_number_is_rejected_on_first_time(): void
    {
        $this->post('/login/first-time', [
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
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->post('/login/first-time', ['index_number' => 'BCS/2099/002']);
        $this->post('/login/otp', ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_password_login_rejects_accounts_pending_onboarding(): void
    {
        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/003',
            'is_active' => true,
            'student_onboarded_at' => null,
            'password' => 'temporary-password-123',
        ]);

        $this->post('/login', [
            'identifier' => 'BCS/2099/003',
            'password' => 'temporary-password-123',
        ])
            ->assertSessionHasErrors('identifier');

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

    public function test_student_password_reset_with_phone_otp(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/050',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => now(),
            'last_student_password_reset_at' => now()->subMonths(4),
        ]);

        $this->post('/student/forgot-password', ['identifier' => 'BCS/2099/050'])
            ->assertRedirect(route('student.password-reset.otp', absolute: false));

        $this->post('/student/forgot-password/otp', ['otp' => '654321'])
            ->assertRedirect(route('student.password-reset.form', absolute: false));

        $this->post('/student/reset-password', [
            'password' => 'NewSecurePass9!',
            'password_confirmation' => 'NewSecurePass9!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('NewSecurePass9!', $user->fresh()->password));
    }
}
