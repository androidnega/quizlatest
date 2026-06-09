<?php

namespace Tests\Feature\Student;

use App\Models\ExamSession;
use App\Models\Quiz;
use App\Support\AssessmentProctoringDefaults;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AssignmentCourseworkFlowTest;

class StudentExamSessionGateTest extends AssignmentCourseworkFlowTest
{
    public function test_student_can_open_quiz_instructions_while_assignment_session_is_active(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];

        $assignment = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Open Assignment Draft',
            'description' => 'Essay work in progress.',
            'assessment_type' => 'assignment',
            'selected_question_types' => ['essay'],
            'status' => 'published',
            'published_at' => now()->subDay(),
            'due_at' => now()->addWeek(),
            'total_marks' => 20,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('assignment', false, false, false),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $assignment->id,
            'class_id' => $ctx['classId'],
        ]);

        ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $assignment->id,
            'session_id' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'active',
            'start_time' => now()->subHour(),
            'exam_status' => 'active',
            'last_seen_at' => now(),
        ]);

        $quiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Parallel Quiz Gate Test',
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
            ->get(route('student.exam.instructions', $quiz))
            ->assertOk()
            ->assertDontSee(__('You already have a timed assessment in progress'), false);
    }

    public function test_expired_timed_session_is_reconciled_so_new_quiz_can_start(): void
    {
        $ctx = $this->seedExaminerStudentCourse();
        $examiner = $ctx['examiner'];

        $staleQuiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Stale Timed Quiz',
            'description' => null,
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now()->subDays(3),
            'duration_minutes' => 30,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->subDays(2),
            'end_time' => now()->addWeek(),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $staleQuiz->id,
            'class_id' => $ctx['classId'],
        ]);

        ExamSession::query()->create([
            'student_id' => $ctx['student']->id,
            'class_id' => $ctx['classId'],
            'exam_id' => $staleQuiz->id,
            'session_id' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'paused',
            'start_time' => now()->subHours(2),
            'exam_status' => 'active',
            'last_seen_at' => now()->subHour(),
        ]);

        $newQuiz = Quiz::query()->create([
            'university_id' => $examiner->university_id,
            'course_id' => $ctx['courseId'],
            'created_by' => $examiner->id,
            'title' => 'Fresh Quiz After Reconcile',
            'description' => null,
            'assessment_type' => 'quiz',
            'selected_question_types' => ['mcq'],
            'status' => 'published',
            'published_at' => now(),
            'duration_minutes' => 20,
            'total_marks' => 10,
            'questions_per_student' => 1,
            'proctoring_settings' => AssessmentProctoringDefaults::baselineForType('quiz', true, true, true),
            'start_time' => now()->subHour(),
            'end_time' => now()->addWeek(),
        ]);
        DB::table('quiz_class')->insert([
            'quiz_id' => $newQuiz->id,
            'class_id' => $ctx['classId'],
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('student.exam.instructions', $newQuiz))
            ->assertOk()
            ->assertDontSee(__('You already have a timed assessment in progress'), false);

        $this->assertSame(
            'submitted',
            ExamSession::query()
                ->where('student_id', $ctx['student']->id)
                ->where('exam_id', $staleQuiz->id)
                ->value('status'),
        );
    }
}
