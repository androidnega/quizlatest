<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSmsOtpGateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_enable_sms_false_blocks_first_time_otp_send(): void
    {
        $admin = $this->admin();
        $settings = app(SystemSettingsService::class);
        $settings->set('enable_sms', '0', $admin);
        $settings->set('arkesel_api_key', 'configured-key', $admin);
        $settings->set('arkesel_sender_id', 'SENDER', $admin);

        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/700',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->post('/login', ['index_number' => 'BCS/2099/700'])
            ->assertSessionHasErrors('index_number');

        $this->assertStringContainsString(
            'SMS verification is currently disabled',
            (string) session('errors')->get('index_number')[0]
        );
    }

    public function test_enable_sms_false_blocks_password_reset_otp_send(): void
    {
        $admin = $this->admin();
        $settings = app(SystemSettingsService::class);
        $settings->set('enable_sms', '0', $admin);
        $settings->set('arkesel_api_key', 'configured-key', $admin);
        $settings->set('arkesel_sender_id', 'SENDER', $admin);

        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/701',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => now(),
            'last_student_password_reset_at' => now()->subMonths(4),
        ]);

        $this->post('/student/forgot-password', ['identifier' => 'BCS/2099/701'])
            ->assertSessionHasErrors('identifier');

        $this->assertStringContainsString(
            'SMS verification is currently disabled',
            (string) session('errors')->get('identifier')[0]
        );
    }

    public function test_enable_sms_true_but_missing_credentials_shows_clear_error_on_first_login(): void
    {
        $admin = $this->admin();
        $settings = app(SystemSettingsService::class);
        $settings->set('enable_sms', '1', $admin);

        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/702',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->post('/login', ['index_number' => 'BCS/2099/702'])
            ->assertSessionHasErrors('index_number');

        $this->assertStringContainsString(
            'SMS verification is not fully configured',
            (string) session('errors')->get('index_number')[0]
        );
    }

    public function test_enable_sms_true_with_credentials_allows_first_login_otp_stub_path(): void
    {
        $admin = $this->admin();
        $settings = app(SystemSettingsService::class);
        $settings->set('enable_sms', '1', $admin);
        $settings->set('arkesel_api_key', 'test-api-key', $admin);
        $settings->set('arkesel_sender_id', 'TESTSND', $admin);

        User::factory()->create([
            'role' => 'student',
            'index_number' => 'BCS/2099/703',
            'phone' => '+233241112233',
            'is_active' => true,
            'student_onboarded_at' => null,
        ]);

        $this->post('/login', ['index_number' => 'BCS/2099/703'])
            ->assertRedirect(route('login.otp', absolute: false));
    }

    public function test_admin_settings_page_shows_sms_status_without_exposing_api_key(): void
    {
        $admin = $this->admin();
        $settings = app(SystemSettingsService::class);
        $settings->set('enable_sms', '1', $admin);
        $settings->set('arkesel_api_key', 'super-secret-arkesel-key-999', $admin);
        $settings->set('arkesel_sender_id', 'MYAPP', $admin);

        $response = $this->actingAs($admin)->get(route('admin.settings.index', absolute: false));

        $response->assertOk();
        $response->assertSee(__('SMS Ready: enabled and credentials configured'), false);
        $response->assertDontSee('super-secret-arkesel-key-999', false);
    }
}
