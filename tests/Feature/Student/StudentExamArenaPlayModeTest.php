<?php

namespace Tests\Feature\Student;

use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use App\Services\SystemSettingsService;
use App\Support\AssessmentProctoringDefaults;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AssignmentCourseworkFlowTest;

/**
 * Coverage for the super-admin "student exam presentation mode" toggle that switches
 * the student exam runtime between the classic flow and the new arena (gamified) flow.
 *
 * Backend (state, save, submit, proctoring, tab-switch) is unchanged across modes —
 * we only assert that the right Blade view is dispatched.
 */
class StudentExamArenaPlayModeTest extends AssignmentCourseworkFlowTest
{
    private function makeQuizWithMcq(array $ctx, string $title): Quiz
    {
        $examiner = $ctx['examiner'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => $title,
            'description' => 'Arena mode coverage quiz.',
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now()->subHour(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->subHour(),
            'end_time' => now()->addWeek(),
        ]);

        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);

        return $quiz;
    }

    private function startTakeSession(array $ctx, Quiz $quiz): ExamSession
    {
        $section = ExamSection::query()->create([
            'exam_id' => $quiz->id,
            'title' => 'Main',
            'section_order' => 1,
        ]);
        $question = Question::query()->create([
            'quiz_id' => $quiz->id,
            'section_id' => $section->id,
            'question_text' => 'Which is the odd one: Pen, Pencil, Book, Eraser?',
            'type' => 'mcq',
            'options' => ['Pen', 'Pencil', 'Book', 'Eraser'],
            'correct_answer' => 'Book',
            'marks' => 1,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);

        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $quiz->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now()->subMinutes(2),
            'end_time' => null,
            'violation_count' => 0,
            'violation_score' => 0,
            'violation_events' => [],
            'last_event_time' => null,
            'risk_state' => 'normal',
            'exam_status' => 'in_progress',
            'submitted_late' => false,
        ]);
        ExamSessionQuestion::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'display_order' => 1,
        ]);

        return $session;
    }

    private function superAdmin(): User
    {
        $uni = (int) DB::table('universities')->value('id');

        return User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super-arena-'.uniqid('', true).'@test.local',
            'role' => 'admin',
            'is_super_admin' => true,
            'university_id' => $uni,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_super_admin_settings_page_shows_play_mode_toggle(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $super = $this->superAdmin();

        $html = (string) $this->actingAs($super)
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Student exam: presentation mode', $html);
        $this->assertStringContainsString('name="student_exam_play_mode"', $html);
        $this->assertStringContainsString('value="classic"', $html);
        $this->assertStringContainsString('value="arena"', $html);
        $this->assertStringContainsString('Arena (gamified)', $html);
    }

    public function test_non_super_admin_settings_page_hides_play_mode_toggle(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $uni = (int) DB::table('universities')->value('id');

        $limited = User::factory()->create([
            'name' => 'Limited Admin',
            'email' => 'limited-admin-arena-'.uniqid('', true).'@test.local',
            'role' => 'admin',
            'is_super_admin' => false,
            'university_id' => $uni,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($limited)
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->assertDontSee('name="student_exam_play_mode"', false);
    }

    public function test_super_admin_can_persist_arena_play_mode(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $super = $this->superAdmin();
        $settings = app(SystemSettingsService::class);

        $this->actingAs($super)
            ->put(route('admin.settings.update'), [
                'student_exam_play_mode' => 'arena',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame('arena', $settings->get('student_exam_play_mode'));
    }

    public function test_super_admin_arena_selection_round_trips_through_full_form_submission(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $super = $this->superAdmin();
        $settings = app(SystemSettingsService::class);

        // Simulate a realistic save where most other admin toggles are also posted.
        $this->actingAs($super)
            ->put(route('admin.settings.update'), [
                'student_exam_play_mode' => 'arena',
                'enable_otp' => '1',
                'enable_sms' => '1',
                'enable_proctoring' => '1',
                'require_camera_monitoring' => '1',
                'fullscreen_required' => '1',
                'auto_submit_enabled' => '1',
                'enable_ai' => '1',
                'enable_live_sockets' => '1',
                'allow_polling_fallback' => '1',
                'student_dashboard_mobile_wallet' => '0',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame('arena', $settings->get('student_exam_play_mode'));

        // Re-render the index page and assert the "arena" radio is rendered checked
        // (this catches any UI regression where the saved value doesn't surface).
        $html = (string) $this->actingAs($super)
            ->get(route('admin.settings.index', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="student_exam_play_mode"[^>]*value="arena"[^>]*\bchecked\b/',
            $html,
            'The "arena" radio must render with the checked attribute after persisting arena mode.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="student_exam_play_mode"[^>]*value="classic"[^>]*\bchecked\b/',
            $html,
            'The "classic" radio must NOT be checked when the persisted mode is arena.',
        );
    }

    public function test_super_admin_can_flip_arena_back_to_classic(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $super = $this->superAdmin();
        $settings = app(SystemSettingsService::class);
        $settings->set('student_exam_play_mode', 'arena', $super);
        $this->assertSame('arena', $settings->get('student_exam_play_mode'));

        $this->actingAs($super)
            ->put(route('admin.settings.update'), [
                'student_exam_play_mode' => 'classic',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame('classic', $settings->get('student_exam_play_mode'));
    }

    public function test_default_play_mode_renders_classic_take_view(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $quiz = $this->makeQuizWithMcq($ctx, 'Classic by default');
        $session = $this->startTakeSession($ctx, $quiz);

        $this->actingAs($ctx['student']);
        $html = $this->get(route('student.exam.take', $session))->assertOk()->getContent();

        $this->assertStringContainsString('id="exam-meta-aside"', (string) $html);
        $this->assertStringContainsString('id="proctoring-live-aside"', (string) $html);
        $this->assertStringNotContainsString('qs-arena__root', (string) $html);
    }

    public function test_arena_play_mode_renders_arena_take_view_for_quizzes(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $ctx = $this->seedExaminerStudentCourse();

        app(SystemSettingsService::class)->set(
            'student_exam_play_mode',
            'arena',
            $this->superAdmin(),
        );

        $quiz = $this->makeQuizWithMcq($ctx, 'Arena flow');
        $session = $this->startTakeSession($ctx, $quiz);

        $this->actingAs($ctx['student']);
        $html = $this->get(route('student.exam.take', $session))->assertOk()->getContent();

        $this->assertStringContainsString('qs-arena__root', (string) $html);
        $this->assertStringContainsString('id="arena-q-options"', (string) $html);
        // The arena uses one continuous progress bar — no Step 1 / 2 / 3 pills.
        $this->assertStringContainsString('id="arena-progress-bar"', (string) $html);
        $this->assertStringContainsString('id="arena-progress-label"', (string) $html);
        $this->assertStringNotContainsString('id="arena-step-pills"', (string) $html);
        $this->assertStringNotContainsString('id="arena-feedback"', (string) $html);
        // Live camera surface: visible audio bar + feed-status indicator.
        $this->assertStringContainsString('id="arena-mic-bar"', (string) $html);
        $this->assertStringContainsString('id="arena-feed-dot"', (string) $html);
        $this->assertStringContainsString('id="arena-feed-label"', (string) $html);
        $this->assertStringContainsString('id="exam-tab-switch-modal"', (string) $html);
        $this->assertStringContainsString('Tab Switch Detected', (string) $html);
        // Arena layout uses its own meta tag and JS entry — make sure both are present.
        $this->assertStringContainsString('name="qs-exam-play-mode" content="arena"', (string) $html);
        $this->assertStringNotContainsString('id="exam-meta-aside"', (string) $html);
    }

    public function test_arena_play_mode_falls_back_to_classic_for_assignments(): void
    {
        $this->seed(InitialSetupSeeder::class);
        $ctx = $this->seedExaminerStudentCourse();

        app(SystemSettingsService::class)->set(
            'student_exam_play_mode',
            'arena',
            $this->superAdmin(),
        );

        $examiner = $ctx['examiner'];
        $assignment = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Arena toggle on but I am an assignment',
            'description' => 'Essays still use the classic editor.',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now()->subHour(),
            'due_at' => now()->addWeek(),
            'total_marks' => 20,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', false, false, false),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $assignment->id,
            'class_id' => $ctx['classId'],
        ]);
        $section = ExamSection::query()->create([
            'exam_id' => $assignment->id,
            'title' => 'Main',
            'section_order' => 1,
        ]);
        $question = Question::query()->create([
            'quiz_id' => $assignment->id,
            'section_id' => $section->id,
            'question_text' => 'Write a short essay on integrity.',
            'type' => 'essay',
            'marks' => 20,
            'question_order' => 1,
            'pool_status' => 'approved',
        ]);
        $session = ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $assignment->id,
            'session_id' => (string) Str::uuid(),
            'status' => 'active',
            'start_time' => now()->subMinute(),
            'risk_state' => 'normal',
            'exam_status' => 'in_progress',
        ]);
        ExamSessionQuestion::query()->create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'display_order' => 1,
        ]);

        $this->actingAs($ctx['student']);
        $html = $this->get(route('student.exam.take', $session))->assertOk()->getContent();

        // Assignment always uses the classic essay editor layout, never the arena card.
        $this->assertStringNotContainsString('qs-arena__root', (string) $html);
        $this->assertStringContainsString('id="assignment-coursework-panel"', (string) $html);
    }
}
