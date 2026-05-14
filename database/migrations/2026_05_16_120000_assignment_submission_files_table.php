<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assignment_submission_files')) {
            Schema::create('assignment_submission_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_session_id')->constrained('exam_sessions')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
                $table->string('original_filename', 255);
                $table->string('stored_path', 512);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->timestamp('uploaded_at')->useCurrent();
                $table->timestamps();

                $table->index(['exam_session_id']);
                $table->index(['student_id', 'quiz_id']);
            });
        }

        if (Schema::hasTable('exam_session_assignment_files')) {
            $legacy = DB::table('exam_session_assignment_files')->get();
            foreach ($legacy as $row) {
                $session = DB::table('exam_sessions')->where('id', $row->exam_session_id)->first();
                if ($session === null) {
                    continue;
                }
                DB::table('assignment_submission_files')->insert([
                    'exam_session_id' => (int) $row->exam_session_id,
                    'student_id' => (int) $session->student_id,
                    'quiz_id' => (int) $session->exam_id,
                    'original_filename' => (string) $row->original_filename,
                    'stored_path' => (string) $row->stored_path,
                    'mime_type' => $row->mime_type,
                    'file_size' => (int) ($row->size_bytes ?? 0),
                    'uploaded_at' => $row->created_at ?? now(),
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
            Schema::drop('exam_session_assignment_files');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_session_assignment_files') && Schema::hasTable('assignment_submission_files')) {
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

            $rows = DB::table('assignment_submission_files')->get();
            foreach ($rows as $row) {
                DB::table('exam_session_assignment_files')->insert([
                    'exam_session_id' => (int) $row->exam_session_id,
                    'stored_path' => (string) $row->stored_path,
                    'original_filename' => (string) $row->original_filename,
                    'mime_type' => $row->mime_type,
                    'size_bytes' => (int) $row->file_size,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
        }

        Schema::dropIfExists('assignment_submission_files');
    }
};
