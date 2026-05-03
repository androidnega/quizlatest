<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('display_order');
            $table->json('option_order')->nullable();
            $table->timestamps();

            $table->unique(['exam_session_id', 'question_id']);
            $table->index(['exam_session_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_questions');
    }
};
