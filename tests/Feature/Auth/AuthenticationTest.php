<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['role' => 'admin']);
        $settings = app(SystemSettingsService::class);
        $settings->set('enable_sms', '1', $admin);
        $settings->set('arkesel_api_key', 'test-arkesel-api-key-for-ci', $admin);
        $settings->set('arkesel_sender_id', 'QUIZSNAP', $admin);
    }

    public function test_login_screen_shows_only_index_and_continue(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(__('Index number'), false);
        $response->assertSee(__('Continue'), false);
        $response->assertDontSee('type="password"', false);
        $response->assertDontSee(__('First-time sign-in'), false);
        $response->assertDontSee(__('Staff sign in'), false);
        $response->assertDontSee(__('Staff portal'), false);
    }

    public function test_legacy_first_time_login_url_redirects_to_login(): void
    {
        $this->get('/login/first-time')
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_onboarded_student_index_submits_to_password_step(): void
    {
        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/010',
            'is_active' => true,
            'student_onboarded_at' => now(),
        ]);

        $this->post('/login', [
            'index_number' => 'BCS/2099/010',
        ])
            ->assertRedirect(route('login.password', absolute: false));

        $this->get('/login/password')
            ->assertOk()
            ->assertSee('BCS/2099/010', false);
    }

    public function test_returning_student_can_login_with_password_after_index_step(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/010',
            'is_active' => true,
            'student_onboarded_at' => now(),
        ]);

        $this->post('/login', ['index_number' => 'BCS/2099/010'])
            ->assertRedirect(route('login.password', absolute: false));

        $this->post('/login/password', [
            'password' => 'password',
            'remember' => '1',
        ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_after_index_step_fails_cleanly(): void
    {
        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/077',
            'is_active' => true,
            'student_onboarded_at' => now(),
        ]);

        $this->post('/login', ['index_number' => 'BCS/2099/077']);
        $this->post('/login/password', ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_non_onboarded_student_index_redirects_to_otp_flow(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/001',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => null,
            'email_verified_at' => null,
        ]);

        $this->post('/login', [
            'index_number' => 'BCS/2099/001',
        ])
            ->assertRedirect(route('login.otp', absolute: false));

        $this->post('/login/otp', ['otp' => '123456'])
            ->assertRedirect(route('student.onboarding', absolute: false));

        $this->assertGuest();
        $this->assertTrue(session()->has('student_onboarding_user_id'));
        $this->assertSame($user->id, session('student_onboarding_user_id'));
    }

    public function test_legacy_post_first_time_route_still_submits_index(): void
    {
        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/001',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->post('/login/first-time', [
            'index_number' => 'BCS/2099/001',
        ])
            ->assertRedirect(route('login.otp', absolute: false));
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

        $this->post('/login', [
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

        $this->post('/login', ['index_number' => 'BCS/2099/088']);
        $this->post('/login/first-time/phone', ['phone' => '+233241112233'])
            ->assertRedirect(route('login.otp', absolute: false));

        $this->post('/login/otp', ['otp' => '123456'])
            ->assertRedirect(route('student.onboarding', absolute: false));

        $user->refresh();
        $this->assertSame('233241112233', $user->phone);
    }

    public function test_unknown_index_number_is_rejected_without_specific_reason(): void
    {
        $this->post('/login', [
            'index_number' => 'DOES/NOT/EXIST',
        ])
            ->assertSessionHasErrors('index_number');

        $this->assertGuest();
    }

    public function test_coordinator_index_cannot_be_used_on_student_login(): void
    {
        User::factory()->create([
            'role' => 'coordinator',
            'index_number' => 'COORD/2099/001',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'index_number' => 'COORD/2099/001',
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

        $this->post('/login', ['index_number' => 'BCS/2099/002']);
        $this->post('/login/otp', ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_onboarded_student_cannot_skip_to_password_without_index_session(): void
    {
        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/003',
            'is_active' => true,
            'student_onboarded_at' => now(),
        ]);

        $this->get('/login/password')
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_staff_login_rejects_student_accounts(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'student@example.com',
            'is_active' => true,
        ]);

        $this->post('/admin_login', [
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

        $this->post('/admin_login', [
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

    public function test_staff_login_page_is_available_at_admin_login(): void
    {
        $response = $this->get('/admin_login');

        $response->assertOk();
        $response->assertSee(__('Staff portal'), false);
        $response->assertSee(__('Email or username'), false);
    }

    public function test_legacy_staff_login_url_redirects_to_admin_login(): void
    {
        $this->get('/staff/login')->assertRedirect('/admin_login');
    }

    public function test_dashboard_redirects_admin_to_admin_dashboard_route(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-dash@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_dashboard_renders_coordinator_workspace_for_coordinator(): void
    {
        $user = User::factory()->create([
            'role' => 'coordinator',
            'email' => 'coord-dash@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Coordinator Dashboard', false);
    }

    public function test_dashboard_redirects_examiner_to_examiner_dashboard_route(): void
    {
        $user = User::factory()->create([
            'role' => 'examiner',
            'email' => 'exam-dash@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('examiner.dashboard', absolute: false));
    }

    public function test_legacy_admin_path_redirects_to_dashboard_admin(): void
    {
        $this->get('/admin/universities')->assertRedirect('/dashboard/admin/universities');
    }
}
