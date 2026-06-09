<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;
use App\Support\AssessmentProctoringDefaults;
use App\Services\StudentAssignmentCatalogService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AssignmentCourseworkFlowTest;

class StudentAssignmentCatalogTest extends AssignmentCourseworkFlowTest
{
    public function test_open_assignment_uses_due_date_window_not_only_end_time(): void
    {
        $ctx = $this->seedExaminerStudentCourse();

        $assignment = Quiz::query()->create([
            'university_id' => $ctx['examiner']->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $ctx['examiner']->id,
            'title' => 'Due next week',
            'description' => 'Answer in full sentences with enough detail for grading.',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 0,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'start_time' => null,
            'end_time' => null,
            'due_at' => now()->addWeek(),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $assignment->id,
            'class_id' => $ctx['classId'],
        ]);

        $catalog = app(StudentAssignmentCatalogService::class)->catalogFor($ctx['student']);

        $this->assertSame(1, $catalog['summaryOpen']);
        $this->assertSame($assignment->id, $catalog['courses'][0]['open']->first()->id);
    }

    public function test_dashboard_next_action_points_to_prepare_for_open_assignment(): void
    {
        $ctx = $this->seedExaminerStudentCourse();

        $assignment = Quiz::query()->create([
            'university_id' => $ctx['examiner']->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $ctx['examiner']->id,
            'title' => 'Essay task',
            'description' => 'Write a detailed response for your course module.',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 0,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', true, true, true),
            'due_at' => now()->addDays(3),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $assignment->id,
            'class_id' => $ctx['classId'],
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Essay task'), false)
            ->assertSee(route('student.exam.prepare', $assignment, false), false);
    }
}
