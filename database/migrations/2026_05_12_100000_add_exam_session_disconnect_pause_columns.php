<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('exam_status');
            $table->timestamp('pause_segment_started_at')->nullable()->after('last_seen_at');
            $table->unsignedInteger('accumulated_pause_seconds')->default(0)->after('pause_segment_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'last_seen_at',
                'pause_segment_started_at',
                'accumulated_pause_seconds',
            ]);
        });
    }
};
