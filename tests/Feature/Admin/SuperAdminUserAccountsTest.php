<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Database\Seeders\OwnerAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperAdminUserAccountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seeded `admin` is a super admin for dev UX; policy tests use a factory admin without the flag.
     */
    private function limitedAdmin(): User
    {
        $uni = (int) DB::table('universities')->value('id');

        return User::factory()->create([
            'name' => 'Limited Admin',
            'email' => 'limited-admin-'.uniqid('', true).'@test.local',
            'role' => 'admin',
            'is_super_admin' => false,
            'university_id' => $uni,
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_regular_admin_cannot_open_accounts_directory(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $limited = $this->limitedAdmin();
        $this->assertFalse($limited->isSuperAdmin());

        $this->actingAs($limited)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_super_admin_dashboard_includes_user_management_section(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $owner = User::query()->where('email', 'manuel')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Manage users'), false)
            ->assertSee(__('Open staff directory'), false);

        $primary = User::query()->where('email', 'admin')->firstOrFail();
        $this->actingAs($primary)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Manage users'), false);
    }

    public function test_regular_admin_dashboard_does_not_show_user_management_section(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $limited = $this->limitedAdmin();

        $html = $this->actingAs($limited)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString(__('Open staff directory'), $html);
    }

    public function test_super_admin_can_open_accounts_directory_and_filter(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $owner = User::query()->where('email', 'manuel')->firstOrFail();
        $this->assertTrue($owner->isSuperAdmin());

        $this->actingAs($owner)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Kofi Mensah', false)
            ->assertDontSee('Akua Serwaa', false);

        $this->actingAs($owner)
            ->get(route('admin.users.index', ['role' => 'coordinator']))
            ->assertOk()
            ->assertSee('Kofi Mensah', false)
            ->assertDontSee('System Admin', false);
    }

    public function test_super_admin_can_reset_coordinator_password_and_cannot_update_student_via_accounts(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $owner = User::query()->where('email', 'manuel')->firstOrFail();
        $plainAdmin = $this->limitedAdmin();
        $coordinator = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $student = User::query()->where('role', 'student')->firstOrFail();

        $staffPayload = [
            'name' => $coordinator->name,
            'email' => $coordinator->email,
            'is_active' => '1',
            'generate_password' => '1',
        ];

        $this->actingAs($plainAdmin)
            ->put(route('admin.users.update', $coordinator), $staffPayload)
            ->assertForbidden();

        $this->actingAs($owner)
            ->put(route('admin.users.update', $coordinator), $staffPayload)
            ->assertRedirect(route('admin.users.edit', $coordinator))
            ->assertSessionHas('generated_password');

        $coordinator->refresh();
        $this->assertNotNull($coordinator->password);

        $studentPayload = [
            'name' => $student->name,
            'email' => $student->email,
            'is_active' => '1',
            'generate_password' => '1',
        ];

        $this->actingAs($owner)
            ->put(route('admin.users.update', $student), $studentPayload)
            ->assertForbidden();
    }

    public function test_super_admin_can_open_create_staff_account_form(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $owner = User::query()->where('email', 'manuel')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee(__('Create staff account'), false);
    }

    public function test_regular_admin_cannot_create_staff_account(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $limited = $this->limitedAdmin();

        $this->actingAs($limited)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($limited)->post(route('admin.users.store'), [])->assertForbidden();
    }

    public function test_super_admin_can_create_examiner_account(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $owner = User::query()->where('email', 'manuel')->firstOrFail();
        $universityId = (int) DB::table('universities')->value('id');

        $this->actingAs($owner)
            ->post(route('admin.users.store'), [
                'role' => 'examiner',
                'name' => 'New Examiner',
                'email' => 'new.examiner@university.edu',
                'university_id' => $universityId,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $examiner = User::query()->where('email', 'new.examiner@university.edu')->firstOrFail();
        $this->assertSame('examiner', $examiner->role);
        $this->assertNotNull($examiner->password);
    }

    public function test_create_staff_rejects_student_role(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $owner = User::query()->where('email', 'manuel')->firstOrFail();
        $universityId = (int) DB::table('universities')->value('id');

        $this->actingAs($owner)
            ->post(route('admin.users.store'), [
                'role' => 'student',
                'name' => 'Bad',
                'email' => 'bad@example.com',
                'university_id' => $universityId,
            ])
            ->assertSessionHasErrors('role');
    }
}
