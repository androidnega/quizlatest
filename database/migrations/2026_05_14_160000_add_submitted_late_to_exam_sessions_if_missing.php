<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
