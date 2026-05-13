<?php

namespace Tests\Feature;

use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionAnswer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\User;
use App\Services\ProctoringOrchestratorService;
use App\Services\ResultFinalizationService;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResultFinalizationScoringPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_violations_and_legacy_deduct_flag_do_not_reduce_stored_score(): void
    {
        $this->seed(InitialSetupSeeder::class);

        $uniId = (int) DB::table('universities')->value('id');
        $deptId = (int) DB::table('departments')->where('code', 'CS')->value('id');
        $programId = (int) DB::table('programs')->where('code', 'BCS')->value('id');
        $levelId = (int) DB::table('levels')->where('code', '100')->value('id');

        $examiner = User::factory()->create([
            'role' => 'examiner',
            'university_id' => $uniId,
            'email' => 'ex.score.'.Str::random(8).'@test.edu',
            'index_number' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'university_id' => $uniId,
            'department_id' => $deptId,
            'code' => 'CS-SCORE',
            'title' => 'Scoring policy course',
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
            'name' => 'Score Class',
            'section' => null,
            'academic_year' => '2026',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $student = User::query()->where('role', 'student')->firstOrFail();
        DB::table('users')->where('id', $student->id)->update(['class_id' => $classId]);
        DB::table('class_course')->insert([
            'class_id' => $classId,
            'course_id' => $courseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $proctoring = ProctoringOrchestratorService::normalizeProctoringSettings([], null);
        $proctoring['violation_actions'] = ['warn' => true, 'deduct' => true, 'autosubmit' => false];
        $proctoring['violation_deduct_marks_per_flag'] = 99;

        $quiz = Quiz::query()->create([
            'university_id' => $uniId,
            'academic_year_id' => null,
            'term_id' => null,
            'course_id' => $courseId,
            'created_by' => $examiner->id,
            'title' => 'Policy quiz',
            'description' => null,
            'assessment_type' => 'quiz',
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 30,
            'total_marks' => 20,
            'questions_per_student' => 1,
            'randomize_questions' => false,
            'randomize_options' => false,
            'proctoring_settings' => $proctoring,
            'start_time' => null,
            'end_time' => null,
        ]);

        $section = ExamSection::query()->create([
            'exam_id' => $quiz->id,
            'title' => 'A',
            'section_order' => 1,
        ]);

        $question = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Q1',
            'type' => 'mcq',
            'options' => ['a', 'b'],
            'correct_answer' => [0],
            'answer_schema' => null,
            'marks' => 20,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'exam_id' => $quiz->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'submitted',
            'start_time' => now()->subMinutes(10),
            'end_time' => now(),
            'violation_count' => 5,
            'violation_score' => 80,
            'violation_events' => [],
            'risk_state' => 'critical',
            'exam_status' => 'submitted_held',
        ]);

        ExamSessionAnswer::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'answer_payload' => ['choice' => 0],
            'points_awarded' => 18,
            'evaluation_status' => 'auto_graded',
            'evaluation_detail' => [],
        ]);

        app(ResultFinalizationService::class)->syncAfterSubmission($session->fresh(['answers', 'exam']), 120, 'violation_auto_submit');

        $result = Result::query()
            ->where('user_id', $student->id)
            ->where('quiz_id', $quiz->id)
            ->first();

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(18.0, (float) $result->score, 0.001, 'Score must equal earned answer points; examiner reviews violations separately.');
        $this->assertSame('held', $result->status);
    }
}
