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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('assessment_type', ['quiz', 'mid', 'exam', 'assignment'])->default('quiz');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->json('proctoring_settings')->default(json_encode([
                'face_match_threshold' => 55,
                'tab_switch_limit' => 3,
                'copy_paste_blocked' => true,
                'audio_monitoring' => true,
                'camera_required' => true,
                'screen_capture_interval_seconds' => 10,
                'violation_actions' => [
                    'warn' => true,
                    'deduct' => false,
                    'autosubmit' => false,
                ],
            ]));
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_to')->nullable();

            $table->index(['university_id', 'course_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
