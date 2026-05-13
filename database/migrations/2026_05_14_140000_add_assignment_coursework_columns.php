<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('end_time');
            $table->timestamp('grades_released_at')->nullable()->after('due_at');
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->boolean('submitted_late')->default(false)->after('exam_status');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn('submitted_late');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['due_at', 'grades_released_at']);
        });
    }
};
