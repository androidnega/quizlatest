<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'assignment_allow_code')) {
                $table->boolean('assignment_allow_code')->default(false)->after('assignment_disable_paste');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'assignment_allow_code')) {
                $table->dropColumn('assignment_allow_code');
            }
        });
    }
};
