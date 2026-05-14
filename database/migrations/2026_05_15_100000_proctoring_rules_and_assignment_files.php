<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_sessions', 'tab_switch_count')) {
                $table->unsignedInteger('tab_switch_count')->default(0)->after('violation_events');
            }
            if (! Schema::hasColumn('exam_sessions', 'auto_submit_reason_code')) {
                $table->string('auto_submit_reason_code', 80)->nullable()->after('exam_status');
            }
            if (! Schema::hasColumn('exam_sessions', 'proctoring_blur_active')) {
                $table->boolean('proctoring_blur_active')->default(false)->after('auto_submit_reason_code');
            }
            if (! Schema::hasColumn('exam_sessions', 'proctoring_blur_reason')) {
                $table->string('proctoring_blur_reason', 120)->nullable()->after('proctoring_blur_active');
            }
            if (! Schema::hasColumn('exam_sessions', 'face_covered_strike_count')) {
                $table->unsignedSmallInteger('face_covered_strike_count')->default(0)->after('proctoring_blur_reason');
            }
        });

        Schema::table('quizzes', function (Blueprint $table) {
            if (! Schema::hasColumn('quizzes', 'assignment_allows_text')) {
                $table->boolean('assignment_allows_text')->default(true)->after('grades_released_at');
            }
            if (! Schema::hasColumn('quizzes', 'assignment_allows_files')) {
                $table->boolean('assignment_allows_files')->default(false)->after('assignment_allows_text');
            }
            if (! Schema::hasColumn('quizzes', 'assignment_allowed_extensions')) {
                $table->json('assignment_allowed_extensions')->nullable()->after('assignment_allows_files');
            }
            if (! Schema::hasColumn('quizzes', 'assignment_max_file_kb')) {
                $table->unsignedInteger('assignment_max_file_kb')->nullable()->after('assignment_allowed_extensions');
            }
        });

        if (! Schema::hasTable('exam_session_assignment_files')) {
            Schema::create('exam_session_assignment_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
                $table->string('stored_path', 512);
                $table->string('original_filename', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->timestamps();

                $table->index(['exam_session_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_assignment_files');

        Schema::table('quizzes', function (Blueprint $table) {
            foreach (['assignment_max_file_kb', 'assignment_allowed_extensions', 'assignment_allows_files', 'assignment_allows_text'] as $col) {
                if (Schema::hasColumn('quizzes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            foreach (['face_covered_strike_count', 'proctoring_blur_reason', 'proctoring_blur_active', 'auto_submit_reason_code', 'tab_switch_count'] as $col) {
                if (Schema::hasColumn('exam_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
