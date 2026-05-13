<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->uuid('share_token')->nullable()->unique()->after('id');
        });

        foreach (DB::table('quizzes')->select('id', 'share_token')->cursor() as $row) {
            if ($row->share_token !== null && $row->share_token !== '') {
                continue;
            }
            DB::table('quizzes')->where('id', $row->id)->update([
                'share_token' => (string) Str::uuid(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
