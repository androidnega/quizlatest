<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->string('verification_image_path')->nullable()->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn('verification_image_path');
        });
    }
};
