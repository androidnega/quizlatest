<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\Quiz;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamEssayClipboardProctoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{student: User, session: ExamSession, quizId: int}
     */
    private function seedStudentWithActiveSession(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $coord = User::query()->where('email', 'kofi.mensah@university.edu')->firstOrFail();
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-ESSAYCLIP',
            'title' => 'Essay clipboard test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'EssayClip',
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

        $student = User::query()->where('role', 'student')->firstOrFail();
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $coord->id,
            'title' => 'Essay clipboard exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'randomize_questions' => false,
            'randomize_options' => false,
            'proctoring_settings' => json_encode(new \stdClass),
            'published_at' => now(),
            'start_time' => null,
            'end_time' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $quizId,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now(),
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'risk_state' => 'normal',
            'exam_status' => 'active',
        ]);

        return ['student' => $student->fresh(), 'session' => $session, 'quizId' => $quizId];
    }

    private function essayClipboardBatchPayload(ExamSession $session, User $student, int $examId, int $questionId = 42): array
    {
        return [
            'events' => [
                [
                    'event_type' => 'essay_clipboard_attempt',
                    'metadata' => [
                        'session_id' => $session->session_id,
                        'student_id' => $student->id,
                        'exam_id' => $examId,
                        'question_id' => $questionId,
                        'action_type' => 'paste',
                    ],
                ],
            ],
        ];
    }

    public function test_batch_logs_essay_clipboard_attempt_with_metadata_and_default_log_only_score(): void
    {
        $ctx = $this->seedStudentWithActiveSession();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'true', $admin);

        $this->actingAs($ctx['student']);
        $response = $this->postJson(
            '/exam-sessions/'.$ctx['session']->session_id.'/proctoring-events/batch',
            $this->essayClipboardBatchPayload($ctx['session'], $ctx['student'], $ctx['quizId']),
        );

        $response->assertOk()
            ->assertJsonPath('status', 'logged')
            ->assertJsonPath('processed', 1);

        $ev = ProctoringEvent::query()->first();
        $this->assertNotNull($ev);
        $this->assertSame('essay_clipboard_attempt', $ev->event_type);
        $this->assertSame(42, $ev->metadata['payload']['question_id']);
        $this->assertSame('paste', $ev->metadata['payload']['action_type']);

        $ctx['session']->refresh();
        $this->assertSame(0, (int) $ctx['session']->violation_score);
    }

    public function test_essay_clipboard_is_logged_when_institution_proctoring_is_disabled(): void
    {
        $ctx = $this->seedStudentWithActiveSession();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'false', $admin);

        $this->actingAs($ctx['student']);
        $this->postJson(
            '/exam-sessions/'.$ctx['session']->session_id.'/proctoring-events/batch',
            $this->essayClipboardBatchPayload($ctx['session'], $ctx['student'], $ctx['quizId']),
        )->assertOk()->assertJsonPath('status', 'logged');

        $this->assertSame(1, ProctoringEvent::query()->count());
        $ctx['session']->refresh();
        $this->assertSame(0, (int) $ctx['session']->violation_score);
    }

    public function test_non_essay_proctoring_events_are_ignored_when_proctoring_disabled(): void
    {
        $ctx = $this->seedStudentWithActiveSession();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'false', $admin);

        $this->actingAs($ctx['student']);
        $this->postJson(
            '/exam-sessions/'.$ctx['session']->session_id.'/proctoring-events/batch',
            [
                'events' => [
                    [
                        'event_type' => 'tab_switch',
                        'metadata' => [
                            'session_id' => $ctx['session']->session_id,
                            'student_id' => $ctx['student']->id,
                            'exam_id' => $ctx['quizId'],
                        ],
                    ],
                ],
            ],
        )->assertOk()
            ->assertJsonPath('status', 'ignored')
            ->assertJsonPath('processed', 0);

        $this->assertSame(0, ProctoringEvent::query()->count());
    }

    public function test_invalid_essay_action_type_is_rejected(): void
    {
        $ctx = $this->seedStudentWithActiveSession();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'true', $admin);

        $payload = $this->essayClipboardBatchPayload($ctx['session'], $ctx['student'], $ctx['quizId']);
        $payload['events'][0]['metadata']['action_type'] = 'invalid_action';

        $this->actingAs($ctx['student']);
        $this->postJson(
            '/exam-sessions/'.$ctx['session']->session_id.'/proctoring-events/batch',
            $payload,
        )->assertStatus(422);

        $this->assertSame(0, ProctoringEvent::query()->count());
    }

    public function test_configurable_violation_weight_increments_score(): void
    {
        $ctx = $this->seedStudentWithActiveSession();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'true', $admin);

        $exam = Quiz::query()->findOrFail($ctx['quizId']);
        $exam->update([
            'proctoring_settings' => [
                'face_match_threshold' => 60,
                'tab_switch_rules' => [1 => 10, 2 => 40, 3 => 60],
                'phone_detection_enabled' => true,
                'fullscreen_enforced' => true,
                'auto_submit_enabled' => true,
                'violation_weights' => [
                    'face_missing' => 10,
                    'multiple_faces' => 25,
                    'phone_detected' => 20,
                    'fullscreen_exit' => 10,
                    'essay_clipboard_attempt' => 3,
                ],
                'cooldown_seconds' => 45,
            ],
        ]);

        $this->actingAs($ctx['student']);
        $this->postJson(
            '/exam-sessions/'.$ctx['session']->session_id.'/proctoring-events/batch',
            $this->essayClipboardBatchPayload($ctx['session'], $ctx['student'], $ctx['quizId']),
        )->assertOk();

        $ctx['session']->refresh();
        $this->assertSame(3, (int) $ctx['session']->violation_score);
    }
}
