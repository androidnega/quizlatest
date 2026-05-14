<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quizzes', 'due_at') || ! Schema::hasColumn('quizzes', 'grades_released_at')) {
            Schema::table('quizzes', function (Blueprint $table) {
                if (! Schema::hasColumn('quizzes', 'due_at')) {
                    $table->timestamp('due_at')->nullable()->after('end_time');
                }
                if (! Schema::hasColumn('quizzes', 'grades_released_at')) {
                    $table->timestamp('grades_released_at')->nullable()->after('due_at');
                }
            });
        }

        if (! Schema::hasColumn('exam_sessions', 'submitted_late')) {
            Schema::table('exam_sessions', function (Blueprint $table) {
                $table->boolean('submitted_late')->default(false)->after('exam_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_sessions', 'submitted_late')) {
            Schema::table('exam_sessions', function (Blueprint $table) {
                $table->dropColumn('submitted_late');
            });
        }

        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'grades_released_at')) {
                $table->dropColumn('grades_released_at');
            }
            if (Schema::hasColumn('quizzes', 'due_at')) {
                $table->dropColumn('due_at');
            }
        });
    }
};
