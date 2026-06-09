<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-Ops Phase 5 — examiner emergency tools.
 *
 * Adds two fields to exam_sessions:
 *   - extra_seconds      : examiner-granted time extension (added to the
 *                          remaining-time budget by ExamSessionTimer).
 *   - examiner_unlocked  : signals that an examiner has manually
 *                          re-activated a stuck/paused session, so the
 *                          stale-pause auto-submit should not immediately
 *                          re-pause the same row in the next minute.
 *
 * Every state change still produces an ActivityLog row through
 * ExaminerEmergencyAuditService — these columns just store the
 * cumulative state.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_sessions')) {
            return;
        }

        Schema::table('exam_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('exam_sessions', 'extra_seconds')) {
                $table->integer('extra_seconds')->default(0)->after('accumulated_pause_seconds');
            }
            if (! Schema::hasColumn('exam_sessions', 'examiner_unlocked_at')) {
                $table->timestamp('examiner_unlocked_at')->nullable()->after('extra_seconds');
            }
            if (! Schema::hasColumn('exam_sessions', 'examiner_unlocked_by')) {
                $table->unsignedBigInteger('examiner_unlocked_by')->nullable()->after('examiner_unlocked_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_sessions')) {
            return;
        }

        Schema::table('exam_sessions', function (Blueprint $table): void {
            foreach (['extra_seconds', 'examiner_unlocked_at', 'examiner_unlocked_by'] as $col) {
                if (Schema::hasColumn('exam_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
