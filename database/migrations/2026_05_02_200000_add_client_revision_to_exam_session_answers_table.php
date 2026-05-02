<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_session_answers', function (Blueprint $table) {
            $table->unsignedInteger('client_revision')->default(0)->after('saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('exam_session_answers', function (Blueprint $table) {
            $table->dropColumn('client_revision');
        });
    }
};
