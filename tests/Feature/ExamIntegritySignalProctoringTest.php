<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\ProctoringEvent;
use App\Models\User;
use App\Services\SystemSettingsService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamIntegritySignalProctoringTest extends TestCase
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
            'code' => 'CS-INTEGRITY',
            'title' => 'Integrity test course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'IntegrityClass',
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
            'title' => 'Integrity exam',
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

        return ['student' => $student, 'session' => $session, 'quizId' => $quizId];
    }

    public function test_batch_logs_exam_integrity_signal_with_metadata(): void
    {
        $ctx = $this->seedStudentWithActiveSession();
        $student = $ctx['student'];
        $session = $ctx['session'];

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'true', $admin);

        $payload = [
            'events' => [[
                'event_type' => 'exam_integrity_signal',
                'metadata' => [
                    'session_id' => $session->session_id,
                    'student_id' => $student->id,
                    'exam_id' => (int) $session->exam_id,
                    'signal' => 'paste',
                    'question_id' => 9,
                ],
            ]],
        ];

        $this->actingAs($student);
        $response = $this->postJson(
            '/exam-sessions/'.$session->session_id.'/proctoring-events/batch',
            $payload,
        );

        $response->assertOk()
            ->assertJsonPath('status', 'logged');

        $ev = ProctoringEvent::query()->where('event_type', 'exam_integrity_signal')->latest('id')->first();
        $this->assertNotNull($ev);
        $this->assertSame('exam_integrity_signal', $ev->event_type);
        $meta = is_array($ev->metadata) ? $ev->metadata : [];
        $inner = is_array($meta['payload'] ?? null) ? $meta['payload'] : [];
        $this->assertSame('paste', $inner['signal'] ?? null);
        $this->assertSame(9, $inner['question_id'] ?? null);
    }

    public function test_exam_integrity_signal_is_logged_when_institution_proctoring_is_disabled(): void
    {
        $ctx = $this->seedStudentWithActiveSession();
        $student = $ctx['student'];
        $session = $ctx['session'];

        $admin = User::query()->where('role', 'admin')->firstOrFail();
        app(SystemSettingsService::class)->set('enable_proctoring', 'false', $admin);

        $payload = [
            'events' => [[
                'event_type' => 'exam_integrity_signal',
                'metadata' => [
                    'session_id' => $session->session_id,
                    'student_id' => $student->id,
                    'exam_id' => (int) $session->exam_id,
                    'signal' => 'copy',
                ],
            ]],
        ];

        $this->actingAs($student);
        $this->postJson(
            '/exam-sessions/'.$session->session_id.'/proctoring-events/batch',
            $payload,
        )->assertOk()
            ->assertJsonPath('status', 'logged');

        $this->assertDatabaseHas('proctoring_events', [
            'user_id' => $student->id,
            'quiz_id' => (int) $session->exam_id,
            'event_type' => 'exam_integrity_signal',
        ]);
    }
}
