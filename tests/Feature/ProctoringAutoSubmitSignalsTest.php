<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProctoringAutoSubmitSignalsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{student: User, session: ExamSession, quizId: int}
     */
    private function seedProctoringExamSession(array $proctoringSettings = []): array
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
            'code' => 'CS-PROCTOR-AUTO',
            'title' => 'Proctor auto course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'ProctorClass',
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

        $defaults = [
            'face_match_threshold' => 60.0,
            'tab_switch_rules' => [1 => 10, 2 => 40, 3 => 60],
            'phone_detection_enabled' => true,
            'fullscreen_enforced' => true,
            'auto_submit_enabled' => true,
            'violation_weights' => [
                'face_missing' => 10,
                'multiple_faces' => 25,
                'phone_detected' => 20,
                'fullscreen_exit' => 10,
                'essay_clipboard_attempt' => 0,
                'exam_integrity_signal' => 0,
            ],
            'cooldown_seconds' => 45,
            'phone_detection_confidence_threshold' => 0.55,
        ];

        $quizId = DB::table('quizzes')->insertGetId([
            'university_id' => $uniId,
            'course_id' => $courseId,
            'created_by' => $coord->id,
            'title' => 'Proctor auto exam',
            'description' => null,
            'assessment_type' => 'exam',
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'randomize_questions' => false,
            'randomize_options' => false,
            'proctoring_settings' => json_encode(array_merge($defaults, $proctoringSettings)),
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
            'tab_switch_count' => 2,
        ]);

        return ['student' => $student, 'session' => $session, 'quizId' => $quizId];
    }

    public function test_tab_switch_third_strike_auto_submits_with_tab_switch_limit_code(): void
    {
        $ctx = $this->seedProctoringExamSession();
        $student = $ctx['student'];
        $session = $ctx['session'];

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'true', $admin);

        $this->actingAs($student);

        $payload = [
            'event_type' => 'tab_switch',
            'metadata' => [
                'session_id' => $session->session_id,
                'student_id' => $student->id,
                'exam_id' => (int) $session->exam_id,
            ],
        ];

        $res = $this->postJson("/exam-sessions/{$session->session_id}/proctoring-events", $payload);
        $res->assertOk()->assertJsonPath('status', 'submitted_held');

        $fresh = $session->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('tab_switch_limit', $fresh->auto_submit_reason_code);
    }

    public function test_phone_detected_auto_submits_with_phone_detected_code(): void
    {
        $ctx = $this->seedProctoringExamSession();
        $student = $ctx['student'];
        $session = $ctx['session'];

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'true', $admin);

        $this->actingAs($student);

        $payload = [
            'event_type' => 'phone_detected',
            'metadata' => [
                'session_id' => $session->session_id,
                'student_id' => $student->id,
                'exam_id' => (int) $session->exam_id,
                'confidence' => 0.92,
            ],
        ];

        $res = $this->postJson("/exam-sessions/{$session->session_id}/proctoring-events", $payload);
        $res->assertOk()->assertJsonPath('status', 'submitted_held');

        $fresh = $session->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame('phone_detected', $fresh->auto_submit_reason_code);
    }

    public function test_possible_screenshot_attempt_logs_without_auto_submit_by_default(): void
    {
        $ctx = $this->seedProctoringExamSession();
        $student = $ctx['student'];
        $session = $ctx['session'];

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'true', $admin);

        $this->actingAs($student);

        $payload = [
            'event_type' => 'possible_screenshot_attempt',
            'metadata' => [
                'session_id' => $session->session_id,
                'student_id' => $student->id,
                'exam_id' => (int) $session->exam_id,
                'keys' => 'PrintScreen',
            ],
        ];

        $res = $this->postJson("/exam-sessions/{$session->session_id}/proctoring-events", $payload);
        $res->assertOk()->assertJsonPath('status', 'logged');

        $this->assertSame('active', $session->fresh()->status);
    }
}
