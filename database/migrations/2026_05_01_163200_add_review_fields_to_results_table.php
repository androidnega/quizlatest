<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->string('exam_status')->default('submitted')->after('status');
            $table->string('review_decision')->nullable()->after('exam_status');
            $table->text('review_note')->nullable()->after('review_decision');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn(['exam_status', 'review_decision', 'review_note']);
        });
    }
};
