<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OwnerAdminSeeder extends Seeder
{
    /**
     * Owner admin account (username stored in `email` column for staff login).
     * Default password: admin123 — change immediately in production.
     *
     * Password is written with Hash::make via the query builder (same as InitialSetupSeeder)
     * so it always matches Auth::attempt regardless of Eloquent casts.
     */
    public function run(): void
    {
        $uniId = DB::table('universities')->value('id');
        if ($uniId === null) {
            $this->command?->warn('OwnerAdminSeeder skipped: `universities` is empty. Run `php artisan db:seed` after InitialSetupSeeder (e.g. `php artisan migrate --seed`).');

            return;
        }

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($adminRoleId === null) {
            $this->command?->warn('OwnerAdminSeeder skipped: no `admin` role row. Run full `php artisan db:seed`.');

            return;
        }

        $now = now();
        $passwordHash = Hash::make('admin123');

        $base = [
            'university_id' => (int) $uniId,
            'name' => 'Manuel (Owner)',
            'index_number' => null,
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => $now,
            'password' => $passwordHash,
            'is_super_admin' => true,
            'remember_token' => null,
            'updated_at' => $now,
        ];

        if (DB::table('users')->where('email', 'manuel')->exists()) {
            DB::table('users')->where('email', 'manuel')->update($base);
        } else {
            DB::table('users')->insert(array_merge($base, [
                'email' => 'manuel',
                'created_at' => $now,
            ]));
        }

        $userId = (int) DB::table('users')->where('email', 'manuel')->value('id');

        DB::table('role_user')->updateOrInsert(
            [
                'user_id' => $userId,
                'role_id' => (int) $adminRoleId,
            ],
            [
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
}
