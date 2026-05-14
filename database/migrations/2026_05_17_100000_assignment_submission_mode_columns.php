<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'assignment_attachment_required')) {
                $table->boolean('assignment_attachment_required')->default(false)->after('assignment_allows_files');
            }
            if (! Schema::hasColumn('quizzes', 'assignment_disable_paste')) {
                $table->boolean('assignment_disable_paste')->default(true)->after('assignment_attachment_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            foreach (['assignment_disable_paste', 'assignment_attachment_required'] as $col) {
                if (Schema::hasColumn('quizzes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
