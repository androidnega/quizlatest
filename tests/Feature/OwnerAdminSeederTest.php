<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Database\Seeders\OwnerAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue(Hash::check('admin123', $owner->password));
    }
}
