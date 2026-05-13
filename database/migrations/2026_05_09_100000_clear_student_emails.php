<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'student')
            ->update([
                'email' => null,
                'email_verified_at' => null,
            ]);
    }

    public function down(): void
    {
        // Irreversible — emails were discarded intentionally.
    }
};
