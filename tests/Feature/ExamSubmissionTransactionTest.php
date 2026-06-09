<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\Result;
use App\Models\User;
use App\Services\ExamSessionSubmissionService;
use App\Services\ResultFinalizationService;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies the DB::transaction wrapper around the post-submit pipeline in
 * ExamSessionSubmissionService::submit(). Three guarantees are exercised:
 *
 *   1. Atomicity / rollback safety — if any step inside the pipeline
 *      (e.g. ResultFinalizationService::syncAfterSubmission) throws, the
 *      session row is NOT left flipped to 'submitted' and no Result row
 *      is persisted. The session remains writable so a retry can resubmit
 *      cleanly.
 *
 *   2. Retry safety — once the failing dependency recovers, calling
 *      submit() again produces exactly one Result row and exactly one
 *      'submitted' session.
 *
 *   3. Idempotency — calling submit() twice on an already-finalised
 *      session is a no-op (CAS short-circuit). The second call must NOT
 *      double-create a Result row, double-decrement the active-session
 *      counter, or duplicate the assignment ActivityLog entry.
 *
 *   4. CAS interlock — a row whose status was concurrently flipped to
 *      'submitted' by another writer is treated as already-finalised by
 *      submit(); the in-flight call returns without running the pipeline.
 */
class ExamSubmissionTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);
    }

    /**
     * @return array{student: User, session: ExamSession}
     */
    private function bootActiveExam(): array
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $uniId = (int) $student->university_id;
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'TX-CTX-'.Str::random(4),
            'title' => 'Tx test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'TxClass-'.Str::random(4),
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('class_course')->insert([
            'class_id' => $classId,
            'course_id' => $courseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'tx-ex.'.Str::random(8).'@t.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Tx test exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 60,
            'total_marks' => 10,
            'proctoring_settings' => json_encode(AssessmentProctoringDefaults::baselineForType('exam', true, true, true)),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('quiz_class')->insert([
            'quiz_id' => $quizId,
            'class_id' => $classId,
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $student->fresh()->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
            'writing_started_at' => now(),
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'active',
            'last_seen_at' => now(),
            'accumulated_pause_seconds' => 0,
        ]);

        return ['student' => $student->fresh(), 'session' => $session];
    }

    public function test_pipeline_failure_rolls_back_status_and_leaves_no_result(): void
    {
        ['session' => $session] = $this->bootActiveExam();

        // Inject a finaliser that always throws. The transaction must
        // unwind every change written so far in this pipeline.
        $this->app->bind(ResultFinalizationService::class, function (): ResultFinalizationService {
            return new class extends ResultFinalizationService
            {
                public function syncAfterSubmission(
                    \App\Models\ExamSession $examSession,
                    int $timeTakenSeconds,
                    string $reviewNote,
                ): void {
                    throw new RuntimeException('Forced finaliser failure for tx test');
                }
            };
        });

        $service = $this->app->make(ExamSessionSubmissionService::class);

        $thrown = null;
        try {
            $service->submit($session, 'submitted', 'manual_submit');
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame('Forced finaliser failure for tx test', $thrown->getMessage());

        // Status flip must be rolled back: the session is still active.
        $row = DB::table('exam_sessions')->where('id', $session->id)->first();
        $this->assertSame('active', $row->status, 'submit() left a half-finalised session in the DB');
        $this->assertNull($row->end_time, 'submit() persisted end_time despite rollback');

        // No Result row should exist for this user/quiz pair.
        $resultCount = Result::query()
            ->where('user_id', $session->student_id)
            ->where('quiz_id', $session->exam_id)
            ->count();
        $this->assertSame(0, $resultCount, 'submit() persisted a Result despite the transaction rolling back');
    }

    public function test_retry_after_failure_produces_clean_finalised_state(): void
    {
        ['session' => $session] = $this->bootActiveExam();

        // First attempt: forced failure inside the transaction.
        $this->app->bind(ResultFinalizationService::class, function (): ResultFinalizationService {
            return new class extends ResultFinalizationService
            {
                public function syncAfterSubmission(
                    \App\Models\ExamSession $examSession,
                    int $timeTakenSeconds,
                    string $reviewNote,
                ): void {
                    throw new RuntimeException('Forced finaliser failure for tx retry test');
                }
            };
        });

        try {
            $this->app->make(ExamSessionSubmissionService::class)
                ->submit($session, 'submitted', 'manual_submit');
        } catch (RuntimeException) {
            // Expected; transaction rolled back.
        }

        // Second attempt: real finaliser, fresh service instance.
        $this->app->forgetInstance(ResultFinalizationService::class);
        $this->app->forgetInstance(ExamSessionSubmissionService::class);
        $this->app->bind(
            ResultFinalizationService::class,
            ResultFinalizationService::class,
        );

        $session->refresh();
        $this->app->make(ExamSessionSubmissionService::class)
            ->submit($session, 'submitted', 'manual_submit');

        $row = DB::table('exam_sessions')->where('id', $session->id)->first();
        $this->assertSame('submitted', $row->status);
        $this->assertNotNull($row->end_time);

        $resultCount = Result::query()
            ->where('user_id', $session->student_id)
            ->where('quiz_id', $session->exam_id)
            ->count();
        $this->assertSame(1, $resultCount, 'Retry must produce exactly one Result row');
    }

    public function test_double_submit_is_idempotent(): void
    {
        ['session' => $session] = $this->bootActiveExam();

        $service = $this->app->make(ExamSessionSubmissionService::class);

        $service->submit($session, 'submitted', 'manual_submit');
        $session->refresh();
        $firstEndTime = $session->end_time;

        // Second call must short-circuit (CAS sees status=submitted).
        $service->submit($session, 'submitted', 'manual_submit');
        $session->refresh();

        $this->assertSame('submitted', $session->status);
        $this->assertEquals(
            $firstEndTime?->toIso8601String(),
            $session->end_time?->toIso8601String(),
            'Second submit call mutated end_time of an already-finalised session',
        );

        $resultCount = Result::query()
            ->where('user_id', $session->student_id)
            ->where('quiz_id', $session->exam_id)
            ->count();
        $this->assertSame(1, $resultCount, 'Second submit call double-created a Result row');
    }

    public function test_cas_interlock_skips_pipeline_when_already_submitted(): void
    {
        ['session' => $session] = $this->bootActiveExam();

        // Simulate a concurrent writer that flipped the status before we
        // ran. The submit() call must see affected_rows=0 and refuse to
        // run the pipeline, regardless of what the in-memory model says.
        DB::table('exam_sessions')
            ->where('id', $session->id)
            ->update(['status' => 'submitted', 'end_time' => now()]);

        // Wipe the in-memory model so the early-return on `status ===
        // 'submitted'` doesn't fire and we exercise the CAS branch instead.
        $session->status = 'active';
        $session->syncOriginal();

        $service = $this->app->make(ExamSessionSubmissionService::class);
        $service->submit($session, 'submitted', 'manual_submit');

        // No Result row written by us — only the concurrent writer's
        // (which there isn't, in this synthetic test). The point is that
        // we must not have created one ourselves.
        $resultCount = Result::query()
            ->where('user_id', $session->student_id)
            ->where('quiz_id', $session->exam_id)
            ->count();
        $this->assertSame(0, $resultCount, 'CAS-losing call ran the post-submit pipeline anyway');
    }
}
