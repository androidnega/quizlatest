<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_session_answers', function (Blueprint $table) {
            $table->decimal('points_awarded', 8, 2)->nullable()->after('answer_payload');
            $table->string('evaluation_status', 32)->nullable()->after('points_awarded');
            $table->json('evaluation_detail')->nullable()->after('evaluation_status');
        });
    }

    public function down(): void
    {
        Schema::table('exam_session_answers', function (Blueprint $table) {
            $table->dropColumn(['points_awarded', 'evaluation_status', 'evaluation_detail']);
        });
    }
};
