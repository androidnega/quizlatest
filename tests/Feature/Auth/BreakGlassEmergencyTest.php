<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Database\Seeders\OwnerAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BreakGlassEmergencyTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);
    }

    private function enableBreakGlass(string $plainSecret): void
    {
        config([
            'breakglass.enabled' => true,
            'breakglass.secret_hash' => Hash::make($plainSecret),
            'breakglass.owner_username' => 'manuel',
            'breakglass.owner_phone' => '233241112233',
        ]);
    }

    public function test_emergency_route_returns_404_when_disabled(): void
    {
        $this->seedBase();
        config(['breakglass.enabled' => false, 'breakglass.secret_hash' => Hash::make('x')]);

        $this->get(route('breakglass.emergency'))->assertNotFound();
        $this->post(route('breakglass.emergency.store'), [
            'privileged_username' => 'nope',
            'emergency_secret' => 'nope',
        ])->assertNotFound();
    }

    public function test_wrong_emergency_secret_fails_without_detail_and_logs(): void
    {
        $this->seedBase();
        $this->enableBreakGlass('correct-secret');

        $before = ActivityLog::query()->count();

        $this->post(route('breakglass.emergency.store'), [
            'privileged_username' => 'kofi.mensah@university.edu',
            'emergency_secret' => 'wrong-secret',
        ])
            ->assertSessionHasErrors('privileged_username');

        $this->assertGreaterThan($before, ActivityLog::query()->count());
        $this->assertTrue(
            ActivityLog::query()->where('event_type', 'break_glass_step1_failed')->exists(),
        );
    }

    public function test_emergency_target_student_is_rejected(): void
    {
        $this->seedBase();
        $this->enableBreakGlass('correct-secret');

        $student = User::query()->where('role', 'student')->firstOrFail();

        $this->post(route('breakglass.emergency.store'), [
            'privileged_username' => (string) $student->index_number,
            'emergency_secret' => 'correct-secret',
        ])
            ->assertSessionHasErrors('privileged_username');

        $this->assertTrue(
            ActivityLog::query()->where('event_type', 'break_glass_step1_rejected')->exists(),
        );
    }

    public function test_correct_secret_and_otp_logs_in_owner_admin(): void
    {
        $this->seedBase();
        $this->enableBreakGlass('glass-secret');

        $this->post(route('breakglass.emergency.store'), [
            'privileged_username' => 'kofi.mensah@university.edu',
            'emergency_secret' => 'glass-secret',
        ])->assertRedirect(route('breakglass.emergency.verify.form', absolute: false));

        $this->assertTrue(
            ActivityLog::query()->where('event_type', 'break_glass_challenge_issued')->exists(),
        );

        $this->get(route('breakglass.emergency.verify.form'))->assertOk();

        $owner = User::query()->where('email', 'manuel')->firstOrFail();

        $this->post(route('breakglass.emergency.verify'), [
            'otp' => '123456',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($owner);

        $this->assertTrue(
            ActivityLog::query()->where('event_type', 'break_glass_success')->exists(),
        );
    }
}
