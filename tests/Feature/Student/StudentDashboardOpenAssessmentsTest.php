<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;
use App\Support\AssessmentProctoringDefaults;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AssignmentCourseworkFlowTest;

class StudentDashboardOpenAssessmentsTest extends AssignmentCourseworkFlowTest
{
    public function test_dashboard_lists_open_quiz_published_more_than_seven_days_ago_when_still_in_window(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Long Window Quiz Marker',
            'description' => null,
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now()->subDays(10),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->subDays(2),
            'end_time' => now()->addWeek(),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Long Window Quiz Marker', false)
            ->assertSee(__('Start quiz'), false);
    }

    public function test_dashboard_shows_upcoming_quiz_with_countdown_and_no_start_button(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Upcoming Quiz Marker',
            'description' => null,
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now()->subDay(),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(9),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $quiz->id,
            'class_id' => $ctx['classId'],
        ]);

        $html = $this->actingAs($ctx['student'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Upcoming Quiz Marker', false)
            ->assertSee(__('Opens in'), false)
            ->getContent();

        $this->assertStringContainsString('data-qs-countdown-ends', $html);

        // The Start CTA is now pre-rendered as the *post-expiry* hero swap
        // (so when the countdown reaches 00:00:00 in the browser it can
        // dynamically replace the clock without a page reload). To keep the
        // original intent of this test — the student must not see/click a
        // Start button while the window is still upcoming — we assert that
        // every occurrence of the Start string in the markup is inside an
        // aria-hidden expired-CTA block, never a directly visible button.
        $startLabel = __('Start quiz');
        $positions = [];
        $offset = 0;
        while (($pos = strpos($html, $startLabel, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }

        foreach ($positions as $pos) {
            $before = substr($html, max(0, $pos - 600), min(600, $pos));
            $insideExpiredCta = str_contains($before, '__expired') || str_contains($before, '__hero-expired');
            $ariaHidden = preg_match('/aria-hidden="true"[^>]*>(?:(?!<[^>]+>).)*$/s', $before) === 1
                || str_contains($before, 'aria-hidden="true"');
            $this->assertTrue(
                $insideExpiredCta && $ariaHidden,
                'Any "Start quiz" string in the markup must live inside the aria-hidden, post-expiry hero CTA — never as a directly visible button while the quiz window has not opened.'
            );
        }
    }

    public function test_dashboard_shows_assignment_due_countdown_within_five_days(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];

        $assignment = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Due Soon Assignment Marker',
            'description' => 'Submit your essay.',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'total_marks' => 20,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', false, false, false),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $assignment->id,
            'class_id' => $ctx['classId'],
        ]);

        $html = $this->actingAs($ctx['student'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Due Soon Assignment Marker', false)
            ->assertSee(__('Due in'), false)
            ->getContent();

        $this->assertStringContainsString('data-qs-countdown-ends', $html);
    }
}
