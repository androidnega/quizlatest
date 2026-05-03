<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type', 32);
            $table->string('extracted_text_path')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('extraction_error')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'status']);
        });

        Schema::create('practice_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('course_material_id')->nullable()->constrained('course_materials')->nullOnDelete();
            $table->string('title');
            $table->string('quiz_type', 32);
            $table->string('difficulty', 32);
            $table->unsignedSmallInteger('question_count');
            $table->string('status', 32)->default('draft');
            $table->boolean('generated_by_ai')->default(false);
            $table->text('generation_error')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['course_id', 'created_at']);
        });

        Schema::create('practice_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_quiz_id')->constrained('practice_quizzes')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('question_text');
            $table->json('options')->nullable();
            $table->json('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['practice_quiz_id', 'display_order']);
        });

        Schema::create('practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_quiz_id')->constrained('practice_quizzes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 10, 2)->default(0);
            $table->decimal('total_marks', 10, 2)->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['practice_quiz_id', 'student_id']);
        });

        Schema::create('practice_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_attempt_id')->constrained('practice_attempts')->cascadeOnDelete();
            $table->foreignId('practice_question_id')->constrained('practice_questions')->cascadeOnDelete();
            $table->json('answer_payload')->nullable();
            $table->decimal('points_awarded', 10, 2)->default(0);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['practice_attempt_id', 'practice_question_id'], 'practice_attempt_question_unique');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('feature', 64);
            $table->string('provider', 64);
            $table->string('model', 128)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['feature', 'created_at']);
        });

        Schema::create('practice_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('course_material_id')->nullable()->constrained('course_materials')->nullOnDelete();
            $table->string('title')->nullable();
            $table->longText('body');
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_summaries');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('practice_answers');
        Schema::dropIfExists('practice_attempts');
        Schema::dropIfExists('practice_questions');
        Schema::dropIfExists('practice_quizzes');
        Schema::dropIfExists('course_materials');
    }
};
