<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;
use App\Support\AssessmentProctoringDefaults;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AssignmentCourseworkFlowTest;

class StudentDashboardNewAssessmentsTest extends AssignmentCourseworkFlowTest
{
    public function test_dashboard_shows_new_for_you_section_for_recently_published_quiz(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Fresh Surface Quiz Marker',
            'description' => null,
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now()->subDay(),
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

        $this->actingAs($ctx['student'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('New for you'), false)
            ->assertSee('Fresh Surface Quiz Marker', false)
            ->assertSee(__('Instructions'), false);
    }
}
