<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->unsignedInteger('violation_score')->default(0)->after('violation_count');
            $table->json('violation_events')->nullable()->after('violation_score');
            $table->timestamp('last_event_time')->nullable()->after('violation_events');
            $table->enum('risk_state', ['normal', 'warning', 'suspicious', 'critical', 'locked'])->default('normal')->after('last_event_time');
            $table->string('exam_status')->default('active')->after('risk_state');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'violation_score',
                'violation_events',
                'last_event_time',
                'risk_state',
                'exam_status',
            ]);
        });
    }
};
