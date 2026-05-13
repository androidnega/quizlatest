<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Database\Seeders\OwnerAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_seeder_creates_manuel_with_hashed_password(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $owner = User::query()->where('email', 'manuel')->firstOrFail();
        $this->assertSame('admin', $owner->role);
        $this->assertTrue($owner->is_active);
        $this->assertTrue($owner->isSuperAdmin());
        $this->assertTrue(Hash::check('admin123', $owner->password));

        $primary = User::query()->where('email', 'admin')->firstOrFail();
        $this->assertTrue($primary->isSuperAdmin());
        $this->assertTrue(Hash::check('admin123', $primary->password));
    }

    public function test_staff_portal_accepts_manuel_and_admin_credentials(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $this->seed(OwnerAdminSeeder::class);

        $this->assertTrue(Auth::attempt(['email' => 'manuel', 'password' => 'admin123']));
        Auth::logout();

        $this->assertTrue(Auth::attempt(['email' => 'admin', 'password' => 'admin123']));
        Auth::logout();
    }
}
