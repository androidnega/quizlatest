<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OwnerAdminSeeder extends Seeder
{
    /**
     * Owner admin account (username stored in `email` column for staff login).
     * Default password: admin123 — change immediately in production.
     */
    public function run(): void
    {
        $uniId = DB::table('universities')->value('id');
        if ($uniId === null) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($adminRoleId === null) {
            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'manuel'],
            [
                'university_id' => (int) $uniId,
                'name' => 'Manuel (Owner)',
                'index_number' => null,
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => Hash::make('admin123'),
            ],
        );

        DB::table('role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'role_id' => (int) $adminRoleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
