<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Architecture Review Phase 3 — refactor exam_sessions.violation_events.
 *
 * The legacy schema stored an unbounded JSON array of every proctoring
 * event in exam_sessions.violation_events, plus a `last_event_time`
 * timestamp. The same data is already persisted in the relational
 * `proctoring_events` table — the JSON column was a redundant (and
 * unbounded-growing) buffer that bloated every UPDATE on a busy
 * exam_session row.
 *
 * This migration is the FIRST stage of the cleanup:
 *
 *   1. Adds `last_violation_at` (replaces `last_event_time`).
 *   2. Backfills `last_violation_at` from `last_event_time` for all
 *      existing rows so no information is lost.
 *   3. Empties `violation_events` JSON for ALREADY-SUBMITTED sessions
 *      (the JSON buffer is no longer needed and only takes disk).
 *
 * The columns `violation_events` and `last_event_time` are NOT dropped
 * yet — they remain nullable for back-compat with any in-flight code
 * paths and any test fixtures that still set them. A follow-up
 * migration in a later sprint will drop them once all readers are
 * verified to be gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_sessions')) {
            return;
        }

        if (! Schema::hasColumn('exam_sessions', 'last_violation_at')) {
            Schema::table('exam_sessions', function (Blueprint $table): void {
                $table->timestamp('last_violation_at')->nullable()->after('violation_events');
            });
        }

        // Backfill from last_event_time when present.
        if (Schema::hasColumn('exam_sessions', 'last_event_time')) {
            DB::table('exam_sessions')
                ->whereNull('last_violation_at')
                ->whereNotNull('last_event_time')
                ->update(['last_violation_at' => DB::raw('last_event_time')]);
        }

        // Drain the JSON buffer for already-submitted sessions. The
        // historical events live in proctoring_events; the JSON column
        // is now dead weight on those rows. We only touch finished
        // sessions to avoid racing with any live writers during the
        // migration window.
        if (Schema::hasColumn('exam_sessions', 'violation_events')) {
            DB::table('exam_sessions')
                ->whereIn('status', ['submitted'])
                ->whereNotNull('violation_events')
                ->update(['violation_events' => DB::connection()->getDriverName() === 'sqlite' ? '[]' : DB::raw("JSON_ARRAY()")]);
        }

        // Helpful index for the new cooldown / duration queries that
        // ProctoringOrchestratorService runs against proctoring_events.
        // The composite (user_id, quiz_id, event_type, created_at) is
        // the natural fit for "find the most recent N events of type T
        // for this attempt".
        if (Schema::hasTable('proctoring_events')) {
            try {
                Schema::table('proctoring_events', function (Blueprint $table): void {
                    $table->index(
                        ['user_id', 'quiz_id', 'event_type', 'created_at'],
                        'proctoring_events_user_quiz_type_created_idx',
                    );
                });
            } catch (\Throwable $e) {
                // Index may already exist (idempotent migration).
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('proctoring_events')) {
            try {
                Schema::table('proctoring_events', function (Blueprint $table): void {
                    $table->dropIndex('proctoring_events_user_quiz_type_created_idx');
                });
            } catch (\Throwable $e) {
            }
        }

        if (Schema::hasTable('exam_sessions') && Schema::hasColumn('exam_sessions', 'last_violation_at')) {
            Schema::table('exam_sessions', function (Blueprint $table): void {
                $table->dropColumn('last_violation_at');
            });
        }
    }
};
