<?php

namespace Tests\Feature;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExaminerProctoringFocusFiltersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{examiner: User, exam: Quiz, otherExam: Quiz, session: ExamSession}
     */
    private function seedTwoExamsOneWithAutoSubmit(): array
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'examiner.proctor.focus.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-FOCUS',
            'title' => 'Focus course',
            'credit_hours' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('examiner_course_assignments')->insert([
            'course_id' => $courseId,
            'examiner_user_id' => $examiner->id,
            'assigned_by' => null,
            'is_active' => true,
            'permissions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $classId = DB::table('classes')->insertGetId([
            'university_id' => $uniId,
            'program_id' => $programId,
            'level_id' => $levelId,
            'name' => 'FocusClass',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mkQuiz = function (string $title) use ($uniId, $courseId, $examiner) {
            $id = DB::table('quizzes')->insertGetId([
                'university_id' => $uniId,
                'course_id' => $courseId,
                'created_by' => $examiner->id,
                'title' => $title,
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

            return Quiz::query()->findOrFail($id);
        };

        $exam = $mkQuiz('Has auto submit');
        $otherExam = $mkQuiz('Clean exam');

        $student = User::query()->where('role', 'student')->firstOrFail();

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $exam->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
            'auto_submit_reason_code' => 'tab_switch_limit',
        ]);

        ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $otherExam->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'risk_state' => 'normal',
            'exam_status' => 'submitted',
            'auto_submit_reason_code' => null,
        ]);

        return compact('examiner', 'exam', 'otherExam', 'session');
    }

    public function test_exams_index_proctoring_focus_tab_switch_limit_lists_matching_assessment_only(): void
    {
        $ctx = $this->seedTwoExamsOneWithAutoSubmit();

        $this->actingAs($ctx['examiner'])
            ->get(route('examiner.exams.index', ['proctoring_focus' => 'tab_switch_limit']))
            ->assertOk()
            ->assertSee('Has auto submit', false)
            ->assertDontSee('Clean exam', false);
    }

    public function test_workspace_sessions_integrity_tab_switch_limit_filters_rows(): void
    {
        $ctx = $this->seedTwoExamsOneWithAutoSubmit();

        $html = $this->actingAs($ctx['examiner'])
            ->get(route('examiner.quizzes.workspace', [
                'exam' => $ctx['exam'],
                'tab' => 'sessions',
                'integrity' => 'tab_switch_limit',
            ]))
            ->assertOk()
            ->getContent();

        $student = User::query()->findOrFail($ctx['session']->student_id);
        $this->assertStringContainsString($student->name, $html);
    }
}
