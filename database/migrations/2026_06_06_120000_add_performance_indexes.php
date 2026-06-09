<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes derived from the QUIZSNAP performance audit (Section 2.4 / P1.7).
 *
 * Each index is added inside its own try/catch so re-running the migration on a
 * database that already has some of them (or whose driver - SQLite in tests -
 * does not support every index pattern) will not abort the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('exam_sessions', 'exam_sessions_exam_status_idx', ['exam_id', 'status']);
        $this->addIndex('exam_sessions', 'exam_sessions_student_exam_status_idx', ['student_id', 'exam_id', 'status']);
        $this->addIndex('exam_sessions', 'exam_sessions_status_lastseen_idx', ['status', 'last_seen_at']);
        $this->addIndex('exam_sessions', 'exam_sessions_last_seen_at_idx', ['last_seen_at']);

        $this->addIndex('exam_session_answers', 'esa_session_question_idx', ['exam_session_id', 'question_id']);

        $this->addIndex('results', 'results_quiz_status_idx', ['quiz_id', 'status']);
        $this->addIndex('results', 'results_user_status_submitted_idx', ['user_id', 'status', 'submitted_at']);

        $this->addIndex('users', 'users_role_uni_class_idx', ['role', 'university_id', 'class_id']);

        $this->addIndex('quizzes', 'quizzes_status_uni_course_start_idx', ['status', 'university_id', 'course_id', 'start_time']);

        $this->addIndex('questions', 'questions_quiz_pool_type_idx', ['quiz_id', 'pool_status', 'type']);

        $this->addIndex('proctoring_events', 'proctoring_events_session_id_idx', ['user_id', 'quiz_id', 'severity', 'created_at']);

        $this->dropIndexIfExists('proctoring_events', 'proctoring_events_event_type_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('exam_sessions', 'exam_sessions_exam_status_idx');
        $this->dropIndexIfExists('exam_sessions', 'exam_sessions_student_exam_status_idx');
        $this->dropIndexIfExists('exam_sessions', 'exam_sessions_status_lastseen_idx');
        $this->dropIndexIfExists('exam_sessions', 'exam_sessions_last_seen_at_idx');

        $this->dropIndexIfExists('exam_session_answers', 'esa_session_question_idx');

        $this->dropIndexIfExists('results', 'results_quiz_status_idx');
        $this->dropIndexIfExists('results', 'results_user_status_submitted_idx');

        $this->dropIndexIfExists('users', 'users_role_uni_class_idx');

        $this->dropIndexIfExists('quizzes', 'quizzes_status_uni_course_start_idx');

        $this->dropIndexIfExists('questions', 'questions_quiz_pool_type_idx');

        $this->dropIndexIfExists('proctoring_events', 'proctoring_events_session_id_idx');
    }

    /**
     * Adds an index, swallowing "duplicate key" / unsupported-driver errors so
     * the migration is idempotent on shared hosting.
     */
    private function addIndex(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $col) {
            if (! Schema::hasColumn($table, $col)) {
                return;
            }
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($name, $columns): void {
                $blueprint->index($columns, $name);
            });
        } catch (\Throwable $e) {
        }
    }

    private function dropIndexIfExists(string $table, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        } catch (\Throwable $e) {
        }
    }
};
