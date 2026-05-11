<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('student_last_dashboard_at')->nullable()->after('last_student_password_reset_at');
            $table->unsignedInteger('policy_notice_ack_version')->default(0)->after('student_last_dashboard_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['student_last_dashboard_at', 'policy_notice_ack_version']);
        });
    }
};
