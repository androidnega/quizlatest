<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->longText('answer_text')->nullable();
            $table->json('answer_payload')->nullable();
            $table->timestamp('saved_at')->useCurrent();
            $table->timestamps();

            $table->unique(['exam_session_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_answers');
    }
};
