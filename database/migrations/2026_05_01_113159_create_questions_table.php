<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quiz_section_id')->nullable();
            $table->longText('question_text');
            $table->enum('question_type', ['mcq', 'true_false', 'short_answer', 'essay'])->default('mcq');
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->decimal('marks', 8, 2)->default(1);
            $table->unsignedInteger('question_order')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'quiz_section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
