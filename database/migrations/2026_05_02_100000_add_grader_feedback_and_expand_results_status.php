<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_session_answers', function (Blueprint $table) {
            $table->text('grader_feedback')->nullable()->after('evaluation_detail');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE results MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'submitted'");
        } else {
            Schema::table('results', function (Blueprint $table) {
                $table->string('status', 32)->default('submitted')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('exam_session_answers', function (Blueprint $table) {
            $table->dropColumn('grader_feedback');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE results MODIFY COLUMN status ENUM('submitted','graded','published') NOT NULL DEFAULT 'submitted'");
        }
    }
};
